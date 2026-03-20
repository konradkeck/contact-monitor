<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmartNote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'smart_note_filter_id',
        'source_type',
        'source_external_id',
        'content',
        'sender_name',
        'sender_value',
        'occurred_at',
        'as_internal_note',
        'status',
        'segments_json',
    ];

    protected $casts = [
        'as_internal_note' => 'boolean',
        'occurred_at'      => 'datetime',
        'segments_json'    => 'array',
    ];

    public function filter(): BelongsTo
    {
        return $this->belongsTo(SmartNoteFilter::class, 'smart_note_filter_id');
    }

    public function scopeUnrecognized(Builder $query): Builder
    {
        return $query->where('status', 'unrecognized');
    }

    public function scopeRecognized(Builder $query): Builder
    {
        return $query->where('status', 'recognized');
    }

    public function sourceLabel(): string
    {
        return match ($this->source_type) {
            'email'   => 'Email',
            'discord' => 'Discord',
            'slack'   => 'Slack',
            'ticket'  => 'Ticket',
            default   => ucfirst($this->source_type),
        };
    }
}
