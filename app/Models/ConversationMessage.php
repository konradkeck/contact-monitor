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

    /**
     * Chat avatar URL for Slack/Discord messages, or null.
     */
    public function chatAvatarUrl(): ?string
    {
        if (! $this->identity) {
            return null;
        }

        if (in_array($this->identity->type, ['discord_user', 'discord_id']) && ! empty($this->identity->meta_json['avatar'])) {
            return 'https://cdn.discordapp.com/avatars/'.$this->identity->value_normalized.'/'.$this->identity->meta_json['avatar'].'.webp?size=56';
        }

        if ($this->identity->type === 'slack_user' && ! empty($this->identity->meta_json['avatar'])) {
            return $this->identity->meta_json['avatar'];
        }

        return null;
    }

    /**
     * Whether this message is from a team member.
     */
    public function isTeamMessage(): bool
    {
        return $this->direction === 'internal' || ($this->identity?->is_team_member ?? false);
    }

    /**
     * Gravatar hash for email conversations, or null.
     */
    public function gravatarHash(): ?string
    {
        $email = $this->identity?->value;
        if (! $email) {
            return null;
        }

        return md5(strtolower(trim($email)));
    }

    /**
     * All attachments (from relation or JSON fallback).
     */
    public function allAttachments(): \Illuminate\Support\Collection
    {
        return $this->attachments->isNotEmpty()
            ? $this->attachments
            : collect($this->attachments_json ?? []);
    }
}
