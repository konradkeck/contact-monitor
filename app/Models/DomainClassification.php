<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DomainClassification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'domain',
        'type',
        'source',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function scopeFreeEmail(Builder $query): Builder
    {
        return $query->where('type', 'free_email');
    }

    public function scopeDisposable(Builder $query): Builder
    {
        return $query->where('type', 'disposable');
    }

    public static function isFreeEmail(string $domain): bool
    {
        return static::where('domain', strtolower($domain))->where('type', 'free_email')->exists();
    }

    public static function isDisposable(string $domain): bool
    {
        return static::where('domain', strtolower($domain))->where('type', 'disposable')->exists();
    }
}
