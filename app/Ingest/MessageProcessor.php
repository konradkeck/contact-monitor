<?php

namespace App\Ingest;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Identity;
use App\Models\IngestItem;
use App\Models\MessageAttachment;
use Illuminate\Support\Facades\Log;

/**
 * Processes ingest items of type 'message'.
 *
 * Payload fields:
 *   conversation_external_id   string   required – maps to conversation.external_thread_id
 *   conversation_channel_type  string   required
 *   thread_parent_message_id   string   optional – thread_key for replies
 *   sender_external_id         string   optional – identity value (email addr, user ID)
 *   sender_identity_type       string   optional – email, slack_user, discord_user (default: email)
 *   sender_name                string   optional
 *   body_text                  string   optional
 *   body_html                  string   optional
 *   occurred_at                ISO8601  required
 *   edited_at                  ISO8601  optional
 *   direction_hint             string   customer|internal|system (default: customer)
 *   source_url                 string   optional
 *   attachments                array    optional [{external_id, filename, content_type, size, source_url}]
 */
class MessageProcessor
{
    public function process(IngestItem $item): void
    {
        $payload = $item->payload;

        // Resolve conversation
        $conversation = Conversation::withTrashed()
            ->where('channel_type', $payload['conversation_channel_type'])
            ->where('system_slug', $item->system_slug)
            ->where('external_thread_id', $payload['conversation_external_id'])
            ->first();

        if ($conversation === null) {
            // Conversation not yet ingested – create a stub
            $conversation = Conversation::create([
                'company_id' => null,
                'primary_person_id' => null,
                'channel_type' => $payload['conversation_channel_type'],
                'system_type' => $item->system_type,
                'system_slug' => $item->system_slug,
                'external_thread_id' => $payload['conversation_external_id'],
                'started_at' => ! empty($payload['occurred_at'])
                    ? \Carbon\Carbon::parse($payload['occurred_at'])
                    : null,
            ]);
        } elseif ($conversation->trashed()) {
            $conversation->restore();
        }

        if ($item->action === 'delete') {
            $msg = ConversationMessage::withTrashed()
                ->where('conversation_id', $conversation->id)
                ->where('external_id', $item->external_id)
                ->first();

            if ($msg && ! $msg->sync_protected) {
                $msg->delete();
                $this->syncConversationStats($conversation);
            }

            $item->update(['status' => 'done', 'processed_at' => now()]);

            return;
        }

        // Resolve identity
        $identity = null;
        $authorName = $payload['sender_name'] ?? 'Unknown';

        if (! empty($payload['sender_external_id'])) {
            $identityType = $payload['sender_identity_type'] ?? 'email';
            $senderValue = $payload['sender_external_id'];
            $senderNorm = strtolower(trim($senderValue));

            $identity = Identity::withTrashed()
                ->where('type', $identityType)
                ->where('system_slug', $item->system_slug)
                ->where('value_normalized', $senderNorm)
                ->first();

            if ($identity === null) {
                $identity = Identity::create([
                    'person_id' => null,
                    'type' => $identityType,
                    'system_slug' => $item->system_slug,
                    'value' => $senderValue,
                    'value_normalized' => $senderNorm,
                    'meta_json' => array_filter([
                        'display_name' => $payload['sender_name'] ?? null,
                        'system_type' => $item->system_type,
                    ]),
                ]);
            } elseif ($identity->trashed()) {
                $identity->restore();
            }

            if ($identity->person?->full_name ?? null) {
                $authorName = $identity->person->first_name.' '.$identity->person->last_name;
            } elseif (! empty($payload['sender_name'])) {
                $authorName = $payload['sender_name'];
            }
        }

        $occurredAt = \Carbon\Carbon::parse($payload['occurred_at']);

        // Find or create message by (conversation_id, external_id)
        $message = ConversationMessage::withTrashed()
            ->where('conversation_id', $conversation->id)
            ->where('external_id', $item->external_id)
            ->first();

        $direction = $this->resolveDirection($payload, $identity);

        // Auto-mark identity as team member when the message comes from our side
        if ($direction === 'internal' && $identity !== null && ! $identity->is_team_member) {
            $identity->update(['is_team_member' => true]);
        }

        $attrs = [
            'conversation_id' => $conversation->id,
            'external_id' => $item->external_id,
            'identity_id' => $identity?->id,
            'author_name' => $authorName,
            'direction' => $direction,
            'body_text' => $payload['body_text'] ?? null,
            'body_html' => $payload['body_html'] ?? null,
            'thread_key' => $payload['thread_parent_message_id'] ?? null,
            'source_url' => $payload['source_url'] ?? null,
            'meta_json' => ! empty($payload['meta']) ? $payload['meta'] : null,
            'edited_at' => ! empty($payload['edited_at'])
                ? \Carbon\Carbon::parse($payload['edited_at'])
                : null,
            'occurred_at' => $occurredAt,
        ];

        if ($message === null) {
            $message = ConversationMessage::create($attrs);
        } else {
            if ($message->trashed()) {
                $message->restore();
            }
            if (! $message->sync_protected) {
                $message->update($attrs);
            }
        }

        // Sync attachments
        $this->syncAttachments($message, $payload['attachments'] ?? []);

        // Update conversation stats
        $this->syncConversationStats($conversation);

        $item->update([
            'status' => 'done',
            'entity_type' => ConversationMessage::class,
            'entity_id' => $message->id,
            'processed_at' => now(),
        ]);
    }

    private function resolveDirection(array $payload, ?Identity $identity): string
    {
        $hint = $payload['direction_hint'] ?? 'customer';

        if (in_array($hint, ['customer', 'internal', 'system'], true)) {
            return $hint;
        }

        Log::warning('MessageProcessor: unknown direction_hint value', [
            'direction_hint' => $hint,
            'external_id' => $payload['conversation_external_id'] ?? null,
        ]);

        return 'customer';
    }

    private function syncAttachments(ConversationMessage $message, array $attachments): void
    {
        foreach ($attachments as $att) {
            if (empty($att['filename'])) {
                continue;
            }

            MessageAttachment::updateOrCreate(
                [
                    'conversation_message_id' => $message->id,
                    'external_id' => $att['external_id'] ?? null,
                    'filename' => $att['filename'],
                ],
                [
                    'content_type' => $att['content_type'] ?? null,
                    'size' => isset($att['size']) ? (int) $att['size'] : null,
                    'source_url' => $att['source_url'] ?? null,
                    'meta_json' => $att['meta'] ?? null,
                ]
            );
        }
    }

    private function syncConversationStats(Conversation $conversation): void
    {
        $stats = ConversationMessage::where('conversation_id', $conversation->id)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as cnt, MIN(occurred_at) as first_at, MAX(occurred_at) as last_at')
            ->first();

        $updates = ['message_count' => $stats->cnt ?? 0];

        if ($stats->first_at && (! $conversation->started_at || $stats->first_at < $conversation->started_at)) {
            $updates['started_at'] = $stats->first_at;
        }
        if ($stats->last_at && (! $conversation->last_message_at || $stats->last_at > $conversation->last_message_at)) {
            $updates['last_message_at'] = $stats->last_at;
        }

        $conversation->update($updates);
    }
}
