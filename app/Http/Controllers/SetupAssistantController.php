<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Identity;
use App\Models\Person;
use App\Models\SynchronizerServer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class SetupAssistantController extends Controller
{
    public function index()
    {
        $items = $this->buildChecklist();

        $statusConfig = [
            'active' => [
                'label'     => 'Action required',
                'badge'     => 'bg-red-100 text-red-700 border border-red-200',
                'cardClass' => 'bg-red-50 border border-red-200 border-l-red-400',
                'iconColor' => 'text-red-400',
                'icon'      => '<circle cx="12" cy="12" r="9" stroke-width="1.75"/><line x1="12" y1="8" x2="12" y2="12" stroke-width="1.75" stroke-linecap="round"/><circle cx="12" cy="16" r="0.75" fill="currentColor" stroke="none"/>',
            ],
            'partially_active' => [
                'label'     => 'Partially done',
                'badge'     => 'bg-amber-100 text-amber-700 border border-amber-200',
                'cardClass' => 'bg-amber-50 border border-amber-200 border-l-amber-400',
                'iconColor' => 'text-amber-400',
                'icon'      => '<circle cx="12" cy="12" r="9" stroke-width="1.75"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 12h8"/>',
            ],
            'disabled' => [
                'label'     => 'Waiting on previous step',
                'badge'     => 'bg-gray-100 text-gray-400 border border-gray-200',
                'cardClass' => 'bg-gray-50 border border-gray-200 border-l-gray-300',
                'iconColor' => 'text-gray-300',
                'icon'      => '<circle cx="12" cy="12" r="9" stroke-width="1.75"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6"/>',
            ],
            'completed' => [
                'label'     => 'Completed',
                'badge'     => 'bg-green-100 text-green-700 border border-green-200',
                'cardClass' => 'bg-green-50 border border-green-200 border-l-green-400',
                'iconColor' => 'text-green-500',
                'icon'      => '<circle cx="12" cy="12" r="9" stroke-width="1.75"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8.5 12.5l2.5 2.5 4.5-5"/>',
            ],
        ];

        // Merge display config into each item so view needs no lookups
        $items     = array_map(fn ($i) => array_merge($i, $statusConfig[$i['status']]), $items);
        $attention = collect($items)->whereNotIn('status', ['completed'])->values();
        $completed = collect($items)->where('status', 'completed')->values();

        return Inertia::render('SetupAssistant', [
            'attention' => $attention,
            'completed' => $completed,
        ]);
    }

    private function buildChecklist(): array
    {
        $hasServer = SynchronizerServer::exists();
        $hasData   = $hasServer && Account::exists();

        $items = [];

        // 1. System up to date — always completed for now
        $items[] = [
            'key'          => 'system_up_to_date',
            'name'         => 'System Up to Date',
            'description'  => 'Confirms you are running the latest version of Contact Monitor. Automated version checking will be added in a future release.',
            'status'       => 'completed',
            'action_label' => null,
            'action_href'  => null,
        ];

        // 2. Add connector server
        $items[] = [
            'key'          => 'connector_server',
            'name'         => 'Add Connector Server',
            'description'  => 'A connector server polls your external systems and pushes data into Contact Monitor. Without at least one registered server, no data can flow into the system.',
            'status'       => $hasServer ? 'completed' : 'active',
            'action_label' => 'Manage Servers',
            'action_href'  => route('synchronizer.servers.index'),
        ];

        // 3. Configure connections
        $items[] = [
            'key'          => 'configure_connections',
            'name'         => 'Configure Connections',
            'description'  => 'Each connection tells the connector server which external account to sync — a WHMCS instance, Slack workspace, Discord server, or mailbox. You need at least one active connection before any data will be imported.',
            'status'       => $hasServer ? $this->fetchConnectionsStatus() : 'disabled',
            'action_label' => 'Configure Connections',
            'action_href'  => $hasServer ? route('synchronizer.index') : null,
        ];

        // 4. Configure mapping
        $items[] = [
            'key'          => 'configure_mapping',
            'name'         => 'Configure Mapping',
            'description'  => 'Links imported accounts and identities to companies and people. Without mapping, conversations cannot be attributed to the correct clients. Target: 80% linked per connection — below 50% is critical.',
            'status'       => $hasData ? $this->computeMappingStatus() : 'disabled',
            'action_label' => 'Go to Mapping',
            'action_href'  => $hasData ? route('configuration.mapping') : null,
        ];

        // 5. Set your organization contacts
        $hasOrgContacts = $hasData && (
            Person::notMerged()->where('is_our_org', true)->exists() ||
            Identity::where('is_team_member', true)->exists()
        );
        $items[] = [
            'key'          => 'org_contacts',
            'name'         => 'Set Your Organization Contacts',
            'description'  => 'Mark which people belong to your team so their activity is excluded from customer timelines and statistics. You can mark people individually or configure team email domains for automatic detection.',
            'status'       => !$hasData ? 'disabled' : ($hasOrgContacts ? 'completed' : 'active'),
            'action_label' => 'Our Organization',
            'action_href'  => $hasData ? route('our-company.index') : null,
        ];

        return $items;
    }

    private function fetchConnectionsStatus(): string
    {
        try {
            $server = SynchronizerServer::first();
            if (!$server) return 'active';

            $url = preg_replace('#^(https?://)(?:localhost|127\.0\.0\.1)#', '$1host.docker.internal', rtrim($server->url, '/'));
            $response = Http::withToken($server->api_token)
                ->baseUrl($url)
                ->timeout(5)
                ->acceptJson()
                ->get('/api/connections');

            if ($response->successful()) {
                $connections = $response->json('connections', []);
                return count($connections) > 0 ? 'completed' : 'active';
            }
        } catch (\Throwable) {
            // server unreachable — fall back to DB proxy
        }

        // Fallback: if accounts exist, data was ingested, meaning connections were configured
        return Account::exists() ? 'completed' : 'active';
    }

    private function computeMappingStatus(): string
    {
        // Accounts linked to companies
        $accountSystems = Account::select(
            'system_type', 'system_slug',
            DB::raw('COUNT(*) as total'),
            DB::raw('COUNT(CASE WHEN company_id IS NULL THEN 1 END) as unlinked')
        )->groupBy('system_type', 'system_slug')->get();

        // Identities linked to people (excluding bots)
        $identSystems = Identity::select(
            'system_slug', 'type',
            DB::raw('COUNT(*) as total'),
            DB::raw('COUNT(CASE WHEN person_id IS NULL THEN 1 END) as unlinked')
        )->where('is_bot', false)->groupBy('system_slug', 'type')->get();

        if ($accountSystems->isEmpty() && $identSystems->isEmpty()) return 'completed';

        $worstRatio = 1.0;

        foreach ($accountSystems as $sys) {
            if ($sys->total === 0) continue;
            $ratio = ($sys->total - $sys->unlinked) / $sys->total;
            $worstRatio = min($worstRatio, $ratio);
        }

        foreach ($identSystems as $sys) {
            if ($sys->total === 0) continue;
            $ratio = ($sys->total - $sys->unlinked) / $sys->total;
            $worstRatio = min($worstRatio, $ratio);
        }

        if ($worstRatio < 0.5) return 'active';
        if ($worstRatio < 0.8) return 'partially_active';
        return 'completed';
    }
}
