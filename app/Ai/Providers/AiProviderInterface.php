<?php

namespace App\Ai\Providers;

interface AiProviderInterface
{
    /**
     * Test that the credential is valid. Throws on failure.
     */
    public function testConnection(): void;

    /**
     * Return list of available model IDs for this provider.
     *
     * @return string[]
     */
    public function fetchModels(): array;

    /**
     * Send a non-streaming completion.
     *
     * @param  array  $messages   [['role' => 'user'|'assistant'|'system', 'content' => '...']]
     * @param  array  $options    provider-specific options (temperature, max_tokens, etc.)
     * @return array{content: string, input_tokens: int, output_tokens: int}
     */
    public function complete(string $model, array $messages, array $options = []): array;

    /**
     * Send a streaming completion. Yields string chunks as they arrive.
     * Also yields a final ['usage' => ['input_tokens' => ..., 'output_tokens' => ...]] item.
     *
     * @param  array  $messages
     * @param  array  $options
     * @return \Generator<string|array>
     */
    public function stream(string $model, array $messages, array $options = []): \Generator;
}
