<?php

namespace App\Ingest;

use App\Models\Account;
use App\Models\Activity;
use App\Models\Conversation;
use App\Models\IngestItem;

/**
 * Processes ingest items of type 'activity'.
 *
 * Payload fields:
 *   activity_type          string   renewal, payment, cancellation, note, etc.
 *   account_external_id    string   optional – links activity to a company via account
 *   account_system_type    string   optional – override system_type for account lookup (e.g. MetricsCube→WHMCS)
 *   account_system_slug    string   optional – override system_slug for account lookup
 *   description            string   optional
 *   occurred_at            ISO8601  required
 *   target_url             string   optional
 *   meta                   array    optional
 */
class ActivityProcessor
{
    public function process(IngestItem $item): void
    {
        $payload = $item->payload;

        // Resolve company via account
        $companyId         = null;
        $personId          = null;
        $accountSystemSlug = $payload['account_system_slug'] ?? $item->system_slug;

        if (!empty($payload['account_external_id'])) {
            $accountSystemType = $payload['account_system_type'] ?? $item->system_type;

            $account = Account::where('system_type', $accountSystemType)
                ->where('system_slug', $accountSystemSlug)
                ->where('external_id', $payload['account_external_id'])
                ->first();

            $companyId = $account?->company_id;
        }

        // Find existing activity by ingest meta
        $activity = Activity::whereJsonContains('meta_json->ingest_key', $item->idempotency_key)->first();

        if ($item->action === 'delete') {
            $activity?->delete();
            $item->update(['status' => 'done', 'processed_at' => now()]);
            return;
        }

        $occurredAt = \Carbon\Carbon::parse($payload['occurred_at']);

        $meta = array_merge(
            $payload['meta'] ?? [],
            [
                'ingest_key'   => $item->idempotency_key,
                'system_type'  => $item->system_type,
                'system_slug'  => $item->system_slug,
                'external_id'  => $item->external_id,
                'description'  => $payload['description'] ?? null,
            ]
        );

        // For ticket-type MetricsCube activities, try to link to the conversation
        $targetUrl = $payload['target_url'] ?? null;
        $mcType    = $meta['mc_type'] ?? '';
        if (!$targetUrl && in_array($mcType, ['Opened Ticket', 'Closed Ticket', 'Ticket Replied'], true)) {
            // For "Ticket Replied": relation_id is the reply log ID, not ticket ID.
            // Extract the actual WHMCS ticket ID from the description.
            if ($mcType === 'Ticket Replied') {
                preg_match('/ticket to #(\d+)/i', $meta['description'] ?? '', $m);
                $ticketId = $m[1] ?? null;
            } else {
                $ticketId = $meta['relation_id'] ?? null;
            }
            if ($ticketId) {
                $conv = Conversation::where('channel_type', 'ticket')
                    ->where('external_thread_id', 'ticket_' . $ticketId)
                    ->first();
                if ($conv) {
                    $targetUrl = '/conversations/' . $conv->id;
                }
            }
        }

        // For discord/slack/imap activities, link to the conversation via conversation_external_id
        if (!empty($meta['conversation_external_id'])) {
            $convQuery = Conversation::where('system_type', $item->system_type)
                ->where('system_slug', $item->system_slug)
                ->where('external_thread_id', $meta['conversation_external_id']);
            if (!empty($meta['channel_type'])) {
                $convQuery->where('channel_type', $meta['channel_type']);
            }
            $conv = $convQuery->first();
            if ($conv) {
                if (!$targetUrl) {
                    $targetUrl = '/conversations/' . $conv->id;
                }
                // Inherit company_id from conversation if not already resolved via account
                if (!$companyId) {
                    $companyId = $conv->company_id;
                }
            }
        }

        if ($activity === null) {
            $activity = Activity::create([
                'company_id'     => $companyId,
                'person_id'      => $personId,
                'type'           => $payload['activity_type'] ?? 'note',
                'occurred_at'    => $occurredAt,
                'target_url'     => $targetUrl,
                'meta_json'      => $meta,
            ]);
        } else {
            $activity->update([
                'company_id'  => $companyId ?? $activity->company_id,
                'occurred_at' => $occurredAt,
                'target_url'  => $targetUrl ?? $activity->target_url,
                'meta_json'   => $meta,
            ]);
        }

        $item->update([
            'status'      => 'done',
            'entity_type' => Activity::class,
            'entity_id'   => $activity->id,
            'processed_at' => now(),
        ]);
    }
}
