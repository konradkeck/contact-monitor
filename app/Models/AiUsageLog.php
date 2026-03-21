<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'action_type',
        'credential_id',
        'model_name',
        'entity_type',
        'entity_id',
        'input_tokens',
        'output_tokens',
        'cost_input_usd',
        'cost_output_usd',
        'prompt_excerpt',
        'meta_json',
    ];

    protected $casts = [
        'input_tokens'    => 'integer',
        'output_tokens'   => 'integer',
        'cost_input_usd'  => 'float',
        'cost_output_usd' => 'float',
        'meta_json'       => 'array',
        'created_at'      => 'datetime',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(AiCredential::class, 'credential_id');
    }

    public function totalCost(): float
    {
        return $this->cost_input_usd + $this->cost_output_usd;
    }

    public static function record(
        string $actionType,
        ?int $credentialId,
        string $modelName,
        int $inputTokens,
        int $outputTokens,
        float $costInputUsd,
        float $costOutputUsd,
        ?string $promptExcerpt = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $meta = []
    ): self {
        return static::create([
            'action_type'     => $actionType,
            'credential_id'   => $credentialId,
            'model_name'      => $modelName,
            'input_tokens'    => $inputTokens,
            'output_tokens'   => $outputTokens,
            'cost_input_usd'  => $costInputUsd,
            'cost_output_usd' => $costOutputUsd,
            'prompt_excerpt'  => $promptExcerpt ? mb_substr($promptExcerpt, 0, 200) : null,
            'entity_type'     => $entityType,
            'entity_id'       => $entityId,
            'meta_json'       => $meta ?: null,
        ]);
    }
}
