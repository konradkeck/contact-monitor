<?php

namespace App\Mcp\Tools;

use App\Models\Person;

class PersonMarkOurOrgTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'person_mark_our_org',
            'description' => 'Set or clear the is_our_org flag on a person.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'person_id'  => ['type' => 'integer'],
                    'is_our_org' => ['type' => 'boolean'],
                ],
                'required' => ['person_id', 'is_our_org'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        $person = Person::findOrFail($params['person_id']);
        $person->update(['is_our_org' => (bool) $params['is_our_org']]);

        return ['id' => $person->id, 'is_our_org' => $person->is_our_org];
    }
}
