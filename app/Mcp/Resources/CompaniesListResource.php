<?php

namespace App\Mcp\Resources;

use App\Models\Company;

class CompaniesListResource
{
    public static function descriptor(): array
    {
        return [
            'uri'         => 'companies://list',
            'name'        => 'Companies List',
            'description' => 'Paginated list of companies. Params: page (int), q (search string).',
            'mimeType'    => 'application/json',
        ];
    }

    public static function read(array $params): array
    {
        $q    = $params['q'] ?? null;
        $page = max(1, (int) ($params['page'] ?? 1));

        $query = Company::notMerged()->with('primaryDomain');

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'ilike', "%{$q}%")
                    ->orWhereHas('domains', fn($d) => $d->where('domain', 'ilike', "%{$q}%"));
            });
        }

        $paginator = $query->orderBy('name')->paginate(25, ['*'], 'page', $page);

        return [
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'items'        => $paginator->map(fn($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'primary_domain' => $c->primaryDomain->first()?->domain,
            ])->values()->all(),
        ];
    }
}
