<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyAlias extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'alias',
        'alias_normalized',
        'type',
        'is_primary',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            $model->alias_normalized = strtolower(trim($model->alias));
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
