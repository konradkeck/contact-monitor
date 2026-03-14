<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyBrandStatus extends Pivot
{
    use SoftDeletes;

    protected $table = 'company_brand_statuses';

    public $incrementing = true;

    protected $fillable = [
        'company_id',
        'brand_product_id',
        'stage',
        'evaluation_score',
        'evaluation_notes',
        'last_evaluated_at',
    ];

    protected $casts = [
        'last_evaluated_at' => 'datetime',
        'evaluation_score' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function brandProduct(): BelongsTo
    {
        return $this->belongsTo(BrandProduct::class);
    }
}
