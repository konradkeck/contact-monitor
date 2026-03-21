<?php

namespace App\Mcp\Tools;

use App\Models\Note;
use App\Models\NoteLink;

class NoteCreateTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'note_create',
            'description' => 'Create a note linked to an entity. entity_type: App\\Models\\Company, App\\Models\\Person or App\\Models\\Conversation.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'content'             => ['type' => 'string'],
                    'entity_type'         => ['type' => 'string'],
                    'entity_id'           => ['type' => 'integer'],
                    'as_internal_note'    => ['type' => 'boolean'],
                ],
                'required' => ['content', 'entity_type', 'entity_id'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        $allowed = ['App\Models\Company', 'App\Models\Person', 'App\Models\Conversation'];
        if (!in_array($params['entity_type'], $allowed, true)) {
            throw new \InvalidArgumentException('Invalid entity_type. Allowed: ' . implode(', ', $allowed));
        }

        $note = Note::create([
            'content'   => $params['content'],
            'source'    => 'mcp',
            'meta_json' => ['as_internal_note' => (bool) ($params['as_internal_note'] ?? false)],
        ]);

        NoteLink::create([
            'note_id'       => $note->id,
            'linkable_type' => $params['entity_type'],
            'linkable_id'   => $params['entity_id'],
        ]);

        return ['id' => $note->id, 'content' => $note->content];
    }
}
