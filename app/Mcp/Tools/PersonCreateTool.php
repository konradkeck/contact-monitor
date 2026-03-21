<?php

namespace App\Mcp\Tools;

use App\Models\Person;

class PersonCreateTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'person_create',
            'description' => 'Create a new person.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'first_name' => ['type' => 'string'],
                    'last_name'  => ['type' => 'string'],
                    'is_our_org' => ['type' => 'boolean'],
                ],
                'required' => ['first_name'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        $person = Person::create([
            'first_name' => $params['first_name'],
            'last_name'  => $params['last_name'] ?? null,
            'is_our_org' => (bool) ($params['is_our_org'] ?? false),
        ]);

        return ['id' => $person->id, 'full_name' => $person->full_name];
    }
}
