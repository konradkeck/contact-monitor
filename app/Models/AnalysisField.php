<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisField extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'run_id',
        'step_run_id',
        'field_group',
        'field_key',
        'field_value',
        'field_type',
        'confidence',
        'is_inferred',
        'sort_order',
    ];

    protected $casts = [
        'is_inferred' => 'boolean',
        'sort_order'  => 'integer',
        'created_at'  => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class, 'run_id');
    }

    public function stepRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisStepRun::class, 'step_run_id');
    }
}
