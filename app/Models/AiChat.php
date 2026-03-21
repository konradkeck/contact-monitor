<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AiChat extends Model
{
    protected $fillable = [
        'user_id', 'project_id', 'title', 'title_is_manual',
        'is_archived', 'is_shared',
        'source_chat_id', 'source_message_id',
        'last_message_at',
    ];

    protected $casts = [
        'title_is_manual' => 'boolean',
        'is_archived'     => 'boolean',
        'is_shared'       => 'boolean',
        'last_message_at' => 'datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(AiProject::class, 'project_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'chat_id')->orderBy('created_at');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(AiChatParticipant::class, 'chat_id');
    }

    public function participantUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ai_chat_participants', 'chat_id', 'user_id')
            ->withPivot('added_at', 'added_by')
            ->withTimestamps();
    }

    public function sourceChat(): BelongsTo
    {
        return $this->belongsTo(AiChat::class, 'source_chat_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(AiChat::class, 'source_chat_id');
    }

    public function projectPins(): HasMany
    {
        return $this->hasMany(AiChatProjectPin::class, 'chat_id');
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    public function isParticipant(int $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    public function canAccess(int $userId): bool
    {
        return $this->isOwnedBy($userId) || $this->isParticipant($userId);
    }

    public function scopeAccessibleBy($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhereHas('participants', fn ($p) => $p->where('user_id', $userId));
        });
    }

    public function toSidebarArray(): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title ?? 'New Conversation',
            'is_shared'       => $this->is_shared,
            'is_archived'     => $this->is_archived,
            'project_id'      => $this->project_id,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'source_chat_id'  => $this->source_chat_id,
        ];
    }
}
