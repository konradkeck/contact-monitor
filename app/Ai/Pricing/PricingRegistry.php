<?php

namespace App\Ai\Pricing;

use App\Models\SystemSetting;

class PricingRegistry
{
    /**
     * Default prices in USD per 1M tokens.
     * Format: 'model-id' => ['input' => float, 'output' => float]
     */
    private static array $defaults = [
        // Anthropic Claude
        'claude-opus-4-6'           => ['input' => 15.00,  'output' => 75.00],
        'claude-sonnet-4-6'         => ['input' => 3.00,   'output' => 15.00],
        'claude-haiku-4-5-20251001' => ['input' => 0.80,   'output' => 4.00],
        'claude-3-5-sonnet-20241022' => ['input' => 3.00,  'output' => 15.00],
        'claude-3-5-haiku-20241022'  => ['input' => 0.80,  'output' => 4.00],
        'claude-3-opus-20240229'     => ['input' => 15.00, 'output' => 75.00],

        // OpenAI
        'gpt-4o'                    => ['input' => 2.50,   'output' => 10.00],
        'gpt-4o-mini'               => ['input' => 0.15,   'output' => 0.60],
        'gpt-4-turbo'               => ['input' => 10.00,  'output' => 30.00],
        'gpt-4'                     => ['input' => 30.00,  'output' => 60.00],
        'gpt-3.5-turbo'             => ['input' => 0.50,   'output' => 1.50],
        'o1'                        => ['input' => 15.00,  'output' => 60.00],
        'o1-mini'                   => ['input' => 3.00,   'output' => 12.00],
        'o3-mini'                   => ['input' => 1.10,   'output' => 4.40],

        // Google Gemini
        'gemini-2.5-pro'            => ['input' => 1.25,   'output' => 10.00],
        'gemini-2.5-flash'          => ['input' => 0.15,   'output' => 0.60],
        'gemini-2.0-flash'          => ['input' => 0.10,   'output' => 0.40],
        'gemini-2.0-flash-lite'     => ['input' => 0.075,  'output' => 0.30],
        'gemini-1.5-pro'            => ['input' => 1.25,   'output' => 5.00],
        'gemini-1.5-flash'          => ['input' => 0.075,  'output' => 0.30],
        'gemini-1.5-flash-8b'       => ['input' => 0.0375, 'output' => 0.15],
        'gemini-pro-latest'         => ['input' => 1.25,   'output' => 10.00],
        'gemini-flash-latest'       => ['input' => 0.15,   'output' => 0.60],

        // xAI Grok
        'grok-3'                    => ['input' => 3.00,   'output' => 15.00],
        'grok-3-mini'               => ['input' => 0.30,   'output' => 0.50],
        'grok-2'                    => ['input' => 2.00,   'output' => 10.00],
        'grok-2-mini'               => ['input' => 0.20,   'output' => 0.40],
    ];

    private static ?array $overrides = null;

    public static function getPrice(string $model): array
    {
        $overrides = self::loadOverrides();

        if (isset($overrides[$model])) {
            return $overrides[$model];
        }

        return self::$defaults[$model] ?? ['input' => 0.0, 'output' => 0.0];
    }

    public static function getInputPrice(string $model): float
    {
        return self::getPrice($model)['input'];
    }

    public static function getOutputPrice(string $model): float
    {
        return self::getPrice($model)['output'];
    }

    /**
     * Calculate cost in USD for given token counts.
     */
    public static function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $price = self::getPrice($model);

        return ($inputTokens / 1_000_000 * $price['input'])
             + ($outputTokens / 1_000_000 * $price['output']);
    }

    public static function allDefaults(): array
    {
        return self::$defaults;
    }

    public static function setOverride(string $model, float $inputPer1M, float $outputPer1M): void
    {
        $overrides = self::loadOverrides();
        $overrides[$model] = ['input' => $inputPer1M, 'output' => $outputPer1M];
        SystemSetting::set('ai_pricing_overrides', $overrides);
        self::$overrides = $overrides;
    }

    public static function removeOverride(string $model): void
    {
        $overrides = self::loadOverrides();
        unset($overrides[$model]);
        SystemSetting::set('ai_pricing_overrides', $overrides);
        self::$overrides = $overrides;
    }

    public static function clearCache(): void
    {
        self::$overrides = null;
    }

    private static function loadOverrides(): array
    {
        if (self::$overrides === null) {
            self::$overrides = SystemSetting::get('ai_pricing_overrides', []) ?? [];
        }
        return self::$overrides;
    }
}
