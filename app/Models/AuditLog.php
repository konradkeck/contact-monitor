<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    // No soft deletes — audit logs are immutable
    // Keep both timestamps but never write updated_at to the table
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'action',
        'entity_type',
        'entity_id',
        'message',
        'meta_json',
    ];

    protected $casts = [
        'meta_json' => 'array',
    ];

    public static function record(
        string $action,
        Model $entity,
        string $message,
        array $meta = [],
        ?int $actorUserId = null
    ): self {
        return self::create([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'entity_type' => get_class($entity),
            'entity_id' => $entity->getKey(),
            'message' => $message,
            'meta_json' => $meta ?: null,
        ]);
    }
}
