<?php

namespace App\Mcp\Resources;

use App\Models\Note;

class NotesListResource
{
    public static function descriptor(): array
    {
        return [
            'uri'         => 'notes://list',
            'name'        => 'Notes List',
            'description' => 'Notes filtered by entity. Params: entity_type (App\\Models\\Company etc.), entity_id, page.',
            'mimeType'    => 'application/json',
        ];
    }

    public static function read(array $params): array
    {
        $entityType = $params['entity_type'] ?? null;
        $entityId   = $params['entity_id'] ?? null;
        $page       = max(1, (int) ($params['page'] ?? 1));

        $query = Note::with('user:id,name')->orderByDesc('created_at');

        if ($entityType && $entityId) {
            $query->whereHas('links', fn($q) => $q->where('linkable_type', $entityType)->where('linkable_id', $entityId));
        }

        $paginator = $query->paginate(25, ['*'], 'page', $page);

        return [
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'items'        => $paginator->map(fn($n) => [
                'id'         => $n->id,
                'content'    => $n->content,
                'source'     => $n->source,
                'user_name'  => $n->user?->name,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
