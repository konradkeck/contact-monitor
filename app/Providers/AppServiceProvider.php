<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Identity;
use App\Models\SynchronizerServer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        View::composer('layouts.app', function (\Illuminate\View\View $view) {
            // Cache the server-exists check for 60s — it rarely changes
            $hasServers = Cache::remember('layout.has_servers', 60, fn () => SynchronizerServer::exists());

            // Server health: read last-known ping result from cache (set by ping() controller)
            $serverNeedsAttention = false;
            $serverBadCount = 0;
            $serverIds = Cache::remember('layout.server_ids', 60, fn () => SynchronizerServer::pluck('id')->toArray());
            foreach ($serverIds as $sid) {
                $status = Cache::get('server.ping.'.$sid);
                if ($status === false) {
                    $serverNeedsAttention = true;
                    $serverBadCount++;
                }
            }

            // Mapping health: check if any system is < 50% linked
            $mappingUnhealthySystems = Cache::remember('layout.mapping_health', 60, function () {
                $bad = [];

                $accountSystems = Account::select('system_type', 'system_slug',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('COUNT(CASE WHEN company_id IS NULL THEN 1 END) as unlinked')
                )->groupBy('system_type', 'system_slug')->get();

                foreach ($accountSystems as $sys) {
                    if ($sys->total > 0 && ($sys->total - $sys->unlinked) / $sys->total < 0.5) {
                        $bad[] = $sys->system_slug;
                    }
                }

                $identitySystems = Identity::select('system_slug', 'type',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('COUNT(CASE WHEN person_id IS NULL THEN 1 END) as unlinked')
                )->where('is_bot', false)->groupBy('system_slug', 'type')->get();

                foreach ($identitySystems as $sys) {
                    if ($sys->total > 0 && ($sys->total - $sys->unlinked) / $sys->total < 0.5) {
                        $bad[] = $sys->system_slug;
                    }
                }

                return array_values(array_unique($bad));
            });

            $mappingNeedsAttention = ! empty($mappingUnhealthySystems);
            $configNeedsAttention  = $serverNeedsAttention || $mappingNeedsAttention;

            // Only compute the mapping sidebar data on data-relations routes
            $mappingSystems = collect();
            if (request()->routeIs('data-relations.*', 'our-company.*', 'filtering.*')) {
                $mappingSystems = Cache::remember('layout.mapping_systems', 30, function () {
                    $identityToSystem = [
                        'email'        => 'imap',
                        'slack_user'   => 'slack',
                        'discord_user' => 'discord',
                    ];

                    $accountSystems = Account::select('system_type', 'system_slug')
                        ->whereNotNull('system_type')->whereNotNull('system_slug')
                        ->distinct()->orderBy('system_type')->orderBy('system_slug')->get();

                    $whmcsSlugs = Account::whereIn('system_type', ['whmcs', 'metricscube'])
                        ->distinct()->pluck('system_slug')->toArray();

                    $identSystems = Identity::select('system_slug', 'type')
                        ->whereNotNull('system_slug')
                        ->where(function ($q) use ($whmcsSlugs) {
                            $q->where('type', '!=', 'email')
                              ->orWhere(function ($q2) use ($whmcsSlugs) {
                                  $q2->whereNotIn('system_slug', $whmcsSlugs)
                                     ->whereRaw("COALESCE(meta_json->>'system_type', '') NOT IN ('whmcs', 'metricscube')");
                              });
                        })
                        ->distinct()->orderBy('type')->orderBy('system_slug')->get()
                        ->map(fn ($r) => (object) [
                            'system_type' => $identityToSystem[$r->type] ?? $r->type,
                            'system_slug' => $r->system_slug,
                        ]);

                    return $accountSystems->concat($identSystems)
                        ->unique(fn ($s) => $s->system_type.'/'.$s->system_slug)
                        ->sortBy(['system_type', 'system_slug'])
                        ->values();
                });
            }

            $view->with(compact(
                'hasServers', 'mappingSystems',
                'configNeedsAttention', 'serverNeedsAttention', 'serverBadCount',
                'mappingNeedsAttention', 'mappingUnhealthySystems'
            ));
        });
    }
}
