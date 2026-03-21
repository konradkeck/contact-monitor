<?php

namespace App\Ai\Providers;

use App\Models\AiCredential;

class AiProviderFactory
{
    public static function make(AiCredential $credential): AiProviderInterface
    {
        $apiKey = $credential->getDecryptedApiKey();

        return match ($credential->provider) {
            'claude'  => new ClaudeProvider($apiKey),
            'openai'  => new OpenAiProvider($apiKey),
            'gemini'  => new GeminiProvider($apiKey),
            'grok'    => new GrokProvider($apiKey),
            default   => throw new \InvalidArgumentException("Unknown AI provider: {$credential->provider}"),
        };
    }

    public static function providers(): array
    {
        return [
            'claude' => 'Anthropic Claude',
            'openai' => 'OpenAI',
            'gemini' => 'Google Gemini',
            'grok'   => 'xAI Grok',
        ];
    }
}
