<?php

namespace App\Ai\CompanyAnalysis;

use App\Models\AnalysisEntity;
use App\Models\AnalysisField;
use App\Models\AnalysisStepRun;

class ResultExtractor
{
    /**
     * Parse AI response content into structured JSON.
     * Strips markdown code fences if present.
     */
    public function parseResponse(string $rawContent): ?array
    {
        $content = trim($rawContent);

        // Strip markdown code fences
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $content, $m)) {
            $content = trim($m[1]);
        }

        $parsed = json_decode($content, true);

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * Extract structured fields and entities from parsed AI response
     * and persist them to the database.
     */
    public function extractAndPersist(array $parsed, AnalysisStepRun $stepRun, int $companyId, int $runId): void
    {
        $sortOrder = 0;

        // Walk through parsed data and extract scalar fields
        foreach ($parsed as $key => $value) {
            if ($key === 'entities' || $key === 'no_update') {
                continue;
            }

            // Handle arrays that look like entity collections
            if (is_array($value) && $this->isEntityCollection($key, $value)) {
                $this->persistEntities($key, $value, $stepRun, $companyId, $runId);
                continue;
            }

            // Handle nested objects with value/confidence structure
            if (is_array($value) && isset($value['value'])) {
                $this->persistField(
                    $key,
                    $value['value'],
                    $value['type'] ?? 'string',
                    $value['confidence'] ?? null,
                    $this->guessFieldGroup($key),
                    $stepRun,
                    $companyId,
                    $runId,
                    $sortOrder++
                );
                continue;
            }

            // Handle simple scalar values
            if (!is_array($value) || $this->isSimpleArray($value)) {
                $this->persistField(
                    $key,
                    is_array($value) ? implode(', ', $value) : $value,
                    $this->guessFieldType($value),
                    null,
                    $this->guessFieldGroup($key),
                    $stepRun,
                    $companyId,
                    $runId,
                    $sortOrder++
                );
                continue;
            }

            // Handle complex objects — store as JSON string field
            if (is_array($value)) {
                $this->persistField(
                    $key,
                    json_encode($value, JSON_UNESCAPED_UNICODE),
                    'json',
                    $value['confidence'] ?? null,
                    $this->guessFieldGroup($key),
                    $stepRun,
                    $companyId,
                    $runId,
                    $sortOrder++
                );
            }
        }

        // Handle explicit entities array
        if (isset($parsed['entities']) && is_array($parsed['entities'])) {
            foreach ($parsed['entities'] as $idx => $entity) {
                $type = $entity['type'] ?? 'unknown';
                $data = $entity['data'] ?? $entity;
                unset($data['type'], $data['confidence']);

                AnalysisEntity::create([
                    'company_id'  => $companyId,
                    'run_id'      => $runId,
                    'step_run_id' => $stepRun->id,
                    'entity_type' => $type,
                    'display_name' => $data['name'] ?? $data['display_name'] ?? null,
                    'data_json'   => $data,
                    'confidence'  => $entity['confidence'] ?? null,
                    'sort_order'  => $idx,
                ]);
            }
        }
    }

    private function persistField(
        string $key,
        mixed $value,
        string $type,
        mixed $confidence,
        string $group,
        AnalysisStepRun $stepRun,
        int $companyId,
        int $runId,
        int $sortOrder
    ): void {
        if ($value === null) {
            return;
        }

        $stringValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        $confidenceStr = is_numeric($confidence)
            ? ($confidence >= 0.8 ? 'high' : ($confidence >= 0.5 ? 'medium' : 'low'))
            : (is_string($confidence) ? $confidence : null);

        AnalysisField::updateOrCreate(
            ['run_id' => $runId, 'field_key' => $key],
            [
                'company_id'  => $companyId,
                'step_run_id' => $stepRun->id,
                'field_group' => $group,
                'field_value' => $stringValue,
                'field_type'  => $type,
                'confidence'  => $confidenceStr,
                'is_inferred' => $confidenceStr === 'low',
                'sort_order'  => $sortOrder,
            ]
        );
    }

    private function persistEntities(string $key, array $items, AnalysisStepRun $stepRun, int $companyId, int $runId): void
    {
        $entityType = $this->singularize($key);

        foreach ($items as $idx => $item) {
            if (!is_array($item)) {
                continue;
            }

            $displayName = $item['name'] ?? $item['value'] ?? (is_string($item) ? $item : null);
            $confidence = $item['confidence'] ?? null;
            $data = $item;
            unset($data['confidence']);

            AnalysisEntity::create([
                'company_id'  => $companyId,
                'run_id'      => $runId,
                'step_run_id' => $stepRun->id,
                'entity_type' => $entityType,
                'display_name' => $displayName,
                'data_json'   => $data,
                'confidence'  => is_numeric($confidence) ? ($confidence >= 0.8 ? 'high' : ($confidence >= 0.5 ? 'medium' : 'low')) : $confidence,
                'sort_order'  => $idx,
            ]);
        }
    }

    private function isEntityCollection(string $key, array $value): bool
    {
        $entityKeys = [
            'c_level_people', 'executives', 'investors', 'owners',
            'funding_history', 'funding_rounds', 'competitors',
            'evidence', 'operating_countries', 'markets',
            'owners_or_parent_entities', 'major_shareholders_or_investors',
        ];

        if (in_array($key, $entityKeys)) {
            return true;
        }

        // Check if it's a list of objects
        if (empty($value)) {
            return false;
        }

        return isset($value[0]) && is_array($value[0]);
    }

    private function isSimpleArray(array $value): bool
    {
        foreach ($value as $item) {
            if (is_array($item) || is_object($item)) {
                return false;
            }
        }

        return true;
    }

    private function guessFieldType(mixed $value): string
    {
        if (is_bool($value)) return 'boolean';
        if (is_int($value)) return 'number';
        if (is_float($value)) return 'number';
        if (is_array($value)) return 'string';

        $str = (string) $value;
        if (preg_match('/^https?:\/\//', $str)) return 'url';
        if (preg_match('/^\d{4}$/', $str)) return 'number';

        return 'string';
    }

    private function guessFieldGroup(string $key): string
    {
        $groups = [
            'geo' => ['country', 'city', 'hq_country', 'hq_city', 'country_hint', 'city_hint', 'operating_countries'],
            'financial' => ['revenue', 'funding', 'customer_count', 'employee_count', 'valuation'],
            'identity' => ['company_name', 'resolved_company_name', 'official_company_name', 'domain', 'resolved_domain', 'website', 'other_known_names'],
            'classification' => ['industry', 'company_type', 'owner_type', 'market_position', 'is_legitimate'],
        ];

        foreach ($groups as $group => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($key, $keyword)) {
                    return $group;
                }
            }
        }

        return 'general';
    }

    private function singularize(string $key): string
    {
        $map = [
            'c_level_people' => 'executive',
            'executives' => 'executive',
            'investors' => 'investor',
            'owners' => 'owner',
            'funding_history' => 'funding_round',
            'funding_rounds' => 'funding_round',
            'competitors' => 'competitor',
            'evidence' => 'evidence',
            'operating_countries' => 'market',
            'markets' => 'market',
            'owners_or_parent_entities' => 'owner',
            'major_shareholders_or_investors' => 'investor',
        ];

        return $map[$key] ?? rtrim($key, 's');
    }
}
