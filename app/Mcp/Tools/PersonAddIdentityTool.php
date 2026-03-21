<?php

namespace App\Mcp\Tools;

use App\Models\Identity;
use App\Models\Person;

class PersonAddIdentityTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'person_add_identity',
            'description' => 'Add an identity (email, slack_user, discord_user) to a person.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'person_id'   => ['type' => 'integer'],
                    'type'        => ['type' => 'string', 'enum' => ['email', 'slack_user', 'discord_user']],
                    'value'       => ['type' => 'string'],
                    'system_slug' => ['type' => 'string'],
                ],
                'required' => ['person_id', 'type', 'value'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        Person::findOrFail($params['person_id']);

        $identity = Identity::firstOrCreate(
            ['type' => $params['type'], 'value_normalized' => strtolower($params['value'])],
            [
                'person_id'   => $params['person_id'],
                'value'       => $params['value'],
                'system_slug' => $params['system_slug'] ?? null,
            ]
        );

        return ['id' => $identity->id, 'type' => $identity->type, 'value' => $identity->value];
    }
}
