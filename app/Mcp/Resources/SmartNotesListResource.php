<?php

namespace App\Mcp\Resources;

use App\Models\SmartNote;

class SmartNotesListResource
{
    public static function descriptor(): array
    {
        return [
            'uri'         => 'smart_notes://list',
            'name'        => 'Smart Notes List',
            'description' => 'Smart notes list. Params: status (unrecognized/recognized), page.',
            'mimeType'    => 'application/json',
        ];
    }

    public static function read(array $params): array
    {
        $status = $params['status'] ?? null;
        $page   = max(1, (int) ($params['page'] ?? 1));

        $query = SmartNote::with('filter:id,type')->orderByDesc('occurred_at');

        if ($status === 'unrecognized') {
            $query->scopeUnrecognized($query);
        } elseif ($status === 'recognized') {
            $query->scopeRecognized($query);
        }

        $paginator = $query->paginate(25, ['*'], 'page', $page);

        return [
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'items'        => $paginator->map(fn($sn) => [
                'id'          => $sn->id,
                'status'      => $sn->status,
                'source_type' => $sn->source_type,
                'content'     => $sn->content,
                'filter_type' => $sn->filter?->type,
                'occurred_at' => $sn->occurred_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
