<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IngestBatch extends Model
{
    protected $fillable = [
        'batch_uuid',
        'source_type',
        'source_slug',
        'item_count',
        'status',
        'processed_count',
        'skipped_count',
        'failed_count',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(IngestItem::class);
    }
}
