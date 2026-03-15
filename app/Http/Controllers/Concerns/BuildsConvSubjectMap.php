<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;

trait BuildsConvSubjectMap
{
    private function buildConvSubjectMap(array $activities): array
    {
        $ticketExtIds = [];
        $chatExtIds   = [];

        foreach ($activities as $activity) {
            $m = $activity->meta_json ?? [];
            $effectiveChannelType = $m['channel_type'] ?? match ($m['system_type'] ?? '') {
                'whmcs', 'metricscube' => 'ticket',
                default => null,
            };
            if ($effectiveChannelType === 'ticket' && ! empty($m['conversation_external_id'])) {
                $ticketExtIds[] = $m['conversation_external_id'];
            }
            $mcType = $m['mc_type'] ?? '';
            if (in_array($mcType, ['Opened Ticket', 'Closed Ticket', 'Ticket Replied'], true) && ! empty($m['relation_id'])) {
                $ticketExtIds[] = 'ticket_'.$m['relation_id'];
            }
            if (in_array($effectiveChannelType, ['slack', 'discord'], true) && ! empty($m['conversation_external_id'])) {
                $chatExtIds[] = $m['conversation_external_id'];
            }
        }

        $map = [];

        if (! empty($ticketExtIds)) {
            DB::table('conversations')
                ->whereIn('external_thread_id', array_unique($ticketExtIds))
                ->whereNull('deleted_at')
                ->select('id', 'external_thread_id', 'subject')
                ->get()
                ->each(function ($c) use (&$map) {
                    $map[$c->external_thread_id] = ['id' => $c->id, 'subject' => $c->subject];
                });
        }

        if (! empty($chatExtIds)) {
            $chatConvs = DB::table('conversations')
                ->whereIn('external_thread_id', array_unique($chatExtIds))
                ->whereNull('deleted_at')
                ->select('id', 'external_thread_id')
                ->get();

            $convIdToExtId = $chatConvs->pluck('external_thread_id', 'id')->all();
            $convIds       = array_keys($convIdToExtId);

            if (! empty($convIds)) {
                $byConv = DB::table('conversation_messages as cm')
                    ->leftJoin('identities as i', function ($join) {
                        $join->on('i.id', '=', 'cm.identity_id')->whereNull('i.deleted_at');
                    })
                    ->whereIn('cm.conversation_id', $convIds)
                    ->whereNull('cm.deleted_at')
                    ->whereNotNull('cm.author_name')
                    ->selectRaw("cm.conversation_id, COALESCE(i.meta_json->>'display_name', cm.author_name) as display_name")
                    ->distinct()
                    ->get()
                    ->groupBy('conversation_id');

                foreach ($convIdToExtId as $convId => $extId) {
                    $names = ($byConv[$convId] ?? collect())
                        ->pluck('display_name')
                        ->filter()
                        ->unique()
                        ->values();
                    $str = $names->take(6)->implode(', ');
                    if ($names->count() > 6) {
                        $str .= ', …';
                    }
                    $map[$extId] = ['id' => $convId, 'participants' => $str ?: null];
                }
            }
        }

        return $map;
    }

    /**
     * Pre-compute display data on each activity for the timeline-items partial.
     * Attaches a `_display` property to each activity model.
     */
    private function prepareTimelineDisplay(array $activities, array $convSubjectMap): void
    {
        foreach ($activities as $activity) {
            $activity->_display = $activity->timelineDisplayData($convSubjectMap);
        }
    }

    /**
     * Return external_thread_ids of conversations matching the system filter rules.
     * Mirrors the logic in ConversationController::applySystemFilters().
     */
    private function filteredConversationExtIds(array $filterDomains, array $filterEmails, array $filterSubjects): array
    {
        if (empty($filterDomains) && empty($filterEmails) && empty($filterSubjects)) {
            return [];
        }

        return DB::table('conversations')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($filterDomains, $filterEmails, $filterSubjects) {
                foreach ($filterSubjects as $subject) {
                    $q->orWhereRaw('LOWER(subject) LIKE ?', ['%'.strtolower($subject).'%']);
                }
                if (! empty($filterDomains) || ! empty($filterEmails)) {
                    $q->orWhereExists(function ($sub) use ($filterDomains, $filterEmails) {
                        $sub->select(DB::raw(1))
                            ->from('conversation_messages as cm_f')
                            ->join('identities as i_f', 'i_f.id', '=', 'cm_f.identity_id')
                            ->whereColumn('cm_f.conversation_id', 'conversations.id')
                            ->where('i_f.type', 'email')
                            ->where(function ($q2) use ($filterDomains, $filterEmails) {
                                foreach ($filterDomains as $domain) {
                                    $q2->orWhereRaw('LOWER(i_f.value) LIKE ?', ['%@'.strtolower($domain)]);
                                }
                                if (! empty($filterEmails)) {
                                    $q2->orWhereIn(DB::raw('LOWER(i_f.value)'), array_map('strtolower', $filterEmails));
                                }
                            });
                    });
                }
            })
            ->pluck('external_thread_id')
            ->filter()
            ->all();
    }

    /**
     * Build a SQL fragment + bindings for "conversation c matches filter rules".
     * Assumes `c` is the alias for the conversations table in the surrounding query.
     */
    private function buildConvFilterSql(array $filterDomains, array $filterEmails, array $filterSubjects): array
    {
        $conditions = [];
        $bindings   = [];

        foreach ($filterSubjects as $subject) {
            $conditions[] = 'LOWER(c.subject) LIKE ?';
            $bindings[]   = '%'.strtolower($subject).'%';
        }

        if (! empty($filterDomains) || ! empty($filterEmails)) {
            $emailConds = [];
            foreach ($filterDomains as $domain) {
                $emailConds[] = 'LOWER(i_f.value) LIKE ?';
                $bindings[]   = '%@'.strtolower($domain);
            }
            if (! empty($filterEmails)) {
                $ph           = implode(',', array_fill(0, count($filterEmails), '?'));
                $emailConds[] = "LOWER(i_f.value) IN ({$ph})";
                foreach ($filterEmails as $email) {
                    $bindings[] = strtolower($email);
                }
            }
            $conditions[] = 'EXISTS (
                SELECT 1 FROM conversation_messages cm_f
                JOIN identities i_f ON i_f.id = cm_f.identity_id AND i_f.deleted_at IS NULL
                WHERE cm_f.conversation_id = c.id
                  AND cm_f.deleted_at IS NULL
                  AND i_f.type = \'email\'
                  AND ('.implode(' OR ', $emailConds).')
            )';
        }

        if (empty($conditions)) {
            return ['FALSE', []];
        }

        return ['('.implode(' OR ', $conditions).')', $bindings];
    }

    /**
     * Exclude activities whose linked conversation matches filter rules.
     * Uses correlated NOT EXISTS subqueries to avoid PostgreSQL type-inference issues.
     */
    private function excludeFilteredActivities($query, array $filterDomains, array $filterEmails, array $filterSubjects): void
    {
        if (empty($filterDomains) && empty($filterEmails) && empty($filterSubjects)) {
            return;
        }

        [$filterSql, $filterBindings] = $this->buildConvFilterSql($filterDomains, $filterEmails, $filterSubjects);

        $query->whereRaw(
            "(meta_json->>'conversation_external_id' IS NULL
            OR NOT EXISTS (
                SELECT 1 FROM conversations c
                WHERE c.external_thread_id = (activities.meta_json->>'conversation_external_id')
                  AND c.system_slug        = (activities.meta_json->>'system_slug')
                  AND {$filterSql}
                  AND c.deleted_at IS NULL
            ))",
            $filterBindings
        )->whereRaw(
            "(meta_json->>'relation_id' IS NULL
            OR NOT EXISTS (
                SELECT 1 FROM conversations c
                WHERE c.external_thread_id = 'ticket_' || (activities.meta_json->>'relation_id')
                  AND {$filterSql}
                  AND c.deleted_at IS NULL
            ))",
            $filterBindings
        );
    }

    /**
     * Restrict activities to only those linked to conversations matching filter rules.
     */
    private function includeOnlyFilteredActivities($query, array $filterDomains, array $filterEmails, array $filterSubjects): void
    {
        if (empty($filterDomains) && empty($filterEmails) && empty($filterSubjects)) {
            $query->whereRaw('1=0');

            return;
        }

        [$filterSql, $filterBindings] = $this->buildConvFilterSql($filterDomains, $filterEmails, $filterSubjects);

        $query->where(function ($q) use ($filterSql, $filterBindings) {
            $q->whereRaw(
                "(meta_json->>'conversation_external_id' IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM conversations c
                    WHERE c.external_thread_id = (activities.meta_json->>'conversation_external_id')
                      AND c.system_slug        = (activities.meta_json->>'system_slug')
                      AND {$filterSql}
                      AND c.deleted_at IS NULL
                ))",
                $filterBindings
            )->orWhereRaw(
                "(meta_json->>'relation_id' IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM conversations c
                    WHERE c.external_thread_id = 'ticket_' || (activities.meta_json->>'relation_id')
                      AND {$filterSql}
                      AND c.deleted_at IS NULL
                ))",
                $filterBindings
            );
        });
    }
}
