<?php

namespace App\Mcp\Tools;

use App\Models\Company;
use App\Models\CompanyBrandStatus;

class CompanySetBrandStatusTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'company_set_brand_status',
            'description' => 'Set or update a brand product status for a company.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'company_id'       => ['type' => 'integer'],
                    'brand_product_id' => ['type' => 'integer'],
                    'stage'            => ['type' => 'string'],
                    'score'            => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                    'notes'            => ['type' => 'string'],
                ],
                'required' => ['company_id', 'brand_product_id', 'stage'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        Company::findOrFail($params['company_id']);

        $status = CompanyBrandStatus::updateOrCreate(
            ['company_id' => $params['company_id'], 'brand_product_id' => $params['brand_product_id']],
            array_filter([
                'stage'              => $params['stage'],
                'evaluation_score'   => $params['score'] ?? null,
                'evaluation_notes'   => $params['notes'] ?? null,
                'last_evaluated_at'  => now(),
            ], fn($v) => $v !== null)
        );

        return ['id' => $status->id, 'stage' => $status->stage, 'evaluation_score' => $status->evaluation_score];
    }
}
