<?php

namespace App\Mcp\Tools;

use App\Models\Company;
use App\Models\CompanyDomain;

class CompanyAddDomainTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'company_add_domain',
            'description' => 'Add a domain to a company.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'company_id'  => ['type' => 'integer'],
                    'domain'      => ['type' => 'string'],
                    'set_primary' => ['type' => 'boolean', 'description' => 'Make this the primary domain'],
                ],
                'required' => ['company_id', 'domain'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        $company    = Company::findOrFail($params['company_id']);
        $setPrimary = (bool) ($params['set_primary'] ?? false);
        $domain     = strtolower(trim($params['domain']));

        if ($setPrimary) {
            $company->domains()->update(['is_primary' => false]);
        }

        $record = CompanyDomain::firstOrCreate(
            ['company_id' => $company->id, 'domain' => $domain],
            ['is_primary' => $setPrimary]
        );

        if ($setPrimary && !$record->is_primary) {
            $record->update(['is_primary' => true]);
        }

        return ['id' => $record->id, 'domain' => $record->domain, 'is_primary' => $record->is_primary];
    }
}
