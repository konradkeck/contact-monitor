<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'chat_id',
        'role',
        'content',
        'tool_calls_json',
        'meta_json',
    ];

    protected $casts = [
        'tool_calls_json' => 'array',
        'meta_json'       => 'array',
        'created_at'      => 'datetime',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(AiChat::class, 'chat_id');
    }
}
