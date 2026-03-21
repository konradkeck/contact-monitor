<?php
namespace App\Broadcasting;

use App\Models\AiChat;
use App\Models\User;

class ChatChannel
{
    public function join(User $user, int $chatId): bool
    {
        $chat = AiChat::find($chatId);
        return $chat && $chat->canAccess($user->id);
    }
}
