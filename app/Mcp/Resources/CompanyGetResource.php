<?php

namespace App\Mcp\Resources;

use App\Models\Company;

class CompanyGetResource
{
    public static function descriptor(): array
    {
        return [
            'uri'         => 'companies://{id}',
            'name'        => 'Company Details',
            'description' => 'Full company record: domains, aliases, accounts, brand statuses, linked people.',
            'mimeType'    => 'application/json',
        ];
    }

    public static function read(array $params): array
    {
        $company = Company::with([
            'domains',
            'aliases',
            'accounts',
            'brandStatuses.brandProduct',
            'people',
            'mergedInto',
        ])->findOrFail($params['id']);

        return [
            'id'             => $company->id,
            'name'           => $company->name,
            'timezone'       => $company->timezone,
            'merged_into_id' => $company->merged_into_id,
            'merged_into'    => $company->mergedInto ? ['id' => $company->mergedInto->id, 'name' => $company->mergedInto->name] : null,
            'domains'        => $company->domains->map(fn($d) => [
                'domain'     => $d->domain,
                'is_primary' => $d->is_primary,
            ])->values()->all(),
            'aliases' => $company->aliases->map(fn($a) => [
                'alias'      => $a->alias,
                'is_primary' => $a->is_primary,
            ])->values()->all(),
            'accounts' => $company->accounts->map(fn($a) => [
                'id'          => $a->id,
                'system_type' => $a->system_type,
                'system_slug' => $a->system_slug,
                'external_id' => $a->external_id,
            ])->values()->all(),
            'brand_statuses' => $company->brandStatuses->map(fn($bs) => [
                'brand_product_id'   => $bs->brand_product_id,
                'brand_product_name' => $bs->brandProduct?->name,
                'stage'              => $bs->stage,
                'evaluation_score'   => $bs->evaluation_score,
                'evaluation_notes'   => $bs->evaluation_notes,
                'last_evaluated_at'  => $bs->last_evaluated_at?->toIso8601String(),
            ])->values()->all(),
            'people' => $company->people->map(fn($p) => [
                'id'         => $p->id,
                'full_name'  => $p->full_name,
                'role'       => $p->pivot->role,
                'started_at' => $p->pivot->started_at,
                'ended_at'   => $p->pivot->ended_at,
            ])->values()->all(),
            'updated_at' => $company->updated_at?->toIso8601String(),
        ];
    }
}
