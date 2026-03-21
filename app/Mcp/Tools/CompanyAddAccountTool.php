<?php

namespace App\Mcp\Tools;

use App\Models\Account;
use App\Models\Company;

class CompanyAddAccountTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'company_add_account',
            'description' => 'Link an external system account to a company.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'company_id'  => ['type' => 'integer'],
                    'system_type' => ['type' => 'string'],
                    'system_slug' => ['type' => 'string'],
                    'external_id' => ['type' => 'string'],
                ],
                'required' => ['company_id', 'system_type', 'system_slug', 'external_id'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        Company::findOrFail($params['company_id']);

        $account = Account::firstOrCreate(
            [
                'system_type' => $params['system_type'],
                'system_slug' => $params['system_slug'],
                'external_id' => $params['external_id'],
            ],
            ['company_id' => $params['company_id']]
        );

        if ($account->wasRecentlyCreated) {
            // Already set via firstOrCreate
        } elseif (!$account->company_id) {
            $account->update(['company_id' => $params['company_id']]);
        }

        return ['id' => $account->id, 'system_type' => $account->system_type, 'company_id' => $account->company_id];
    }
}
