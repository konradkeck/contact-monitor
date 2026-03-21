<?php

namespace App\Mcp\Resources;

use App\Models\Activity;

class ActivitySearchResource
{
    public static function descriptor(): array
    {
        return [
            'uri'         => 'activity://search',
            'name'        => 'Activity Search',
            'description' => 'Search activities. Params: q, from (date), to (date), type, company_id, person_id, page.',
            'mimeType'    => 'application/json',
        ];
    }

    public static function read(array $params): array
    {
        $q         = $params['q'] ?? null;
        $from      = $params['from'] ?? null;
        $to        = $params['to'] ?? null;
        $type      = $params['type'] ?? null;
        $companyId = $params['company_id'] ?? null;
        $personId  = $params['person_id'] ?? null;
        $page      = max(1, (int) ($params['page'] ?? 1));

        $query = Activity::with(['company:id,name', 'person:id,first_name,last_name'])
            ->whereNull('companies.merged_into_id')
            ->leftJoin('companies', 'activities.company_id', '=', 'companies.id');

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('activities.description', 'ilike', "%{$q}%")
                    ->orWhereHas('company', fn($c) => $c->where('name', 'ilike', "%{$q}%"))
                    ->orWhereHas('person', fn($p) => $p->where('first_name', 'ilike', "%{$q}%")->orWhere('last_name', 'ilike', "%{$q}%"));
            });
        }

        if (!empty($from)) {
            $query->whereDate('activities.occurred_at', '>=', $from);
        }
        if (!empty($to)) {
            $query->whereDate('activities.occurred_at', '<=', $to);
        }
        if ($type) {
            $query->where('activities.type', $type);
        }
        if ($companyId) {
            $query->where('activities.company_id', $companyId);
        }
        if ($personId) {
            $query->where('activities.person_id', $personId);
        }

        $paginator = $query->select('activities.*')
            ->orderByDesc('activities.occurred_at')
            ->paginate(25, ['*'], 'page', $page);

        return [
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'items'        => $paginator->map(fn($a) => [
                'id'           => $a->id,
                'type'         => $a->type,
                'description'  => $a->description,
                'company_id'   => $a->company_id,
                'company_name' => $a->company?->name,
                'person_id'    => $a->person_id,
                'person_name'  => $a->person?->full_name,
                'occurred_at'  => $a->occurred_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
