<?php

namespace App\Mcp\Tools;

use App\Models\Person;

class PersonMergeTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'person_merge',
            'description' => 'Merge source person into target. Non-destructive: sets merged_into_id on source.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'source_id' => ['type' => 'integer', 'description' => 'Person to merge (becomes secondary)'],
                    'target_id' => ['type' => 'integer', 'description' => 'Person to merge into (becomes primary)'],
                ],
                'required' => ['source_id', 'target_id'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        $source = Person::findOrFail($params['source_id']);
        $target = Person::findOrFail($params['target_id']);

        if ($source->id === $target->id) {
            throw new \InvalidArgumentException('Cannot merge a person into themselves.');
        }

        $source->update(['merged_into_id' => $target->id]);

        return [
            'source_id'   => $source->id,
            'source_name' => $source->full_name,
            'target_id'   => $target->id,
            'target_name' => $target->full_name,
        ];
    }
}
