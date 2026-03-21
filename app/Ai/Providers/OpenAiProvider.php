<?php

namespace App\Ai\Providers;

use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiProviderInterface
{
    private const API_BASE = 'https://api.openai.com/v1';

    public function __construct(private readonly string $apiKey) {}

    public function testConnection(): void
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(10)
            ->get(self::API_BASE . '/models');

        if ($response->failed()) {
            throw new \RuntimeException('OpenAI API error: ' . ($response->json('error.message') ?? $response->status()));
        }
    }

    public function fetchModels(): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(10)
            ->get(self::API_BASE . '/models');

        if ($response->failed()) {
            return $this->fallbackModels();
        }

        $models = $response->json('data', []);
        $ids = array_column($models, 'id');

        // Filter to relevant chat models only
        $ids = array_values(array_filter($ids, fn($id) => str_starts_with($id, 'gpt-') || str_starts_with($id, 'o1') || str_starts_with($id, 'o3')));
        sort($ids);

        return $ids ?: $this->fallbackModels();
    }

    public function complete(string $model, array $messages, array $options = []): array
    {
        $msgs = $this->convertMessages($messages);

        $payload = [
            'model'      => $model,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'messages'   => $msgs,
        ];

        if (isset($options['tools'])) {
            $payload['tools'] = $this->convertToolsToOpenAi($options['tools']);
        }

        $response = Http::withHeaders($this->headers())
            ->timeout(120)
            ->post(self::API_BASE . '/chat/completions', $payload);

        if ($response->failed()) {
            throw new \RuntimeException('OpenAI API error: ' . ($response->json('error.message') ?? $response->status()));
        }

        $data    = $response->json();
        $message = $data['choices'][0]['message'] ?? [];

        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $tc) {
            $toolCalls[] = [
                'id'    => $tc['id'],
                'name'  => $tc['function']['name'],
                'input' => json_decode($tc['function']['arguments'] ?? '{}', true) ?? [],
            ];
        }

        return [
            'content'       => $message['content'] ?? '',
            'tool_calls'    => $toolCalls,
            'stop_reason'   => empty($toolCalls) ? 'end_turn' : 'tool_use',
            'input_tokens'  => $data['usage']['prompt_tokens'] ?? 0,
            'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
        ];
    }

    public function stream(string $model, array $messages, array $options = []): \Generator
    {
        $msgs = $this->convertMessages($messages);

        $payload = [
            'model'      => $model,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'stream'     => true,
            'messages'   => $msgs,
        ];

        if (isset($options['tools'])) {
            $payload['tools'] = $this->convertToolsToOpenAi($options['tools']);
        }

        $response = Http::withHeaders($this->headers())
            ->timeout(120)
            ->withOptions(['stream' => true])
            ->post(self::API_BASE . '/chat/completions', $payload);

        if ($response->failed()) {
            throw new \RuntimeException('OpenAI API error: ' . ($response->json('error.message') ?? $response->status()));
        }

        $inputTokens  = 0;
        $outputTokens = 0;

        // Track tool calls being streamed
        $toolCalls    = [];
        $currentTools = []; // indexed by tool call index

        $body   = $response->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(1024);
            $lines  = explode("\n", $buffer);
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);
                if (!str_starts_with($line, 'data: ')) continue;

                $raw = substr($line, 6);
                if ($raw === '[DONE]') continue;

                $json = json_decode($raw, true);
                if (!$json) continue;

                $delta = $json['choices'][0]['delta'] ?? [];

                // Text content
                if (isset($delta['content'])) {
                    yield $delta['content'];
                }

                // Tool call chunks
                if (isset($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $tcDelta) {
                        $idx = $tcDelta['index'];
                        if (isset($tcDelta['id'])) {
                            $currentTools[$idx] = [
                                'id'   => $tcDelta['id'],
                                'name' => $tcDelta['function']['name'] ?? '',
                                'args' => '',
                            ];
                        }
                        if (isset($tcDelta['function']['arguments'])) {
                            $currentTools[$idx]['args'] .= $tcDelta['function']['arguments'];
                        }
                    }
                }

                if (isset($json['usage'])) {
                    $inputTokens  = $json['usage']['prompt_tokens'] ?? 0;
                    $outputTokens = $json['usage']['completion_tokens'] ?? 0;
                }
            }
        }

        // Convert accumulated tool calls
        foreach ($currentTools as $tc) {
            $toolCalls[] = [
                'id'    => $tc['id'],
                'name'  => $tc['name'],
                'input' => json_decode($tc['args'] ?? '{}', true) ?? [],
            ];
        }

        yield [
            'usage'       => ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens],
            'tool_calls'  => $toolCalls,
            'stop_reason' => empty($toolCalls) ? 'end_turn' : 'tool_use',
        ];
    }

    /**
     * Convert messages with structured content blocks to OpenAI format.
     */
    private function convertMessages(array $messages): array
    {
        $result = [];
        foreach ($messages as $msg) {
            if (is_string($msg['content'])) {
                $result[] = $msg;
                continue;
            }

            // Structured content blocks (tool_use / tool_result)
            if (is_array($msg['content'])) {
                if ($msg['role'] === 'assistant') {
                    // Convert tool_use blocks to OpenAI format
                    $text = '';
                    $oaiToolCalls = [];
                    foreach ($msg['content'] as $block) {
                        if (($block['type'] ?? '') === 'text') {
                            $text .= $block['text'];
                        } elseif (($block['type'] ?? '') === 'tool_use') {
                            $oaiToolCalls[] = [
                                'id'       => $block['id'],
                                'type'     => 'function',
                                'function' => [
                                    'name'      => $block['name'],
                                    'arguments' => json_encode($block['input'] ?? new \stdClass()),
                                ],
                            ];
                        }
                    }
                    $oaiMsg = ['role' => 'assistant'];
                    if ($text) $oaiMsg['content'] = $text;
                    if (!empty($oaiToolCalls)) $oaiMsg['tool_calls'] = $oaiToolCalls;
                    $result[] = $oaiMsg;
                } elseif ($msg['role'] === 'user') {
                    // Convert tool_result blocks to OpenAI tool messages
                    foreach ($msg['content'] as $block) {
                        if (($block['type'] ?? '') === 'tool_result') {
                            $result[] = [
                                'role'         => 'tool',
                                'tool_call_id' => $block['tool_use_id'],
                                'content'      => $block['content'] ?? '',
                            ];
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Convert Claude-format tools to OpenAI format.
     */
    private function convertToolsToOpenAi(array $tools): array
    {
        return array_map(fn($t) => [
            'type'     => 'function',
            'function' => [
                'name'        => $t['name'],
                'description' => $t['description'],
                'parameters'  => $t['input_schema'] ?? ['type' => 'object', 'properties' => new \stdClass()],
            ],
        ], $tools);
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ];
    }

    private function fallbackModels(): array
    {
        return [
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
            'o1',
            'o1-mini',
            'o3-mini',
        ];
    }
}
