<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Identity;
use App\Models\Person;
use App\Models\SynchronizerServer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // ── Permission gates ──────────────────────────────────────────────────
        foreach (['browse_data', 'data_write', 'notes_write', 'analyse', 'configuration'] as $perm) {
            Gate::define($perm, fn ($user) => $user->hasPermission($perm));
        }

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

            // Setup assistant status (DB-only, no API call — used for sidebar/top-menu dot)
            $setupStatus = Cache::remember('layout.setup_status', 60, function () use ($hasServers) {
                if (!$hasServers) return 'active';
                $hasData = Account::exists();
                if (!$hasData) return 'active';

                // Mapping ratio — accounts linked to companies
                $accountSystems = Account::select('system_type', 'system_slug',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('COUNT(CASE WHEN company_id IS NULL THEN 1 END) as unlinked')
                )->groupBy('system_type', 'system_slug')->get();

                // Mapping ratio — identities linked to people (excluding bots)
                $identSystems = Identity::select('system_slug', 'type',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('COUNT(CASE WHEN person_id IS NULL THEN 1 END) as unlinked')
                )->where('is_bot', false)->groupBy('system_slug', 'type')->get();

                $worstRatio = 1.0;
                foreach ($accountSystems->concat($identSystems) as $sys) {
                    if ($sys->total === 0) continue;
                    $ratio = ($sys->total - $sys->unlinked) / $sys->total;
                    $worstRatio = min($worstRatio, $ratio);
                }

                $allSystems = $accountSystems->concat($identSystems);
                if ($allSystems->isNotEmpty() && $worstRatio < 0.5) return 'active';

                // Org contacts
                $hasOrgContacts = Person::where('is_our_org', true)->exists()
                               || Identity::where('is_team_member', true)->exists();
                if (!$hasOrgContacts) return 'active';

                if ($allSystems->isNotEmpty() && $worstRatio < 0.8) return 'partially_active';
                return 'completed';
            });

            $configNeedsAttention = $serverNeedsAttention || $mappingNeedsAttention || $setupStatus === 'active';

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

            // ── Layout-level computed variables (moved from @php blocks) ──
            $disabledMsg = 'Configure a synchronizer server first';

            $isConfigRoute = request()->routeIs(
                'synchronizer.*', 'data-relations.*', 'our-company.*',
                'filtering.*', 'segmentation.*', 'configuration.*', 'team-access.*',
                'setup-assistant.*'
            );

            $topSections = [
                'Browse Data' => [
                    'route'    => 'dashboard',
                    'pattern'  => ['dashboard', 'companies.*', 'people.*', 'conversations.*', 'activity.*'],
                    'disabled' => !$hasServers,
                    'type'     => 'normal',
                ],
                'Analyse' => [
                    'route'    => null,
                    'pattern'  => [],
                    'disabled' => true,
                    'type'     => 'ai',
                ],
                'Configuration' => [
                    'route'    => 'setup-assistant.index',
                    'pattern'  => ['synchronizer.*', 'data-relations.*', 'our-company.*', 'filtering.*', 'segmentation.*', 'configuration.*'],
                    'disabled' => false,
                    'type'     => 'config',
                    'dot'      => $configNeedsAttention,
                ],
            ];

            // Pre-compute isActive for each top section
            foreach ($topSections as $label => &$section) {
                $section['isActive'] = !empty($section['pattern']) && request()->routeIs($section['pattern']);
                $section['href'] = ($section['disabled'] || $section['route'] === null) ? '#' : route($section['route']);
                $section['permKey'] = match($section['type']) { 'config' => 'configuration', 'ai' => 'analyse', default => 'browse_data' };
            }
            unset($section);

            $onMapping = false;
            $currentMapping = null;
            $taActive  = false;
            $segActive = false;
            $saActive  = false;
            $syncItems = [];
            $drItems = [];
            $sidebarItems = [];

            if ($isConfigRoute) {
                $onMapping = request()->routeIs('data-relations.mapping', 'configuration.mapping');
                $currentMapping = (request()->route('systemType') && request()->route('systemSlug'))
                    ? request()->route('systemType').'/'.request()->route('systemSlug')
                    : null;

                $taActive  = request()->routeIs('team-access.*');
                $segActive = request()->routeIs('segmentation.*');
                $saActive  = request()->routeIs('setup-assistant.*');

                $syncItems = [
                    ['label' => 'Connections',          'route' => 'synchronizer.index',         'match' => ['synchronizer.index', 'synchronizer.connections.*', 'synchronizer.runs*', 'synchronizer.kill-all', 'synchronizer.run-all'], 'disabled' => !$hasServers, 'dot' => false,
                     'icon' => '<circle cx="6" cy="17" r="2.75" stroke-width="1.75"/><circle cx="20" cy="4" r="2" stroke-width="1.75"/><circle cx="20" cy="15" r="2" stroke-width="1.75"/><circle cx="10" cy="5" r="2" stroke-width="1.75"/><path stroke-linecap="round" stroke-width="1.75" d="M8 15L18.5 5.5M8 16.5L18.5 14.5M7.5 14.5L9.5 7"/>'],
                    ['label' => 'Synchronizer Servers', 'route' => 'synchronizer.servers.index', 'match' => ['synchronizer.servers.*', 'synchronizer.wizard.*'], 'disabled' => false, 'dot' => $serverNeedsAttention,
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>'],
                ];

                foreach ($syncItems as &$item) {
                    $item['active'] = request()->routeIs($item['match']);
                }
                unset($item);

                $drItems = [
                    ['href' => route('configuration.mapping'), 'label' => 'Mapping',         'active' => request()->routeIs('data-relations.index', 'configuration.mapping', 'data-relations.mapping'), 'dot' => $mappingNeedsAttention,
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>'],
                    ['href' => route('filtering.index'),       'label' => 'Filtering',       'active' => request()->routeIs('filtering.*'), 'dot' => false,
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>'],
                    ['href' => route('our-company.index'),     'label' => 'Our Organization','active' => request()->routeIs('our-company.*'), 'dot' => false,
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 21h18M4 21V7l8-4 8 4v14M9 21v-6h6v6"/>'],
                ];
            } elseif (auth()->check() && auth()->user()->hasPermission('browse_data')) {
                $companiesCount = Cache::remember('layout.companies_count', 60, fn () => \App\Models\Company::count());
                $peopleCount    = Cache::remember('layout.people_count',    60, fn () => Person::where('is_our_org', false)->count());

                $sidebarItems = [
                    ['label' => 'Dashboard',     'route' => 'dashboard',          'match' => ['dashboard'],         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>'],
                    ['label' => 'Companies',     'route' => 'companies.index',    'match' => ['companies.*'],       'count' => $companiesCount, 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>'],
                    ['label' => 'People',        'route' => 'people.index',       'match' => ['people.*'],          'count' => $peopleCount,    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>'],
                    ['label' => 'Conversations', 'route' => 'conversations.index','match' => ['conversations.*'],   'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>'],
                    ['label' => 'Activity',      'route' => 'activity.index',     'match' => ['activity.*'],        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 10V3L4 14h7v7l9-11h-7z"/>'],
                ];

                foreach ($sidebarItems as &$item) {
                    $item['active'] = request()->routeIs($item['match']);
                }
                unset($item);
            }

            $mainMargin = ($isConfigRoute && $onMapping && $mappingSystems->isNotEmpty()) ? '24rem' : '13rem';

            $view->with(compact(
                'hasServers', 'mappingSystems',
                'configNeedsAttention', 'serverNeedsAttention', 'serverBadCount',
                'mappingNeedsAttention', 'mappingUnhealthySystems',
                'setupStatus',
                'disabledMsg', 'isConfigRoute', 'topSections',
                'onMapping', 'currentMapping', 'taActive', 'segActive', 'saActive',
                'syncItems', 'drItems', 'sidebarItems', 'mainMargin'
            ));
        });

        View::composer('synchronizer.*', function (\Illuminate\View\View $view) {
            $view->with('typeColors', [
                'whmcs'       => ['bg' => 'rgba(88,166,255,.1)',  'color' => '#388bfd', 'border' => 'rgba(88,166,255,.25)'],
                'gmail'       => ['bg' => 'rgba(248,81,73,.1)',   'color' => '#f85149', 'border' => 'rgba(248,81,73,.25)'],
                'imap'        => ['bg' => 'rgba(63,185,80,.1)',   'color' => '#3fb950', 'border' => 'rgba(63,185,80,.25)'],
                'metricscube' => ['bg' => 'rgba(139,92,246,.1)',  'color' => '#7c3aed', 'border' => 'rgba(139,92,246,.25)'],
                'discord'     => ['bg' => 'rgba(88,101,242,.12)', 'color' => '#5865f2', 'border' => 'rgba(88,101,242,.3)'],
                'slack'       => ['bg' => 'rgba(74,21,75,.1)',    'color' => '#e01e5a', 'border' => 'rgba(224,30,90,.3)'],
            ]);
            $view->with('statusColors', [
                'completed' => ['color' => '#3fb950', 'bg' => 'rgba(63,185,80,.1)',  'border' => 'rgba(63,185,80,.25)'],
                'running'   => ['color' => '#388bfd', 'bg' => 'rgba(88,166,255,.1)', 'border' => 'rgba(88,166,255,.25)'],
                'pending'   => ['color' => '#b45309', 'bg' => 'rgba(251,191,36,.12)', 'border' => 'rgba(251,191,36,.4)'],
                'failed'    => ['color' => '#f85149', 'bg' => 'rgba(248,81,73,.1)',  'border' => 'rgba(248,81,73,.25)'],
            ]);
        });

        View::composer('conversations.partials.messages', function (\Illuminate\View\View $view) {
            $data = $view->getData();
            $conversation = $data['conversation'] ?? null;
            if ($conversation) {
                $view->with('channelCfg', $conversation->channelConfig());
                $view->with('isSlack', in_array($conversation->channel_type, ['slack', 'discord']));
                $view->with('isEmail', $conversation->channel_type === 'email');
                $view->with('isTicket', $conversation->channel_type === 'ticket');
            }
            $view->with('replies', $data['replies'] ?? collect());
            $view->with('discordMentionMap', $data['discordMentionMap'] ?? []);
        });

        View::composer('partials.activity-stats', function (\Illuminate\View\View $view) {
            $view->with('dotColors', [
                'payment'       => 'bg-green-400',
                'renewal'       => 'bg-blue-400',
                'cancellation'  => 'bg-red-400',
                'ticket'        => 'bg-yellow-400',
                'note'          => 'bg-gray-400',
                'status_change' => 'bg-slate-300',
                'campaign_run'  => 'bg-slate-300',
                'followup'      => 'bg-slate-300',
            ]);
            $view->with('channelDot', [
                'email'   => 'bg-purple-400',
                'ticket'  => 'bg-yellow-400',
                'discord' => 'bg-indigo-400',
                'slack'   => 'bg-green-400',
            ]);
        });
    }
}
