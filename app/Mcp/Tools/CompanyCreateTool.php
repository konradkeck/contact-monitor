<?php

namespace App\Mcp\Tools;

use App\Models\Company;
use App\Models\CompanyDomain;

class CompanyCreateTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'company_create',
            'description' => 'Create a new company.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'name'           => ['type' => 'string', 'description' => 'Company name'],
                    'primary_domain' => ['type' => 'string', 'description' => 'Primary domain (optional)'],
                ],
                'required' => ['name'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        $company = Company::create(['name' => $params['name']]);

        if (!empty($params['primary_domain'])) {
            CompanyDomain::create([
                'company_id' => $company->id,
                'domain'     => strtolower(trim($params['primary_domain'])),
                'is_primary' => true,
            ]);
        }

        return ['id' => $company->id, 'name' => $company->name];
    }
}
