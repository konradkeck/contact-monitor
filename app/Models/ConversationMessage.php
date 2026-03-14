<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversationMessage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'external_id',
        'identity_id',
        'author_name',
        'direction',
        'body_text',
        'body_html',
        'attachments_json',
        'thread_key',
        'thread_count',
        'source_url',
        'is_system_message',
        'edited_at',
        'meta_json',
        'occurred_at',
        'is_archived',
        'archived_at',
        'sync_protected',
    ];

    protected $casts = [
        'attachments_json' => 'array',
        'meta_json' => 'array',
        'is_system_message' => 'boolean',
        'occurred_at' => 'datetime',
        'edited_at' => 'datetime',
        'archived_at' => 'datetime',
        'is_archived' => 'boolean',
        'sync_protected' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function identity(): BelongsTo
    {
        return $this->belongsTo(Identity::class);
    }

    public function attachments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MessageAttachment::class, 'conversation_message_id');
    }
}
