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
        'is_bot',
    ];

    protected $casts = [
        'meta_json' => 'array',
        'is_team_member' => 'boolean',
        'is_bot' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            $model->value_normalized = strtolower(trim($model->value));
        });
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function conversationParticipants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }
}
