<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiCredential extends Model
{
    protected $fillable = [
        'name',
        'provider',
        'api_key',
        'extra_config',
        'is_active',
    ];

    protected $casts = [
        'extra_config' => 'array',
        'is_active'    => 'boolean',
    ];

    protected $hidden = ['api_key'];

    public function getDecryptedApiKey(): string
    {
        return decrypt($this->api_key);
    }

    public function setApiKeyAttribute(string $value): void
    {
        $this->attributes['api_key'] = encrypt($value);
    }

    public function modelConfigs(): HasMany
    {
        return $this->hasMany(AiModelConfig::class, 'credential_id');
    }

    public function providerLabel(): string
    {
        return match ($this->provider) {
            'claude'  => 'Anthropic (Claude)',
            'openai'  => 'OpenAI',
            'gemini'  => 'Google (Gemini)',
            'grok'    => 'xAI (Grok)',
            default   => ucfirst($this->provider),
        };
    }
}
