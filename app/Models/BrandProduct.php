<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BrandProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'variant',
        'slug',
        'meta_json',
    ];

    protected $casts = [
        'meta_json' => 'array',
    ];

    public function companyStatuses(): HasMany
    {
        return $this->hasMany(CompanyBrandStatus::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_brand_statuses')
            ->using(CompanyBrandStatus::class)
            ->withPivot(['stage', 'evaluation_score', 'evaluation_notes', 'last_evaluated_at'])
            ->withTimestamps();
    }
}
