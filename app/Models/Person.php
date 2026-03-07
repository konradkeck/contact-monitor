<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'is_our_org',
        'meta_json',
    ];

    protected $casts = [
        'meta_json'  => 'array',
        'is_our_org' => 'boolean',
    ];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function identities(): HasMany
    {
        return $this->hasMany(Identity::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_person')
            ->withPivot(['role', 'started_at', 'ended_at'])
            ->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->orderByDesc('occurred_at');
    }

    public function conversationParticipations(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function notes(): HasManyThrough
    {
        return $this->hasManyThrough(
            Note::class,
            NoteLink::class,
            'linkable_id',
            'id',
            'id',
            'note_id'
        )->where('note_links.linkable_type', self::class);
    }

    public function latestActivity(): HasOne
    {
        return $this->hasOne(Activity::class)->latestOfMany('occurred_at');
    }

    public function latestContact(): HasOne
    {
        return $this->hasOne(Activity::class)
            ->whereIn('type', ['ticket', 'conversation', 'followup'])
            ->latestOfMany('occurred_at');
    }

    public function gravatarUrl(int $size = 80): string
    {
        $email = $this->identities->where('type', 'email')->first()?->value ?? '';
        $hash  = md5(strtolower(trim($email)));
        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s={$size}";
    }

    /** Initials for fallback avatar. */
    public function initials(): string
    {
        $f = mb_substr($this->first_name, 0, 1);
        $l = mb_substr($this->last_name  ?? '', 0, 1);
        return strtoupper($f . $l);
    }
}
