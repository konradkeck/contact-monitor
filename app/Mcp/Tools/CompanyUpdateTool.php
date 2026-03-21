<?php

namespace App\Mcp\Tools;

use App\Models\Company;

class CompanyUpdateTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'company_update',
            'description' => 'Update a company\'s name or timezone.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'id'       => ['type' => 'integer', 'description' => 'Company ID'],
                    'name'     => ['type' => 'string'],
                    'timezone' => ['type' => 'string'],
                ],
                'required' => ['id'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        $company = Company::findOrFail($params['id']);
        $company->update(array_filter([
            'name'     => $params['name'] ?? null,
            'timezone' => $params['timezone'] ?? null,
        ], fn($v) => $v !== null));

        return ['id' => $company->id, 'name' => $company->name, 'timezone' => $company->timezone];
    }
}
