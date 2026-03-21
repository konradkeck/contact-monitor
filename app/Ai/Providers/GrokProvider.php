<?php

namespace App\Ai\Providers;

use Illuminate\Support\Facades\Http;

class GrokProvider implements AiProviderInterface
{
    private const API_BASE = 'https://api.x.ai/v1';

    public function __construct(private readonly string $apiKey) {}

    public function testConnection(): void
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(10)
            ->get(self::API_BASE . '/models');

        if ($response->failed()) {
            throw new \RuntimeException('Grok API error: ' . ($response->json('error.message') ?? $response->status()));
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
        $ids = array_values(array_filter($ids, fn($id) => str_starts_with($id, 'grok-')));
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
            $payload['tools'] = $this->convertTools($options['tools']);
        }

        $response = Http::withHeaders($this->headers())
            ->timeout(120)
            ->post(self::API_BASE . '/chat/completions', $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Grok API error: ' . ($response->json('error.message') ?? $response->status()));
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
            $payload['tools'] = $this->convertTools($options['tools']);
        }

        $response = Http::withHeaders($this->headers())
            ->timeout(120)
            ->withOptions(['stream' => true])
            ->post(self::API_BASE . '/chat/completions', $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Grok API error: ' . ($response->json('error.message') ?? $response->status()));
        }

        $inputTokens  = 0;
        $outputTokens = 0;
        $currentTools = [];

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

                if (isset($delta['content'])) {
                    yield $delta['content'];
                }

                // Tool call chunks
                if (isset($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $tcDelta) {
                        $idx = $tcDelta['index'];
                        if (isset($tcDelta['id'])) {
                            $currentTools[$idx] = ['id' => $tcDelta['id'], 'name' => $tcDelta['function']['name'] ?? '', 'args' => ''];
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

        $toolCalls = [];
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
     * Convert messages with structured content blocks to OpenAI/Grok format.
     */
    private function convertMessages(array $messages): array
    {
        $result = [];
        foreach ($messages as $msg) {
            if (is_string($msg['content'])) {
                $result[] = $msg;
                continue;
            }

            if (is_array($msg['content'])) {
                if ($msg['role'] === 'assistant') {
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

    private function convertTools(array $tools): array
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
            'grok-3',
            'grok-3-mini',
            'grok-2',
            'grok-2-mini',
        ];
    }
}
