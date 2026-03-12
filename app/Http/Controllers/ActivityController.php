<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $timelinePage = $this->baseQuery()->cursorPaginate(25);

        $convSubjectMap = $this->buildConvSubjectMap($timelinePage->items());

        $convSystems = DB::table('activities')
            ->where('type', 'conversation')
            ->whereRaw("meta_json->>'channel_type' IS NOT NULL")
            ->select(
                DB::raw("meta_json->>'channel_type' as channel_type"),
                DB::raw("meta_json->>'system_slug' as system_slug"),
                DB::raw("meta_json->>'system_type' as system_type")
            )
            ->distinct()->get()->sortBy('channel_type')->values();

        $activityTypes = DB::table('activities')
            ->where('type', '!=', 'conversation')
            ->distinct()->pluck('type')
            ->sortBy(fn($t) => $t === 'note' ? 'zzz' : $t) // 'note' (Other) always last
            ->values();

        // Initial stats (unfiltered)
        $typeCounts = DB::table('activities')
            ->where('type', '!=', 'conversation')
            ->whereNull('deleted_at')
            ->select('type', DB::raw('count(*) as cnt'))
            ->groupBy('type')
            ->orderByDesc('cnt')
            ->get();

        $convCounts = DB::table('activities')
            ->where('type', 'conversation')
            ->whereNull('deleted_at')
            ->selectRaw("
                COALESCE(
                    meta_json->>'channel_type',
                    CASE meta_json->>'system_type'
                        WHEN 'whmcs'       THEN 'ticket'
                        WHEN 'metricscube' THEN 'ticket'
                        WHEN 'discord'     THEN 'discord'
                        WHEN 'slack'       THEN 'slack'
                        WHEN 'imap'        THEN 'email'
                        WHEN 'gmail'       THEN 'email'
                        ELSE 'conversation'
                    END
                ) as channel_type,
                meta_json->>'system_slug' as system_slug,
                count(*) as cnt
            ")
            ->groupByRaw("1, 2")
            ->orderByDesc('cnt')
            ->get();

        $totalConv = $convCounts->sum('cnt');

        return view('activity.index', compact(
            'timelinePage', 'convSubjectMap', 'convSystems', 'activityTypes',
            'typeCounts', 'convCounts', 'totalConv'
        ));
    }

    public function timeline(Request $request)
    {
        $query = $this->baseQuery();

        if ($types = $request->get('types')) {
            $query->whereIn('type', (array) $types);
        }
        if ($systems = $request->get('systems')) {
            $pairs = array_filter((array) $systems);
            if (!empty($pairs)) {
                $query->where(function ($q) use ($pairs) {
                    foreach ($pairs as $pair) {
                        [$ch, $slug] = array_pad(explode('|', $pair, 2), 2, '');
                        $q->orWhere(function ($sq) use ($ch, $slug) {
                            $sq->whereRaw("meta_json->>'channel_type' = ?", [$ch])
                               ->whereRaw("meta_json->>'system_slug' = ?", [$slug]);
                        });
                    }
                });
            }
        }
        if ($excludeType = $request->get('exclude_type')) {
            $query->where('type', '!=', $excludeType);
        }
        if ($from = $request->get('from')) {
            $query->whereDate('occurred_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('occurred_at', '<=', $to);
        }

        $page = $query->cursorPaginate(25, ['*'], 'cursor', $request->get('cursor'));

        return view('activity.partials.timeline-items', [
            'activities'     => $page->items(),
            'nextCursor'     => $page->nextCursor()?->encode(),
            'convSubjectMap' => $this->buildConvSubjectMap($page->items()),
        ]);
    }

    public function stats(Request $request)
    {
        $query = Activity::query();

        if ($types = $request->get('types')) {
            $query->whereIn('type', (array) $types);
        }
        if ($excludeType = $request->get('exclude_type')) {
            $query->where('type', '!=', $excludeType);
        }
        if ($systems = $request->get('systems')) {
            $pairs = array_filter((array) $systems);
            if (!empty($pairs)) {
                $query->where(function ($q) use ($pairs) {
                    foreach ($pairs as $pair) {
                        [$ch, $slug] = array_pad(explode('|', $pair, 2), 2, '');
                        $q->orWhere(function ($sq) use ($ch, $slug) {
                            $sq->whereRaw("meta_json->>'channel_type' = ?", [$ch])
                               ->whereRaw("meta_json->>'system_slug' = ?", [$slug]);
                        });
                    }
                });
            }
        }
        if ($from = $request->get('from')) {
            $query->whereDate('occurred_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('occurred_at', '<=', $to);
        }

        // Non-conversation type counts
        $typeCounts = (clone $query)
            ->where('type', '!=', 'conversation')
            ->select('type', DB::raw('count(*) as cnt'))
            ->groupBy('type')
            ->orderByDesc('cnt')
            ->get();

        // Conversation counts broken down by effective channel_type + system_slug
        $convCounts = (clone $query)
            ->where('type', 'conversation')
            ->selectRaw("
                COALESCE(
                    meta_json->>'channel_type',
                    CASE meta_json->>'system_type'
                        WHEN 'whmcs'        THEN 'ticket'
                        WHEN 'metricscube'  THEN 'ticket'
                        WHEN 'discord'      THEN 'discord'
                        WHEN 'slack'        THEN 'slack'
                        WHEN 'imap'         THEN 'email'
                        WHEN 'gmail'        THEN 'email'
                        ELSE 'conversation'
                    END
                ) as channel_type,
                meta_json->>'system_slug' as system_slug,
                count(*) as cnt
            ")
            ->groupByRaw("1, 2")
            ->orderByDesc('cnt')
            ->get();

        $totalConv = $convCounts->sum('cnt');

        return view('partials.activity-stats', compact('typeCounts', 'convCounts', 'totalConv'));
    }

    private function baseQuery()
    {
        return Activity::with(['company', 'person'])->orderByDesc('occurred_at');
    }

    private function buildConvSubjectMap(array $activities): array
    {
        $extIds = [];
        foreach ($activities as $activity) {
            $m = $activity->meta_json ?? [];
            // Resolve effective channel type — meta_json may store channel_type directly,
            // or only system_type (e.g. WHMCS activities store system_type='whmcs', no channel_type key)
            $effectiveChannelType = $m['channel_type'] ?? match($m['system_type'] ?? '') {
                'whmcs', 'metricscube' => 'ticket',
                default => null,
            };
            if ($effectiveChannelType === 'ticket' && !empty($m['conversation_external_id'])) {
                $extIds[] = $m['conversation_external_id'];
            }
            $mcType = $m['mc_type'] ?? '';
            if (in_array($mcType, ['Opened Ticket', 'Closed Ticket', 'Ticket Replied'], true) && !empty($m['relation_id'])) {
                $extIds[] = 'ticket_' . $m['relation_id'];
            }
        }
        if (empty($extIds)) {
            return [];
        }
        $map = [];
        DB::table('conversations')
            ->whereIn('external_thread_id', array_unique($extIds))
            ->select('id', 'external_thread_id', 'subject')
            ->get()
            ->each(function ($c) use (&$map) {
                $map[$c->external_thread_id] = ['id' => $c->id, 'subject' => $c->subject];
            });
        return $map;
    }
}
