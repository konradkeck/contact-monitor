<?php

namespace App\Mcp\Tools;

use App\Models\Conversation;

class ConversationArchiveTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'conversation_archive',
            'description' => 'Archive a conversation.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'conversation_id' => ['type' => 'integer'],
                ],
                'required' => ['conversation_id'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        $conversation = Conversation::findOrFail($params['conversation_id']);
        $conversation->update(['archived_at' => now()]);

        return ['conversation_id' => $conversation->id, 'archived' => true];
    }
}
