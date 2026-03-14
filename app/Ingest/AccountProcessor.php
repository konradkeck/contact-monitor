<?php

namespace App\Ingest;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyAlias;
use App\Models\CompanyDomain;
use App\Models\IngestItem;

/**
 * Processes ingest items of type 'account'.
 *
 * Payload fields:
 *   company_name   string  optional
 *   email          string  optional – used to match company by domain
 *   phone          string  optional
 *   address        string  optional
 *   meta           array   optional extra fields
 */
class AccountProcessor
{
    public function process(IngestItem $item): void
    {
        $payload = $item->payload;

        $account = Account::withTrashed()
            ->where('system_type', $item->system_type)
            ->where('system_slug', $item->system_slug)
            ->where('external_id', $item->external_id)
            ->first();

        if ($item->action === 'delete') {
            if ($account && ! $account->trashed()) {
                $account->delete();
            }
            $item->update(['status' => 'done', 'processed_at' => now()]);

            return;
        }

        $companyId = $account?->company_id ?? $this->resolveCompanyId($payload);

        $meta = array_merge($payload['meta'] ?? [], array_filter([
            'company_name' => $payload['company_name'] ?? null,
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'address' => $payload['address'] ?? null,
        ]));

        if ($account === null) {
            $account = Account::create([
                'company_id' => $companyId,
                'system_type' => $item->system_type,
                'system_slug' => $item->system_slug,
                'external_id' => $item->external_id,
                'meta_json' => $meta ?: null,
            ]);
        } else {
            if ($account->trashed()) {
                $account->restore();
            }
            $account->update([
                'company_id' => $companyId ?? $account->company_id,
                'meta_json' => $meta ?: $account->meta_json,
            ]);
        }

        $item->update([
            'status' => 'done',
            'entity_type' => Account::class,
            'entity_id' => $account->id,
            'processed_at' => now(),
        ]);
    }

    private function resolveCompanyId(array $payload): ?int
    {
        // Try by email domain
        if (! empty($payload['email'])) {
            $parts = explode('@', $payload['email']);
            $domain = strtolower(trim($parts[1] ?? ''));
            if ($domain) {
                $companyId = CompanyDomain::where('domain', $domain)->value('company_id');
                if ($companyId) {
                    return $companyId;
                }
            }
        }

        // Try by company name (alias match)
        if (! empty($payload['company_name'])) {
            $normalized = strtolower(trim($payload['company_name']));
            $companyId = CompanyAlias::where('alias_normalized', $normalized)->value('company_id');
            if ($companyId) {
                return $companyId;
            }
        }

        return null;
    }
}
