<?php

namespace App\Mcp\Tools;

use App\Models\Company;
use App\Models\Note;
use App\Models\NoteLink;
use App\Models\Person;
use App\Models\SmartNote;

class SmartNoteRecognizeTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'smart_note_recognize',
            'description' => 'Recognize a smart note by splitting it into segments and assigning to companies/people.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'smart_note_id' => ['type' => 'integer'],
                    'segments'      => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'content'    => ['type' => 'string'],
                                'company_id' => ['type' => 'integer', 'description' => 'Assign note to this company'],
                                'person_id'  => ['type' => 'integer', 'description' => 'Assign note to this person'],
                            ],
                            'required' => ['content'],
                        ],
                    ],
                ],
                'required' => ['smart_note_id', 'segments'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        $smartNote = SmartNote::findOrFail($params['smart_note_id']);
        $savedSegs = [];

        foreach ($params['segments'] as $seg) {
            $companyId = isset($seg['company_id']) ? (int) $seg['company_id'] : null;
            $personId  = isset($seg['person_id']) ? (int) $seg['person_id'] : null;
            $noteId    = null;

            $assignTo = $companyId ? 'company' : ($personId ? 'person' : null);
            $entityId = $companyId ?? $personId;

            if ($assignTo && $entityId) {
                $note = Note::create([
                    'content'   => $seg['content'],
                    'source'    => 'smart_note',
                    'meta_json' => [
                        'as_internal_note' => $smartNote->as_internal_note,
                        'smart_note_id'    => $smartNote->id,
                    ],
                ]);

                $linkableType = $assignTo === 'company' ? Company::class : Person::class;

                NoteLink::create([
                    'note_id'       => $note->id,
                    'linkable_type' => $linkableType,
                    'linkable_id'   => $entityId,
                ]);

                $noteId = $note->id;
            }

            $savedSegs[] = [
                'content'    => $seg['content'],
                'company_id' => $companyId,
                'person_id'  => $personId,
                'note_id'    => $noteId,
            ];
        }

        $smartNote->segments_json = $savedSegs;
        $smartNote->status        = 'recognized';
        $smartNote->save();

        return ['smart_note_id' => $smartNote->id, 'status' => 'recognized', 'notes_created' => count(array_filter($savedSegs, fn($s) => $s['note_id']))];
    }
}
