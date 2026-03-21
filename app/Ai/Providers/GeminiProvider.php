<?php

namespace App\Ai\Providers;

use Illuminate\Support\Facades\Http;

class GeminiProvider implements AiProviderInterface
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(private readonly string $apiKey) {}

    public function testConnection(): void
    {
        $response = Http::timeout(10)
            ->get(self::API_BASE . '/models', ['key' => $this->apiKey]);

        if ($response->failed()) {
            throw new \RuntimeException('Gemini API error: ' . ($response->json('error.message') ?? $response->status()));
        }
    }

    public function fetchModels(): array
    {
        $response = Http::timeout(10)
            ->get(self::API_BASE . '/models', ['key' => $this->apiKey]);

        if ($response->failed()) {
            return $this->fallbackModels();
        }

        $models = $response->json('models', []);
        $ids = array_map(fn($m) => str_replace('models/', '', $m['name'] ?? ''), $models);
        $ids = array_values(array_filter($ids, fn($id) => str_starts_with($id, 'gemini-')));
        sort($ids);

        return $ids ?: $this->fallbackModels();
    }

    public function complete(string $model, array $messages, array $options = []): array
    {
        [$system, $contents] = $this->convertMessages($messages);

        $payload = [
            'contents'         => $contents,
            'generationConfig' => ['maxOutputTokens' => $options['max_tokens'] ?? 4096],
        ];

        if ($system) {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        if (isset($options['tools'])) {
            $payload['tools'] = $this->convertToolsToGemini($options['tools']);
        }

        $response = Http::timeout(120)
            ->post(self::API_BASE . "/models/{$model}:generateContent?key={$this->apiKey}", $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Gemini API error: ' . ($response->json('error.message') ?? $response->status()));
        }

        $data  = $response->json();
        $parts = $data['candidates'][0]['content']['parts'] ?? [];

        $textParts = [];
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $textParts[] = $part['text'];
            } elseif (isset($part['functionCall'])) {
                $id = $part['functionCall']['id'] ?? ('tc_' . uniqid());
                $toolCalls[] = [
                    'id'           => $id,
                    'name'         => $part['functionCall']['name'],
                    'input'        => $part['functionCall']['args'] ?? [],
                    '_gemini_part' => $part, // preserve raw part including thoughtSignature
                ];
            }
        }

        return [
            'content'        => implode('', $textParts),
            'content_blocks' => $parts,
            'tool_calls'     => $toolCalls,
            'stop_reason'    => empty($toolCalls) ? 'end_turn' : 'tool_use',
            'input_tokens'   => $data['usageMetadata']['promptTokenCount'] ?? 0,
            'output_tokens'  => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
        ];
    }

    public function stream(string $model, array $messages, array $options = []): \Generator
    {
        [$system, $contents] = $this->convertMessages($messages);

        $payload = [
            'contents'         => $contents,
            'generationConfig' => ['maxOutputTokens' => $options['max_tokens'] ?? 4096],
        ];

        if ($system) {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        if (isset($options['tools'])) {
            $payload['tools'] = $this->convertToolsToGemini($options['tools']);
        }

        $response = Http::timeout(120)
            ->withOptions(['stream' => true])
            ->post(self::API_BASE . "/models/{$model}:streamGenerateContent?alt=sse&key={$this->apiKey}", $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Gemini API error: ' . ($response->json('error.message') ?? $response->status()));
        }

        $inputTokens  = 0;
        $outputTokens = 0;
        $toolCalls    = [];

        $body   = $response->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(1024);
            $lines  = explode("\n", $buffer);
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);
                if (!str_starts_with($line, 'data: ')) continue;

                $json = json_decode(substr($line, 6), true);
                if (!$json) continue;

                $parts = $json['candidates'][0]['content']['parts'] ?? [];
                foreach ($parts as $part) {
                    if (isset($part['text'])) {
                        yield $part['text'];
                    } elseif (isset($part['functionCall'])) {
                        $id = $part['functionCall']['id'] ?? ('tc_' . uniqid());
                        $toolCalls[] = [
                            'id'           => $id,
                            'name'         => $part['functionCall']['name'],
                            'input'        => $part['functionCall']['args'] ?? [],
                            '_gemini_part' => $part, // preserve raw part including thoughtSignature
                        ];
                    }
                }

                if (isset($json['usageMetadata'])) {
                    $inputTokens  = $json['usageMetadata']['promptTokenCount'] ?? 0;
                    $outputTokens = $json['usageMetadata']['candidatesTokenCount'] ?? 0;
                }
            }
        }

        yield [
            'usage'       => ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens],
            'tool_calls'  => $toolCalls,
            'stop_reason' => empty($toolCalls) ? 'end_turn' : 'tool_use',
        ];
    }

    private function convertMessages(array $messages): array
    {
        $system   = null;
        $contents = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system = $msg['content'];
                continue;
            }

            $role = $msg['role'] === 'assistant' ? 'model' : 'user';

            // Handle structured content (tool_use / tool_result blocks)
            if (is_array($msg['content'])) {
                $parts = [];
                foreach ($msg['content'] as $block) {
                    if (($block['type'] ?? '') === 'text') {
                        $parts[] = ['text' => $block['text']];
                    } elseif (($block['type'] ?? '') === 'tool_use') {
                        // Use raw Gemini part if available (preserves thoughtSignature)
                        if (isset($block['_gemini_part'])) {
                            $part = $block['_gemini_part'];
                            // Ensure args is always an object, not array (PHP json_decode converts {} to [])
                            if (isset($part['functionCall']['args']) && $part['functionCall']['args'] === []) {
                                $part['functionCall']['args'] = new \stdClass();
                            }
                            $parts[] = $part;
                        } else {
                            $args = $block['input'] ?? [];
                            $parts[] = ['functionCall' => [
                                'name' => $block['name'],
                                'args' => empty($args) ? new \stdClass() : $args,
                            ]];
                        }
                    } elseif (($block['type'] ?? '') === 'tool_result') {
                        $responseData = json_decode($block['content'] ?? '{}', true) ?? [];
                        $parts[] = ['functionResponse' => [
                            'name'     => $block['_tool_name'] ?? 'unknown',
                            'response' => empty($responseData) ? new \stdClass() : $responseData,
                        ]];
                    }
                }
                if (!empty($parts)) {
                    $contents[] = ['role' => $role, 'parts' => $parts];
                }
                continue;
            }

            $contents[] = ['role' => $role, 'parts' => [['text' => $msg['content']]]];
        }

        return [$system, $contents];
    }

    /**
     * Convert Claude-format tool definitions to Gemini format.
     */
    private function convertToolsToGemini(array $tools): array
    {
        $declarations = [];
        foreach ($tools as $tool) {
            $declaration = [
                'name'        => $tool['name'],
                'description' => $tool['description'],
            ];
            if (!empty($tool['input_schema'])) {
                $declaration['parameters'] = $this->cleanSchema($tool['input_schema']);
            }
            $declarations[] = $declaration;
        }

        return [['function_declarations' => $declarations]];
    }

    /**
     * Clean up JSON schema for Gemini compatibility.
     */
    private function cleanSchema(array $schema): array
    {
        if (($schema['type'] ?? '') === 'object' && empty($schema['properties'])) {
            $schema['properties'] = ['_unused' => ['type' => 'string', 'description' => 'Unused parameter']];
        }
        return $schema;
    }

    private function fallbackModels(): array
    {
        return [
            'gemini-2.0-flash',
            'gemini-2.0-flash-lite',
            'gemini-1.5-pro',
            'gemini-1.5-flash',
        ];
    }
}
