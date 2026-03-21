<?php

namespace App\Ai\Providers;

use Illuminate\Support\Facades\Http;

class ClaudeProvider implements AiProviderInterface
{
    private const API_BASE = 'https://api.anthropic.com/v1';
    private const API_VERSION = '2023-06-01';

    public function __construct(private readonly string $apiKey) {}

    public function testConnection(): void
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(10)
            ->get(self::API_BASE . '/models');

        if ($response->failed()) {
            throw new \RuntimeException('Claude API error: ' . ($response->json('error.message') ?? $response->status()));
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
        return array_column($models, 'id');
    }

    public function complete(string $model, array $messages, array $options = []): array
    {
        [$system, $msgs] = $this->splitSystemMessage($messages);

        $payload = [
            'model'      => $model,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'messages'   => $msgs,
        ];

        if (isset($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        if ($system) {
            $payload['system'] = $system;
        }

        $response = Http::withHeaders($this->headers())
            ->timeout(120)
            ->post(self::API_BASE . '/messages', $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Claude API error: ' . ($response->json('error.message') ?? $response->status()));
        }

        $data = $response->json();

        // Parse content blocks — may contain text and tool_use
        $textParts = [];
        $toolCalls = [];

        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $textParts[] = $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = [
                    'id'    => $block['id'],
                    'name'  => $block['name'],
                    'input' => $block['input'] ?? [],
                ];
            }
        }

        return [
            'content'       => implode('', $textParts),
            'content_blocks' => $data['content'] ?? [],
            'tool_calls'    => $toolCalls,
            'stop_reason'   => $data['stop_reason'] ?? 'end_turn',
            'input_tokens'  => $data['usage']['input_tokens'] ?? 0,
            'output_tokens' => $data['usage']['output_tokens'] ?? 0,
        ];
    }

    public function stream(string $model, array $messages, array $options = []): \Generator
    {
        [$system, $msgs] = $this->splitSystemMessage($messages);

        $payload = [
            'model'      => $model,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'stream'     => true,
            'messages'   => $msgs,
        ];

        if (isset($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        if ($system) {
            $payload['system'] = $system;
        }

        $response = Http::withHeaders($this->headers())
            ->timeout(120)
            ->withOptions(['stream' => true])
            ->post(self::API_BASE . '/messages', $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Claude API error: ' . ($response->json('error.message') ?? $response->status()));
        }

        $inputTokens  = 0;
        $outputTokens = 0;
        $stopReason   = 'end_turn';

        // Track tool_use content blocks being streamed
        $toolCalls        = [];
        $currentBlockType = null;
        $currentToolId    = null;
        $currentToolName  = null;
        $toolInputJson    = '';

        $body   = $response->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(1024);
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);
                if (!str_starts_with($line, 'data: ')) continue;

                $json = json_decode(substr($line, 6), true);
                if (!$json) continue;

                $type = $json['type'] ?? '';

                if ($type === 'content_block_start') {
                    $block = $json['content_block'] ?? [];
                    $currentBlockType = $block['type'] ?? 'text';
                    if ($currentBlockType === 'tool_use') {
                        $currentToolId   = $block['id'] ?? '';
                        $currentToolName = $block['name'] ?? '';
                        $toolInputJson   = '';
                    }
                }

                if ($type === 'content_block_delta') {
                    $delta = $json['delta'] ?? [];
                    if (($delta['type'] ?? '') === 'text_delta' && isset($delta['text'])) {
                        yield $delta['text'];
                    } elseif (($delta['type'] ?? '') === 'input_json_delta' && isset($delta['partial_json'])) {
                        $toolInputJson .= $delta['partial_json'];
                    }
                }

                if ($type === 'content_block_stop' && $currentBlockType === 'tool_use') {
                    $toolCalls[] = [
                        'id'    => $currentToolId,
                        'name'  => $currentToolName,
                        'input' => json_decode($toolInputJson, true) ?? [],
                    ];
                    $currentBlockType = null;
                }

                if ($type === 'message_delta') {
                    if (isset($json['usage'])) {
                        $outputTokens = $json['usage']['output_tokens'] ?? 0;
                    }
                    if (isset($json['delta']['stop_reason'])) {
                        $stopReason = $json['delta']['stop_reason'];
                    }
                }

                if ($type === 'message_start' && isset($json['message']['usage'])) {
                    $inputTokens = $json['message']['usage']['input_tokens'] ?? 0;
                }
            }
        }

        yield [
            'usage'       => ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens],
            'tool_calls'  => $toolCalls,
            'stop_reason' => $stopReason,
        ];
    }

    private function headers(): array
    {
        return [
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
            'content-type'      => 'application/json',
        ];
    }

    private function splitSystemMessage(array $messages): array
    {
        $system = null;
        $rest   = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system = $msg['content'];
            } else {
                $rest[] = $msg;
            }
        }
        return [$system, $rest];
    }

    private function fallbackModels(): array
    {
        return [
            'claude-opus-4-6',
            'claude-sonnet-4-6',
            'claude-haiku-4-5-20251001',
        ];
    }
}
