<?php

namespace App\Mcp\Resources;

use App\Models\Person;

class PersonGetResource
{
    public static function descriptor(): array
    {
        return [
            'uri'         => 'people://{id}',
            'name'        => 'Person Details',
            'description' => 'Full person record: identities, linked companies.',
            'mimeType'    => 'application/json',
        ];
    }

    public static function read(array $params): array
    {
        $person = Person::with(['identities', 'companies.primaryDomain', 'mergedInto'])
            ->findOrFail($params['id']);

        return [
            'id'             => $person->id,
            'first_name'     => $person->first_name,
            'last_name'      => $person->last_name,
            'full_name'      => $person->full_name,
            'is_our_org'     => $person->is_our_org,
            'merged_into_id' => $person->merged_into_id,
            'merged_into'    => $person->mergedInto ? ['id' => $person->mergedInto->id, 'full_name' => $person->mergedInto->full_name] : null,
            'identities'     => $person->identities->map(fn($i) => [
                'id'               => $i->id,
                'type'             => $i->type,
                'value'            => $i->value,
                'value_normalized' => $i->value_normalized,
                'system_slug'      => $i->system_slug,
                'is_team_member'   => $i->is_team_member,
                'is_bot'           => $i->is_bot,
            ])->values()->all(),
            'companies' => $person->companies->map(fn($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'primary_domain' => $c->primaryDomain->first()?->domain,
                'role'           => $c->pivot->role,
                'started_at'     => $c->pivot->started_at,
                'ended_at'       => $c->pivot->ended_at,
            ])->values()->all(),
            'updated_at' => $person->updated_at?->toIso8601String(),
        ];
    }
}
