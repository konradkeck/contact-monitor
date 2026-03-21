<?php

namespace App\Mcp\Tools;

use App\Models\Person;

class PersonUpdateTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'person_update',
            'description' => 'Update a person\'s name.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'id'         => ['type' => 'integer'],
                    'first_name' => ['type' => 'string'],
                    'last_name'  => ['type' => 'string'],
                ],
                'required' => ['id'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        $person = Person::findOrFail($params['id']);
        $person->update(array_filter([
            'first_name' => $params['first_name'] ?? null,
            'last_name'  => $params['last_name'] ?? null,
        ], fn($v) => $v !== null));

        return ['id' => $person->id, 'full_name' => $person->full_name];
    }
}
