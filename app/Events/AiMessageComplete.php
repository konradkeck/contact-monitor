<?php
namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AiMessageComplete implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $chatId,
        public readonly int $messageId,
        public readonly string $content,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly bool $stopped = false,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.' . $this->chatId)];
    }

    public function broadcastAs(): string
    {
        return 'AiMessageComplete';
    }
}
