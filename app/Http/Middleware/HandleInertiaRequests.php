<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\AiCredential;
use App\Models\AiModelConfig;
use App\Models\Identity;
use App\Models\Person;
use App\Models\SmartNote;
use App\Models\SynchronizerServer;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        $shared = [
            'auth' => [
                'user' => $user ? [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ] : null,
                'permissions' => $user ? [
                    'browse_data'   => $user->hasPermission('browse_data'),
                    'data_write'    => $user->hasPermission('data_write'),
                    'notes_write'   => $user->hasPermission('notes_write'),
                    'analyse'       => $user->hasPermission('analyse'),
                    'configuration' => $user->hasPermission('configuration'),
                ] : null,
            ],
            'flash' => [
                'success'       => $request->session()->get('success'),
                'error'         => $request->session()->get('error'),
                'api_key_plain' => $request->session()->get('api_key_plain'),
            ],
        ];

        // Only compute layout data for authenticated users on non-API routes
        if ($user && !$request->is('api/*') && !$request->is('mcp')) {
            $shared['layout'] = $this->layoutData($request, $user);
        }

        return array_merge(parent::share($request), $shared);
    }

    private function layoutData(Request $request, $user): array
    {
        $hasServers = Cache::remember('layout.has_servers', 60, fn () => SynchronizerServer::exists());

        // Server health
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

        // Mapping health
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

        $mappingNeedsAttention = !empty($mappingUnhealthySystems);

        // Setup assistant status
        $setupStatus = Cache::remember('layout.setup_status', 60, function () use ($hasServers) {
            if (!$hasServers) return 'active';
            $hasData = Account::exists();
            if (!$hasData) return 'active';

            $accountSystems = Account::select('system_type', 'system_slug',
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(CASE WHEN company_id IS NULL THEN 1 END) as unlinked')
            )->groupBy('system_type', 'system_slug')->get();

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

            $hasOrgContacts = Person::where('is_our_org', true)->exists()
                           || Identity::where('is_team_member', true)->exists();
            if (!$hasOrgContacts) return 'active';

            if ($allSystems->isNotEmpty() && $worstRatio < 0.8) return 'partially_active';
            return 'completed';
        });

        $configNeedsAttention = $serverNeedsAttention || $mappingNeedsAttention || $setupStatus === 'active';

        $hasAiCredentials = Cache::remember('layout.has_ai_credentials', 60, fn () => AiCredential::exists());
        $analyseEnabled = Cache::remember('layout.analyse_enabled', 60, fn () =>
            AiModelConfig::where('action_type', 'analyze')
                ->whereNotNull('credential_id')
                ->whereNotNull('model_name')
                ->exists()
        );

        // Determine current section
        $isConfigRoute = $request->routeIs(
            'synchronizer.*', 'data-relations.*', 'our-company.*',
            'filtering.*', 'segmentation.*', 'configuration.*', 'team-access.*',
            'setup-assistant.*', 'smart-notes.config.*',
            'ai-config.*', 'ai-credentials.*', 'ai-model-configs.*', 'ai-costs.*',
            'mcp-server.*', 'mcp-log.*', 'company-analysis.config.*'
        );
        $isAnalyseRoute = $request->routeIs('analyze.*');
        $isBrowseRoute = !$isConfigRoute && !$isAnalyseRoute;

        $section = $isAnalyseRoute ? 'analyze' : ($isConfigRoute ? 'configuration' : 'browse_data');

        // Top sections
        $topSections = [
            [
                'label'       => 'Browse Data',
                'href'        => route('dashboard'),
                'isActive'    => $isBrowseRoute,
                'disabled'    => !$hasServers,
                'disabledMsg' => 'Configure a synchronizer server first',
                'permKey'     => 'browse_data',
                'type'        => 'normal',
                'dot'         => false,
            ],
            [
                'label'       => 'Analyze',
                'href'        => route('analyze.index'),
                'isActive'    => $isAnalyseRoute,
                'disabled'    => !$analyseEnabled,
                'disabledMsg' => 'Configure an AI credential and assign a model for Analyze Chat first',
                'permKey'     => 'analyse',
                'type'        => 'ai',
                'dot'         => false,
            ],
            [
                'label'       => 'Configuration',
                'href'        => route('setup-assistant.index'),
                'isActive'    => $isConfigRoute,
                'disabled'    => false,
                'disabledMsg' => null,
                'permKey'     => 'configuration',
                'type'        => 'config',
                'dot'         => $configNeedsAttention,
            ],
        ];

        // Sidebar items for current section
        $sidebarItems = match ($section) {
            'browse_data' => $this->browseDataSidebar($request, $user),
            'configuration' => $this->configSidebar($request, $hasServers, $serverNeedsAttention, $mappingNeedsAttention, $hasAiCredentials, $setupStatus),
            default => [],
        };

        // Mapping sub-sidebar (only on data-relations routes)
        $mappingSystems = [];
        if ($isConfigRoute && $request->routeIs('data-relations.*', 'our-company.*', 'filtering.*')) {
            $mappingSystems = Cache::remember('layout.mapping_systems', 30, function () {
                $identityToSystem = [
                    'email'        => 'imap',
                    'slack_user'   => 'slack',
                    'discord_user' => 'discord',
                ];

                $accountSystems = Account::select('system_type', 'system_slug')
                    ->whereNotNull('system_type')->whereNotNull('system_slug')
                    ->distinct()->orderBy('system_type')->orderBy('system_slug')->get()
                    ->map(fn ($r) => ['system_type' => $r->system_type, 'system_slug' => $r->system_slug]);

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
                    ->map(fn ($r) => [
                        'system_type' => $identityToSystem[$r->type] ?? $r->type,
                        'system_slug' => $r->system_slug,
                    ]);

                return $accountSystems->concat($identSystems)
                    ->unique(fn ($s) => $s['system_type'].'/'.$s['system_slug'])
                    ->sortBy(['system_type', 'system_slug'])
                    ->values()
                    ->toArray();
            });
        }

        $onMapping = $request->routeIs('data-relations.mapping', 'configuration.mapping');
        $currentMapping = ($request->route('systemType') && $request->route('systemSlug'))
            ? $request->route('systemType').'/'.$request->route('systemSlug')
            : null;

        return [
            'section'               => $section,
            'topSections'           => $topSections,
            'sidebarItems'          => $sidebarItems,
            'setupStatus'           => $setupStatus,
            'hasAiCredentials'      => $hasAiCredentials,
            'analyseEnabled'        => $analyseEnabled,
            'configNeedsAttention'  => $configNeedsAttention,
            'serverNeedsAttention'  => $serverNeedsAttention,
            'serverBadCount'        => $serverBadCount,
            'mappingNeedsAttention' => $mappingNeedsAttention,
            'mappingUnhealthySystems' => $mappingUnhealthySystems,
            'mappingSystems'        => $mappingSystems,
            'onMapping'             => $onMapping,
            'currentMapping'        => $currentMapping,
        ];
    }

    private function browseDataSidebar(Request $request, $user): array
    {
        if (!$user->hasPermission('browse_data')) return [];

        $companiesCount = Cache::remember('layout.companies_count', 60, fn () => \App\Models\Company::notMerged()->count());
        $peopleCount    = Cache::remember('layout.people_count', 60, fn () => Person::notMerged()->where('is_our_org', false)->count());

        $smartNotesEnabled      = Cache::remember('layout.smart_notes_enabled', 60, fn () => (bool) SystemSetting::get('smart_notes_enabled', false));
        $smartNotesUnrecognized = $smartNotesEnabled ? Cache::remember('layout.smart_notes_unrecognized', 30, fn () => SmartNote::unrecognized()->count()) : 0;

        $items = [
            ['label' => 'Dashboard',     'href' => route('dashboard'),           'active' => $request->routeIs('dashboard'),         'icon' => 'dashboard',      'count' => null],
            ['label' => 'Companies',     'href' => route('companies.index'),     'active' => $request->routeIs('companies.*'),       'icon' => 'companies',      'count' => $companiesCount],
            ['label' => 'People',        'href' => route('people.index'),        'active' => $request->routeIs('people.*'),          'icon' => 'people',         'count' => $peopleCount],
            ['label' => 'Conversations', 'href' => route('conversations.index'), 'active' => $request->routeIs('conversations.*'),   'icon' => 'conversations',  'count' => null],
            ['label' => 'Activity',      'href' => route('activity.index'),      'active' => $request->routeIs('activity.*'),        'icon' => 'activity',       'count' => null],
            [
                'label'       => 'Smart Notes',
                'href'        => route('smart-notes.index'),
                'active'      => $request->routeIs('smart-notes.index', 'smart-notes.recognize', 'smart-notes.save-recognition'),
                'icon'        => 'smart_notes',
                'count'       => $smartNotesUnrecognized ?: null,
                'disabled'    => !$smartNotesEnabled,
                'disabledMsg' => 'Enable Smart Notes in Configuration → Smart Notes',
                'ai'          => true,
            ],
        ];

        return $items;
    }

    private function configSidebar(Request $request, bool $hasServers, bool $serverNeedsAttention, bool $mappingNeedsAttention, bool $hasAiCredentials, string $setupStatus): array
    {
        return [
            'general' => [
                [
                    'label'  => 'Setup Assistant',
                    'href'   => route('setup-assistant.index'),
                    'active' => $request->routeIs('setup-assistant.*'),
                    'icon'   => 'setup_assistant',
                    'dot'    => match ($setupStatus) { 'active' => 'red', 'partially_active' => 'amber', 'completed' => 'green', default => null },
                ],
                [
                    'label'  => 'Team Access',
                    'href'   => route('team-access.index'),
                    'active' => $request->routeIs('team-access.*'),
                    'icon'   => 'team_access',
                ],
            ],
            'synchronization' => [
                [
                    'label'    => 'Connections',
                    'href'     => route('synchronizer.index'),
                    'active'   => $request->routeIs('synchronizer.index', 'synchronizer.connections.*', 'synchronizer.runs*', 'synchronizer.kill-all', 'synchronizer.run-all'),
                    'icon'     => 'connections',
                    'disabled' => !$hasServers,
                ],
                [
                    'label'  => 'Synchronizer Servers',
                    'href'   => route('synchronizer.servers.index'),
                    'active' => $request->routeIs('synchronizer.servers.*', 'synchronizer.wizard.*'),
                    'icon'   => 'servers',
                    'dot'    => $serverNeedsAttention ? 'red' : null,
                ],
                [
                    'label'  => 'Smart Notes',
                    'href'   => route('smart-notes.config.index'),
                    'active' => $request->routeIs('smart-notes.config.*'),
                    'icon'   => 'smart_notes_config',
                ],
            ],
            'data_relations' => [
                [
                    'label'  => 'Mapping',
                    'href'   => route('configuration.mapping'),
                    'active' => $request->routeIs('data-relations.index', 'configuration.mapping', 'data-relations.mapping'),
                    'icon'   => 'mapping',
                    'dot'    => $mappingNeedsAttention ? 'red' : null,
                ],
                [
                    'label'  => 'Filtering',
                    'href'   => route('filtering.index'),
                    'active' => $request->routeIs('filtering.*'),
                    'icon'   => 'filtering',
                ],
                [
                    'label'  => 'Our Organization',
                    'href'   => route('our-company.index'),
                    'active' => $request->routeIs('our-company.*'),
                    'icon'   => 'our_org',
                ],
            ],
            'ai_functionality' => [
                [
                    'label'  => 'Connect AI',
                    'href'   => route('ai-config.index'),
                    'active' => $request->routeIs('ai-config.*', 'ai-credentials.*', 'ai-model-configs.*'),
                    'icon'   => 'connect_ai',
                ],
                [
                    'label'       => 'Company Analysis',
                    'href'        => $hasAiCredentials ? route('company-analysis.config.index') : '#',
                    'active'      => $request->routeIs('company-analysis.config.*'),
                    'icon'        => 'company_analysis',
                    'disabled'    => !$hasAiCredentials,
                    'disabledMsg' => !$hasAiCredentials ? 'Add an AI credential first' : null,
                ],
                [
                    'label'       => 'AI Costs',
                    'href'        => route('ai-costs.index'),
                    'active'      => $request->routeIs('ai-costs.*'),
                    'icon'        => 'ai_costs',
                    'disabled'    => !$hasAiCredentials,
                    'disabledMsg' => 'Add an AI credential first',
                ],
                [
                    'label'  => 'MCP Server',
                    'href'   => route('mcp-server.index'),
                    'active' => $request->routeIs('mcp-server.*', 'mcp-log.*'),
                    'icon'   => 'mcp_server',
                ],
            ],
            'segmentation' => [
                [
                    'label'  => 'Segmentation',
                    'href'   => route('segmentation.index'),
                    'active' => $request->routeIs('segmentation.*'),
                    'icon'   => 'segmentation',
                ],
            ],
        ];
    }
}
