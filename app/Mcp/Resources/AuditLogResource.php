<?php

namespace App\Mcp\Resources;

use App\Models\AuditLog;

class AuditLogResource
{
    public static function descriptor(): array
    {
        return [
            'uri'         => 'audit_log://list',
            'name'        => 'Audit Log',
            'description' => 'Audit log entries. Params: entity_type, entity_id, action, from, to, page.',
            'mimeType'    => 'application/json',
        ];
    }

    public static function read(array $params): array
    {
        $page       = max(1, (int) ($params['page'] ?? 1));
        $entityType = $params['entity_type'] ?? null;
        $entityId   = $params['entity_id'] ?? null;
        $action     = $params['action'] ?? null;
        $from       = $params['from'] ?? null;
        $to         = $params['to'] ?? null;

        $query = AuditLog::with('user:id,name')->orderByDesc('created_at');

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }
        if ($entityId) {
            $query->where('entity_id', $entityId);
        }
        if ($action) {
            $query->where('action', $action);
        }
        if (!empty($from)) {
            $query->whereDate('created_at', '>=', $from);
        }
        if (!empty($to)) {
            $query->whereDate('created_at', '<=', $to);
        }

        $paginator = $query->paginate(25, ['*'], 'page', $page);

        return [
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'items'        => $paginator->map(fn($log) => [
                'id'          => $log->id,
                'user_name'   => $log->user?->name,
                'entity_type' => $log->entity_type,
                'entity_id'   => $log->entity_id,
                'action'      => $log->action,
                'description' => $log->description,
                'created_at'  => $log->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
