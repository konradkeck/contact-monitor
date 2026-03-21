<?php

namespace App\Mcp\Tools;

use App\Models\Company;
use App\Models\Person;

class PersonLinkCompanyTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'person_link_company',
            'description' => 'Link a person to a company.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'person_id'  => ['type' => 'integer'],
                    'company_id' => ['type' => 'integer'],
                    'role'       => ['type' => 'string'],
                    'started_at' => ['type' => 'string', 'description' => 'Date (YYYY-MM-DD)'],
                    'ended_at'   => ['type' => 'string', 'description' => 'Date (YYYY-MM-DD)'],
                ],
                'required' => ['person_id', 'company_id'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        $person  = Person::findOrFail($params['person_id']);
        Company::findOrFail($params['company_id']);

        $person->companies()->syncWithoutDetaching([
            $params['company_id'] => array_filter([
                'role'       => $params['role'] ?? null,
                'started_at' => $params['started_at'] ?? null,
                'ended_at'   => $params['ended_at'] ?? null,
            ], fn($v) => $v !== null),
        ]);

        return ['person_id' => $params['person_id'], 'company_id' => $params['company_id']];
    }
}
