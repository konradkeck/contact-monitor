<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'tool_name',
        'entity_type',
        'entity_id',
        'input_json',
        'output_json',
        'context',
        'ip_address',
    ];

    protected $casts = [
        'input_json'  => 'array',
        'output_json' => 'array',
        'created_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected $table = 'mcp_logs';

    public static function record(string $tool, array $input, mixed $output, string $context = 'unknown', ?int $entityId = null, ?string $entityType = null): self
    {
        return static::create([
            'tool_name'   => $tool,
            'input_json'  => $input,
            'output_json' => is_array($output) ? $output : ['result' => $output],
            'context'     => $context,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'ip_address'  => request()->ip(),
        ]);
    }
}
