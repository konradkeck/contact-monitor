<?php

namespace App\Mcp\Resources;

use App\Models\Conversation;

class ConversationGetResource
{
    public static function descriptor(): array
    {
        return [
            'uri'         => 'conversations://{id}',
            'name'        => 'Conversation Details',
            'description' => 'Conversation with messages. depth param: headers (default) / recent (last 20) / full (all).',
            'mimeType'    => 'application/json',
        ];
    }

    public static function read(array $params): array // phpcs:ignore
    {
        $depth = $params['depth'] ?? 'headers';

        $conversation = Conversation::with(['company:id,name', 'participants.identity'])
            ->findOrFail($params['id']);

        $result = [
            'id'              => $conversation->id,
            'subject'         => $conversation->subject,
            'channel_type'    => $conversation->channel_type,
            'system_type'     => $conversation->system_type,
            'system_slug'     => $conversation->system_slug,
            'company_id'      => $conversation->company_id,
            'company_name'    => $conversation->company?->name,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'archived_at'     => $conversation->archived_at?->toIso8601String(),
            'participants'    => $conversation->participants->map(fn($p) => [
                'identity_type'  => $p->identity?->type,
                'identity_value' => $p->identity?->value,
            ])->values()->all(),
        ];

        if ($depth === 'recent') {
            $messages = $conversation->messages()
                ->whereNull('parent_message_id')
                ->orderByDesc('occurred_at')
                ->limit(20)
                ->get();
            $result['messages'] = self::formatMessages($messages);
        } elseif ($depth === 'full') {
            $messages = $conversation->messages()
                ->whereNull('parent_message_id')
                ->orderBy('occurred_at')
                ->get();
            $result['messages'] = self::formatMessages($messages);
        }

        return $result;
    }

    private static function formatMessages($messages): array
    {
        return $messages->map(fn($m) => [
            'id'        => $m->id,
            'direction' => $m->direction,
            'sender'    => $m->author_name,
            'body'        => $m->body_text ?? strip_tags($m->body_html ?? ''),
            'occurred_at' => $m->occurred_at?->toIso8601String(),
        ])->values()->all();
    }
}
