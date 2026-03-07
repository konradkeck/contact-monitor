<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngestItem extends Model
{
    protected $fillable = [
        'ingest_batch_id',
        'idempotency_key',
        'item_type',
        'action',
        'system_type',
        'system_slug',
        'external_id',
        'payload_hash',
        'payload',
        'status',
        'entity_type',
        'entity_id',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'payload'      => 'array',
        'processed_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(IngestBatch::class, 'ingest_batch_id');
    }
}
