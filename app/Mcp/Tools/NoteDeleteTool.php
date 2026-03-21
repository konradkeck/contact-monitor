<?php

namespace App\Mcp\Tools;

use App\Models\Note;

class NoteDeleteTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'note_delete',
            'description' => 'Delete a note (soft delete).',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'note_id' => ['type' => 'integer'],
                ],
                'required' => ['note_id'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        $note = Note::findOrFail($params['note_id']);
        $note->delete();

        return ['deleted' => true, 'note_id' => $params['note_id']];
    }
}
