<?php

namespace App\Mcp\Resources;

use App\Models\Person;

class PeopleListResource
{
    public static function descriptor(): array
    {
        return [
            'uri'         => 'people://list',
            'name'        => 'People List',
            'description' => 'Paginated list of people. Params: page, q (search), is_our_org (bool).',
            'mimeType'    => 'application/json',
        ];
    }

    public static function read(array $params): array
    {
        $q        = $params['q'] ?? null;
        $page     = max(1, (int) ($params['page'] ?? 1));
        $isOurOrg = isset($params['is_our_org']) ? filter_var($params['is_our_org'], FILTER_VALIDATE_BOOLEAN) : null;

        $query = Person::notMerged();

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('first_name', 'ilike', "%{$q}%")
                    ->orWhere('last_name', 'ilike', "%{$q}%")
                    ->orWhereHas('identities', fn($i) => $i->where('value_normalized', 'ilike', "%{$q}%"));
            });
        }

        if ($isOurOrg !== null) {
            $query->where('is_our_org', $isOurOrg);
        }

        $paginator = $query->orderBy('last_name')->orderBy('first_name')->paginate(25, ['*'], 'page', $page);

        return [
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'items'        => $paginator->map(fn($p) => [
                'id'         => $p->id,
                'full_name'  => $p->full_name,
                'is_our_org' => $p->is_our_org,
            ])->values()->all(),
        ];
    }
}
