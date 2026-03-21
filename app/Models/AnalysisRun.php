<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalysisRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'user_id',
        'status',
        'base_context_json',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'base_context_json' => 'array',
        'started_at'        => 'datetime',
        'completed_at'      => 'datetime',
        'created_at'        => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stepRuns(): HasMany
    {
        return $this->hasMany(AnalysisStepRun::class, 'run_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(AnalysisField::class, 'run_id');
    }

    public function entities(): HasMany
    {
        return $this->hasMany(AnalysisEntity::class, 'run_id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId)->orderByDesc('created_at');
    }
}
