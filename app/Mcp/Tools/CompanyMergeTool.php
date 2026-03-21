<?php

namespace App\Mcp\Tools;

use App\Models\Company;

class CompanyMergeTool
{
    public static function descriptor(): array
    {
        return [
            'name'        => 'company_merge',
            'description' => 'Merge source company into target. Non-destructive: sets merged_into_id on source.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'source_id' => ['type' => 'integer', 'description' => 'Company to be merged (becomes secondary)'],
                    'target_id' => ['type' => 'integer', 'description' => 'Company to merge into (becomes primary)'],
                ],
                'required' => ['source_id', 'target_id'],
            ],
        ];
    }

    public static function execute(array $params): array
    {
        $source = Company::findOrFail($params['source_id']);
        $target = Company::findOrFail($params['target_id']);

        if ($source->id === $target->id) {
            throw new \InvalidArgumentException('Cannot merge a company into itself.');
        }

        $source->update(['merged_into_id' => $target->id]);

        return [
            'source_id'   => $source->id,
            'source_name' => $source->name,
            'target_id'   => $target->id,
            'target_name' => $target->name,
        ];
    }
}
