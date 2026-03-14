<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'primary_person_id',
        'channel_type',
        'system_type',
        'system_slug',
        'subject',
        'external_thread_id',
        'message_count',
        'started_at',
        'last_message_at',
        'is_archived',
        'archived_at',
        'sync_protected',
        'meta_json',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_message_at' => 'datetime',
        'archived_at' => 'datetime',
        'message_count' => 'integer',
        'is_archived' => 'boolean',
        'sync_protected' => 'boolean',
        'meta_json' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function primaryPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'primary_person_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('occurred_at');
    }
}
