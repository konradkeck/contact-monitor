<?php

namespace App\Ingest;

use App\Models\Conversation;
use App\Models\IngestItem;

/**
 * Processes ingest items of type 'conversation'.
 *
 * Payload fields:
 *   channel_type              string   email, slack, discord, ticket
 *   subject                   string   optional
 *   started_at                ISO8601  optional
 *   last_message_at           ISO8601  optional
 *   thread_parent_external_id string   optional (Discord/Slack parent thread)
 */
class ConversationProcessor
{
    public function process(IngestItem $item): void
    {
        $payload = $item->payload;

        // Conversations are keyed by (channel_type, system_slug, external_thread_id)
        $conversation = Conversation::withTrashed()
            ->where('channel_type', $payload['channel_type'])
            ->where('system_slug', $item->system_slug)
            ->where('external_thread_id', $item->external_id)
            ->first();

        if ($item->action === 'delete') {
            if ($conversation && ! $conversation->sync_protected) {
                $conversation->delete();
            }
            $item->update(['status' => 'done', 'processed_at' => now()]);

            return;
        }

        $startedAt = ! empty($payload['started_at']) ? \Carbon\Carbon::parse($payload['started_at']) : null;
        $lastMessageAt = ! empty($payload['last_message_at']) ? \Carbon\Carbon::parse($payload['last_message_at']) : null;

        $metaJson = ! empty($payload['meta']) ? $payload['meta'] : null;

        if ($conversation === null) {
            $conversation = Conversation::create([
                'company_id' => null,
                'primary_person_id' => null,
                'channel_type' => $payload['channel_type'],
                'system_type' => $item->system_type,
                'system_slug' => $item->system_slug,
                'subject' => $payload['subject'] ?? null,
                'external_thread_id' => $item->external_id,
                'started_at' => $startedAt,
                'last_message_at' => $lastMessageAt,
                'meta_json' => $metaJson,
            ]);
        } else {
            if ($conversation->trashed()) {
                $conversation->restore();
            }

            $updates = [];

            // Update subject if we now have one and didn't before
            if (! $conversation->subject && ! empty($payload['subject'])) {
                $updates['subject'] = $payload['subject'];
            }

            // Update system_type if missing
            if (! $conversation->system_type) {
                $updates['system_type'] = $item->system_type;
            }

            // Update meta_json if we have new data
            if ($metaJson && $metaJson !== $conversation->meta_json) {
                $updates['meta_json'] = array_merge($conversation->meta_json ?? [], $metaJson);
            }

            // Extend timestamps (never shrink them)
            if ($startedAt && (! $conversation->started_at || $startedAt < $conversation->started_at)) {
                $updates['started_at'] = $startedAt;
            }
            if ($lastMessageAt && (! $conversation->last_message_at || $lastMessageAt > $conversation->last_message_at)) {
                $updates['last_message_at'] = $lastMessageAt;
            }

            if (! empty($updates)) {
                $conversation->update($updates);
            }
        }

        $item->update([
            'status' => 'done',
            'entity_type' => Conversation::class,
            'entity_id' => $conversation->id,
            'processed_at' => now(),
        ]);
    }
}
