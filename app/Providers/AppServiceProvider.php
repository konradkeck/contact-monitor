<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\AiCredential;
use App\Models\AiModelConfig;
use App\Models\Identity;
use App\Models\Person;
use App\Models\SmartNote;
use App\Models\SynchronizerServer;
use App\Models\SystemSetting;
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
        // ── On localhost, append APP_PORT to app.url ──────────────────────────
        $port = env('APP_PORT');
        $url  = rtrim(config('app.url'), '/');
        if ($port && preg_match('#^https?://(localhost|127\.0\.0\.1)$#', $url)) {
            config(['app.url' => $url . ':' . $port]);
            url()->forceRootUrl($url . ':' . $port);
        }

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
                'setup-assistant.*', 'smart-notes.config.*',
                'ai-config.*', 'ai-credentials.*', 'ai-model-configs.*', 'ai-costs.*',
                'mcp-server.*', 'mcp-log.*'
            );

            $topSections = [
                'Browse Data' => [
                    'route'    => 'dashboard',
                    'pattern'  => ['dashboard', 'companies.*', 'people.*', 'conversations.*', 'activity.*'],
                    'disabled' => !$hasServers,
                    'type'     => 'normal',
                ],
                'Analyze' => [
                    'route'    => 'analyse.index',
                    'pattern'  => ['analyse.*'],
                    'disabled' => !Cache::remember('layout.analyse_enabled', 60, fn () => AiModelConfig::where('action_type', 'analyze')->whereNotNull('credential_id')->whereNotNull('model_name')->exists()),
                    'type'     => 'ai',
                    'disabledMsg' => 'Configure an AI credential and assign a model for Analyse Chat first',
                ],
                'Configuration' => [
                    'route'    => 'setup-assistant.index',
                    'pattern'  => ['synchronizer.*', 'data-relations.*', 'our-company.*', 'filtering.*', 'segmentation.*', 'configuration.*', 'team-access.*', 'setup-assistant.*'],
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
            $aiActive  = false;
            $syncItems    = [];
            $drItems      = [];
            $aiItems      = [];
            $sidebarItems = [];

            if ($isConfigRoute) {
                $onMapping = request()->routeIs('data-relations.mapping', 'configuration.mapping');
                $currentMapping = (request()->route('systemType') && request()->route('systemSlug'))
                    ? request()->route('systemType').'/'.request()->route('systemSlug')
                    : null;

                $taActive  = request()->routeIs('team-access.*');
                $segActive = request()->routeIs('segmentation.*');
                $saActive  = request()->routeIs('setup-assistant.*');
                $aiActive  = request()->routeIs('ai-config.*', 'ai-credentials.*', 'ai-model-configs.*', 'ai-costs.*', 'mcp-server.*', 'mcp-log.*');

                $hasAiCredentials = Cache::remember('layout.has_ai_credentials', 60, fn () => AiCredential::exists());

                $aiItems = [
                    ['label' => 'Connect AI',        'route' => 'ai-config.index',   'match' => ['ai-config.*', 'ai-credentials.*', 'ai-model-configs.*'],
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>'],
                    ['label' => 'Company Analysis',  'route' => 'ai-config.index', 'match' => ['ai-company-analysis.*'],
                     'is_disabled' => true, 'disabledMsg' => !$hasAiCredentials ? 'Add an AI credential first' : 'Coming soon',
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>'],
                    ['label' => 'AI Costs',          'route' => 'ai-costs.index',    'match' => ['ai-costs.*'],
                     'is_disabled' => !$hasAiCredentials, 'disabledMsg' => 'Add an AI credential first',
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                    ['label' => 'MCP Server',        'route' => 'mcp-server.index',  'match' => ['mcp-server.*', 'mcp-log.*'],
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1 1 .03 2.7-1.31 2.7H4.11c-1.34 0-2.31-1.7-1.31-2.7L4.2 15.3"/>'],
                ];

                foreach ($aiItems as &$item) {
                    $item['active'] = !empty($item['match']) && request()->routeIs($item['match']);
                }
                unset($item);

                $syncItems = [
                    ['label' => 'Connections',          'route' => 'synchronizer.index',         'match' => ['synchronizer.index', 'synchronizer.connections.*', 'synchronizer.runs*', 'synchronizer.kill-all', 'synchronizer.run-all'], 'disabled' => !$hasServers, 'dot' => false,
                     'icon' => '<circle cx="6" cy="17" r="2.75" stroke-width="1.75"/><circle cx="20" cy="4" r="2" stroke-width="1.75"/><circle cx="20" cy="15" r="2" stroke-width="1.75"/><circle cx="10" cy="5" r="2" stroke-width="1.75"/><path stroke-linecap="round" stroke-width="1.75" d="M8 15L18.5 5.5M8 16.5L18.5 14.5M7.5 14.5L9.5 7"/>'],
                    ['label' => 'Synchronizer Servers', 'route' => 'synchronizer.servers.index', 'match' => ['synchronizer.servers.*', 'synchronizer.wizard.*'], 'disabled' => false, 'dot' => $serverNeedsAttention,
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>'],
                    ['label' => 'Smart Notes', 'route' => 'smart-notes.config.index', 'match' => ['smart-notes.config.*'], 'disabled' => false, 'dot' => false, 'ai' => false,
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7 8h10M7 12h6m-6 4h4M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>'],
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
                $companiesCount = Cache::remember('layout.companies_count', 60, fn () => \App\Models\Company::notMerged()->count());
                $peopleCount    = Cache::remember('layout.people_count',    60, fn () => Person::notMerged()->where('is_our_org', false)->count());

                $smartNotesEnabled       = Cache::remember('layout.smart_notes_enabled', 60, fn () => (bool) SystemSetting::get('smart_notes_enabled', false));
                $smartNotesUnrecognized  = $smartNotesEnabled ? Cache::remember('layout.smart_notes_unrecognized', 30, fn () => SmartNote::unrecognized()->count()) : 0;

                $sidebarItems = [
                    ['label' => 'Dashboard',     'route' => 'dashboard',          'match' => ['dashboard'],         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>'],
                    ['label' => 'Companies',     'route' => 'companies.index',    'match' => ['companies.*'],       'count' => $companiesCount, 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>'],
                    ['label' => 'People',        'route' => 'people.index',       'match' => ['people.*'],          'count' => $peopleCount,    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>'],
                    ['label' => 'Conversations', 'route' => 'conversations.index','match' => ['conversations.*'],   'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>'],
                    ['label' => 'Activity',      'route' => 'activity.index',     'match' => ['activity.*'],        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 10V3L4 14h7v7l9-11h-7z"/>'],
                    ['label' => 'Smart Notes',   'route' => 'smart-notes.index',  'match' => ['smart-notes.index', 'smart-notes.recognize', 'smart-notes.save-recognition'],
                     'disabled' => !$smartNotesEnabled,
                     'disabledMsg' => 'Enable Smart Notes in Configuration → Smart Notes',
                     'count' => $smartNotesUnrecognized ?: null,
                     'ai' => true,
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>'],
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
                'onMapping', 'currentMapping', 'taActive', 'segActive', 'saActive', 'aiActive',
                'syncItems', 'drItems', 'aiItems', 'sidebarItems', 'mainMargin'
            ));
        });

        View::composer('conversations.partials.messages', function (\Illuminate\View\View $view) {
            $data = $view->getData();
            $conversation = $data['conversation'] ?? null;
            if ($conversation) {
                $view->with('channelCfg', $conversation->channelConfig());
                $view->with('isSlack', in_array($conversation->channel_type, ['slack', 'discord']));
                $view->with('isEmail', $conversation->channel_type === 'email');
                $view->with('isTicket', $conversation->channel_type === 'ticket');
                $view->with('usesMarkdown',
                    ($conversation->channel_type === 'ticket' && $conversation->system_type === 'whmcs')
                    || $conversation->channel_type === 'discord'
                );
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
