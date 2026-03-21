<?php
namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ParticipantUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $chatId,
        public readonly array $participants,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.' . $this->chatId)];
    }

    public function broadcastAs(): string
    {
        return 'ParticipantUpdated';
    }
}
