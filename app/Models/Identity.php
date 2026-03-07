<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Identity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'person_id',
        'system_slug',
        'type',
        'value',
        'value_normalized',
        'meta_json',
        'is_team_member',
    ];

    protected $casts = [
        'meta_json'      => 'array',
        'is_team_member' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            $model->value_normalized = strtolower(trim($model->value));
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function conversationParticipants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }
}
