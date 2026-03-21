<?php

namespace App\Ai\CompanyAnalysis;

use App\Models\DomainClassification;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DomainSyncService
{
    private const DEFAULT_SOURCES = [
        'disposable' => 'https://raw.githubusercontent.com/disposable/disposable-email-domains/master/domains.txt',
        'free_email' => 'https://gist.githubusercontent.com/tbrianjones/5992856/raw/93213efb652749e226e69884d6c048e595c1280a/free_email_provider_domains.txt',
    ];

    /**
     * Sync if last sync was >24h ago. Returns true if synced.
     */
    public function syncIfStale(): bool
    {
        $autoSyncEnabled = SystemSetting::get('domain_sync_auto_enabled', false);
        $lastSynced = SystemSetting::get('domain_classifications_last_synced_at');

        if (!$autoSyncEnabled && $lastSynced) {
            return false;
        }

        if ($lastSynced && now()->diffInHours($lastSynced) < 24) {
            return false;
        }

        return $this->sync() > 0;
    }

    /**
     * Sync domain lists from configured sources.
     * Returns total count of domains synced.
     */
    public function sync(): int
    {
        $sources = $this->getSources();
        $total = 0;

        foreach ($sources as $type => $url) {
            if (empty($url)) {
                continue;
            }

            try {
                $response = Http::timeout(30)->get($url);

                if (!$response->successful()) {
                    Log::warning("Domain sync failed for {$type}: HTTP {$response->status()}");
                    continue;
                }

                $domains = collect(explode("\n", $response->body()))
                    ->map(fn ($d) => strtolower(trim($d)))
                    ->filter(fn ($d) => !empty($d) && !str_starts_with($d, '#') && str_contains($d, '.'))
                    ->unique()
                    ->values();

                if ($domains->isEmpty()) {
                    continue;
                }

                $dbType = $type === 'disposable' ? 'disposable' : 'free_email';

                // Batch upsert
                $chunks = $domains->chunk(500);
                foreach ($chunks as $chunk) {
                    $rows = $chunk->map(fn ($d) => [
                        'domain' => $d,
                        'type'   => $dbType,
                        'source' => 'github_list',
                    ])->all();

                    DB::table('domain_classifications')->upsert(
                        $rows,
                        ['domain', 'type'],
                        ['source']
                    );
                }

                $total += $domains->count();
            } catch (\Exception $e) {
                Log::warning("Domain sync error for {$type}: {$e->getMessage()}");
            }
        }

        SystemSetting::set('domain_classifications_last_synced_at', now()->toIso8601String());

        return $total;
    }

    /**
     * Get configured source URLs (or defaults).
     */
    public function getSources(): array
    {
        return SystemSetting::get('domain_sync_sources', self::DEFAULT_SOURCES);
    }

    /**
     * Get sync stats.
     */
    public function stats(): array
    {
        return [
            'free_email_count' => DomainClassification::freeEmail()->count(),
            'disposable_count' => DomainClassification::disposable()->count(),
            'last_synced_at'   => SystemSetting::get('domain_classifications_last_synced_at'),
            'auto_enabled'     => (bool) SystemSetting::get('domain_sync_auto_enabled', false),
            'sources'          => $this->getSources(),
        ];
    }
}
