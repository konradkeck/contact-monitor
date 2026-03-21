<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisEntity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'run_id',
        'step_run_id',
        'entity_type',
        'display_name',
        'data_json',
        'confidence',
        'sort_order',
    ];

    protected $casts = [
        'data_json'  => 'array',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
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
