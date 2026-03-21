<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalysisStepRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'run_id',
        'step_id',
        'step_key',
        'status',
        'prompt_template_used',
        'rendered_prompt',
        'raw_response',
        'parsed_response',
        'error_message',
        'model_name',
        'input_tokens',
        'output_tokens',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'parsed_response' => 'array',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
        'created_at'      => 'datetime',
        'input_tokens'    => 'integer',
        'output_tokens'   => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class, 'run_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(AnalysisStep::class, 'step_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(AnalysisField::class, 'step_run_id');
    }

    public function entities(): HasMany
    {
        return $this->hasMany(AnalysisEntity::class, 'step_run_id');
    }
}
