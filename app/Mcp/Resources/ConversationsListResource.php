<?php

namespace App\Mcp\Resources;

use App\Models\Conversation;

class ConversationsListResource
{
    public static function descriptor(): array
    {
        return [
            'uri'         => 'conversations://list',
            'name'        => 'Conversations List',
            'description' => 'Conversation headers. Params: page, company_id, person_id, channel_type.',
            'mimeType'    => 'application/json',
        ];
    }

    public static function read(array $params): array
    {
        $page         = max(1, (int) ($params['page'] ?? 1));
        $companyId    = $params['company_id'] ?? null;
        $personId     = $params['person_id'] ?? null;
        $channelType  = $params['channel_type'] ?? null;

        $query = Conversation::with(['company:id,name', 'messages' => fn($q) => $q->latest()->limit(1)])
            ->whereNull('archived_at');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($personId) {
            $query->whereHas('participants', fn($q) => $q->whereHas('identity', fn($q2) => $q2->where('person_id', $personId)));
        }

        if ($channelType) {
            $query->where('channel_type', $channelType);
        }

        $paginator = $query->orderByDesc('last_message_at')->paginate(25, ['*'], 'page', $page);

        return [
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'items'        => $paginator->map(fn($c) => [
                'id'              => $c->id,
                'subject'         => $c->subject,
                'channel_type'    => $c->channel_type,
                'system_type'     => $c->system_type,
                'system_slug'     => $c->system_slug,
                'company_id'      => $c->company_id,
                'company_name'    => $c->company?->name,
                'message_count'   => $c->messages_count ?? null,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
