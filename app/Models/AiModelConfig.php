<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModelConfig extends Model
{
    protected $fillable = [
        'action_type',
        'credential_id',
        'model_name',
        'helper_credential_id',
        'helper_model_name',
        'extra_config',
    ];

    protected $casts = [
        'extra_config' => 'array',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(AiCredential::class, 'credential_id');
    }

    public function helperCredential(): BelongsTo
    {
        return $this->belongsTo(AiCredential::class, 'helper_credential_id');
    }

    public static function forAction(string $actionType): ?self
    {
        return static::with(['credential', 'helperCredential'])
            ->where('action_type', $actionType)
            ->first();
    }

    public static function actionLabels(): array
    {
        return [
            'analyze'               => 'Analyse Chat',
            'company_analysis'      => 'Company Analysis',
            'conv_summary_message'  => 'Conversation Summary (message)',
            'conv_summary_company'  => 'Conversation Summary (company)',
            'conv_summary_person'   => 'Conversation Summary (person)',
            'notes_recognition'     => 'Notes Recognition',
        ];
    }
}
