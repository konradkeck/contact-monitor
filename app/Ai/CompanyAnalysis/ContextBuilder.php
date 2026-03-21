<?php

namespace App\Ai\CompanyAnalysis;

use App\Models\Company;
use App\Models\DomainClassification;

class ContextBuilder
{
    /**
     * Build the base variable context for prompt rendering from a Company.
     */
    public function build(Company $company): array
    {
        $company->load([
            'domains',
            'aliases',
            'accounts',
            'people.identities',
            'conversations' => fn ($q) => $q->orderByDesc('last_message_at')->limit(5),
            'conversations.messages' => fn ($q) => $q->orderByDesc('created_at')->limit(3),
            'brandStatuses.brandProduct',
        ]);

        $primaryDomain = $company->domains->firstWhere('is_primary', true) ?? $company->domains->first();
        $allDomains = $company->domains->pluck('domain')->implode(', ');

        // People and identities
        $people = $company->people->filter(fn ($p) => !$p->is_our_org && is_null($p->merged_into_id));
        $personName = $people->map(fn ($p) => trim($p->first_name . ' ' . $p->last_name))->filter()->implode(', ');

        $emails = $people->flatMap(fn ($p) => $p->identities->where('type', 'email')->pluck('value_normalized'))->filter()->unique();
        $contactEmails = $emails->implode(', ');
        $firstEmail = $emails->first();

        // Email domain classification
        $emailDomain = $firstEmail ? substr($firstEmail, strrpos($firstEmail, '@') + 1) : ($primaryDomain?->domain ?? '');
        $isFreeEmail = $emailDomain ? DomainClassification::isFreeEmail($emailDomain) : false;
        $isDisposable = $emailDomain ? DomainClassification::isDisposable($emailDomain) : false;

        // Latest conversation/message data
        $latestConv = $company->conversations->first();
        $latestMessage = $latestConv?->messages->first();
        $lastMessageExcerpt = $latestMessage ? mb_substr(strip_tags($latestMessage->content ?? ''), 0, 500) : '';

        // Try to extract domain from last message
        $domainFromLastMessage = $this->extractDomainFromText($lastMessageExcerpt);

        // Channel types
        $channelTypes = $company->conversations->pluck('channel_type')->unique()->filter()->implode(', ');

        // Services summary from accounts
        $servicesSummary = $this->buildServicesSummary($company);

        // Brand statuses
        $brandStatuses = $company->brandStatuses->map(fn ($bs) =>
            ($bs->brandProduct?->name ?? 'Unknown') . ': ' . ($bs->stage ?? 'none') . ' (score: ' . ($bs->evaluation_score ?? 'N/A') . ')'
        )->implode('; ');

        // Address from account meta
        $address = $this->extractAddress($company);

        return [
            'company_name'              => $company->name,
            'primary_domain'            => $primaryDomain?->domain ?? '',
            'all_domains'               => $allDomains,
            'person_name'               => $personName,
            'email'                     => $firstEmail ?? '',
            'email_domain'              => $emailDomain,
            'is_free_email_domain'      => $isFreeEmail ? 'yes' : 'no',
            'is_disposable_email_domain' => $isDisposable ? 'yes' : 'no',
            'contact_names'             => $personName,
            'contact_emails'            => $contactEmails,
            'domain_from_last_message'  => $domainFromLastMessage,
            'last_message_excerpt'      => $lastMessageExcerpt,
            'address'                   => $address,
            'channel_types'             => $channelTypes,
            'services_summary'          => $servicesSummary,
            'brand_statuses'            => $brandStatuses,
        ];
    }

    /**
     * Return a list of all available base variable names with descriptions.
     */
    public static function availableVariables(): array
    {
        return [
            'company_name'              => 'Company name from record',
            'primary_domain'            => 'Primary domain (if set)',
            'all_domains'               => 'All domains (comma-separated)',
            'person_name'               => 'Contact person names (comma-separated)',
            'email'                     => 'First contact email address',
            'email_domain'              => 'Domain part of email (or primary domain)',
            'is_free_email_domain'      => 'Whether email domain is a free provider (yes/no)',
            'is_disposable_email_domain' => 'Whether email domain is disposable (yes/no)',
            'contact_names'             => 'All contact names (comma-separated)',
            'contact_emails'            => 'All contact emails (comma-separated)',
            'domain_from_last_message'  => 'Domain extracted from latest message/signature',
            'last_message_excerpt'      => 'Last message excerpt (max 500 chars)',
            'address'                   => 'Postal address (from account metadata)',
            'channel_types'             => 'Communication channels (comma-separated)',
            'services_summary'          => 'Summary of services/products',
            'brand_statuses'            => 'Brand product statuses',
        ];
    }

    private function extractDomainFromText(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Match URLs or domain-like patterns
        if (preg_match('/(?:https?:\/\/)?(?:www\.)?([a-z0-9][-a-z0-9]*\.[a-z]{2,}(?:\.[a-z]{2,})?)/i', $text, $m)) {
            return strtolower($m[1]);
        }

        return '';
    }

    private function buildServicesSummary(Company $company): string
    {
        $totalServices = 0;
        $activeServices = 0;
        $totalRevenue = 0;

        foreach ($company->accounts as $acc) {
            $services = $acc->meta_json['services'] ?? [];
            foreach ($services as $svc) {
                $totalServices++;
                if (strtolower($svc['status'] ?? '') === 'active') {
                    $activeServices++;
                }
                $totalRevenue += (float) ($svc['total_revenue'] ?? 0);
            }
        }

        if ($totalServices === 0) {
            return '';
        }

        $parts = ["{$activeServices}/{$totalServices} active services"];
        if ($totalRevenue > 0) {
            $parts[] = 'total revenue: $' . number_format($totalRevenue, 2);
        }

        return implode(', ', $parts);
    }

    private function extractAddress(Company $company): string
    {
        foreach ($company->accounts as $acc) {
            $meta = $acc->meta_json ?? [];
            $parts = array_filter([
                $meta['address1'] ?? $meta['address'] ?? null,
                $meta['address2'] ?? null,
                $meta['city'] ?? null,
                $meta['state'] ?? null,
                $meta['postcode'] ?? $meta['zip'] ?? null,
                $meta['country'] ?? null,
            ]);
            if (!empty($parts)) {
                return implode(', ', $parts);
            }
        }

        return '';
    }
}
