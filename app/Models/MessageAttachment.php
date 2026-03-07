<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $fillable = [
        'conversation_message_id',
        'external_id',
        'filename',
        'content_type',
        'size',
        'source_url',
        'storage_path',
        'meta_json',
    ];

    protected $casts = [
        'meta_json' => 'array',
        'size'      => 'integer',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }
}
