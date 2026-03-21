<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsConvSubjectMap;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\BrandProduct;
use App\Models\Company;
use App\Models\CompanyAlias;
use App\Models\CompanyBrandStatus;
use App\Models\CompanyDomain;
use App\Models\Note;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyController extends Controller
{
    use BuildsConvSubjectMap;

    public function index(Request $request): View
    {
        $search = $request->get('q');
        $sort = $request->get('sort', 'updated_at');
        $dir = $request->get('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $tab = $request->get('tab', 'clients');
        if (! in_array($tab, ['clients', 'our_org'])) {
            $tab = 'clients';
        }

        $brandProducts = BrandProduct::orderBy('name')->get();
        $channelTypes = \App\Models\Conversation::distinct()->orderBy('channel_type')->pluck('channel_type');

        $allowedSorts = ['name', 'domain', 'contacts', 'updated_at', 'last_conv'];
        foreach ($brandProducts as $bp) {
            $allowedSorts[] = 'bp_score_'.$bp->id;
            $allowedSorts[] = 'bp_stage_'.$bp->id;
        }
        if (! in_array($sort, $allowedSorts)) {
            $sort = 'updated_at';
        }

        $query = Company::query()
            ->notMerged()
            ->when($tab === 'our_org', fn ($q) => $q->whereHas('people', fn ($p) => $p->where('is_our_org', true)))
            ->when($search, function ($q) use ($search) {
                $term = '%'.strtolower($search).'%';
                $q->where(function ($sub) use ($term) {
                    $sub->whereRaw('LOWER(companies.name) LIKE ?', [$term])
                        ->orWhereHas('aliases', fn ($a) => $a->whereRaw('LOWER(alias_normalized) LIKE ?', [$term]))
                        ->orWhereHas('domains', fn ($d) => $d->whereRaw('LOWER(domain) LIKE ?', [$term]));
                });
            })
            ->when($request->get('f_domain'), fn ($q, $v) => $q->whereHas('domains', fn ($d) => $d->whereRaw('LOWER(domain) LIKE ?', ['%'.strtolower($v).'%']))
            )
            ->when($request->get('f_people_min'), fn ($q, $v) => $q->has('people', '>=', (int) $v))
            ->when($request->get('f_conv_type'), fn ($q, $v) => $q->whereHas('conversations', fn ($c) => $c->where('channel_type', $v))
            )
            ->when($request->get('f_updated_from'), fn ($q, $v) => $q->whereDate('updated_at', '>=', $v))
            ->when($request->get('f_updated_to'), fn ($q, $v) => $q->whereDate('updated_at', '<=', $v))
            ->with([
                'domains',
                'aliases',
                'people.identities',
                'brandStatuses.brandProduct',
                'conversations' => fn ($q) => $q->orderByDesc('last_message_at'),
                'notes' => fn ($q) => $q->orderByDesc('created_at'),
            ]);

        // Brand product filters
        foreach ($brandProducts as $bp) {
            if ($stage = $request->get("f_bp_{$bp->id}_stage")) {
                $query->whereHas('brandStatuses', fn ($q) => $q->where('brand_product_id', $bp->id)->where('stage', $stage)
                );
            }
            if ($min = $request->get("f_bp_{$bp->id}_score_min")) {
                $query->whereHas('brandStatuses', fn ($q) => $q->where('brand_product_id', $bp->id)->where('evaluation_score', '>=', (int) $min)
                );
            }
            if ($max = $request->get("f_bp_{$bp->id}_score_max")) {
                $query->whereHas('brandStatuses', fn ($q) => $q->where('brand_product_id', $bp->id)->where('evaluation_score', '<=', (int) $max)
                );
            }
        }

        // Sorting
        match (true) {
            $sort === 'domain' => $query->orderByRaw(
                "(SELECT domain FROM company_domains WHERE company_id=companies.id AND is_primary=true LIMIT 1) {$dir} NULLS LAST"
            ),
            $sort === 'contacts' => $query->orderByRaw(
                "(SELECT COUNT(*) FROM company_person WHERE company_id=companies.id) {$dir}"
            ),
            $sort === 'last_conv' => $query->orderByRaw(
                "(SELECT MAX(last_message_at) FROM conversations WHERE company_id=companies.id) {$dir} NULLS LAST"
            ),
            str_starts_with($sort, 'bp_score_') => $query->orderByRaw(
                "(SELECT evaluation_score FROM company_brand_statuses WHERE company_id=companies.id AND brand_product_id=? LIMIT 1) {$dir} NULLS LAST",
                [(int) substr($sort, 9)]
            ),
            str_starts_with($sort, 'bp_stage_') => $query->orderByRaw(
                "(SELECT CASE stage WHEN 'lead' THEN 1 WHEN 'prospect' THEN 2 WHEN 'trial' THEN 3 WHEN 'active' THEN 4 WHEN 'churned' THEN 5 ELSE 6 END FROM company_brand_statuses WHERE company_id=companies.id AND brand_product_id=? LIMIT 1) {$dir} NULLS LAST",
                [(int) substr($sort, 9)]
            ),
            default => $query->orderBy($sort, $dir),
        };

        // ── Filtered companies ─────────────────────────────────
        $filterDomains = SystemSetting::get('filter_domains', []);
        $filterEmails = SystemSetting::get('filter_emails', []);
        $filterContacts = DB::table('filter_contacts')->pluck('person_id')->all();

        $filteredIds = [];
        $filteredReasons = [];

        // By domain: companies whose any domain matches a filter_domains entry
        if (! empty($filterDomains)) {
            $domainMatches = DB::table('company_domains')
                ->whereIn(DB::raw('LOWER(domain)'), array_map('strtolower', $filterDomains))
                ->select('company_id', 'domain')
                ->get();
            foreach ($domainMatches as $row) {
                $filteredIds[] = $row->company_id;
                $filteredReasons[$row->company_id] = "Domain: {$row->domain}";
            }
        }

        // By filter_contacts: companies linked via company_person to any filtered person
        if (! empty($filterContacts)) {
            $contactCompanies = DB::table('company_person')
                ->whereIn('person_id', $filterContacts)
                ->pluck('company_id')
                ->unique()
                ->all();
            foreach ($contactCompanies as $companyId) {
                if (! isset($filteredReasons[$companyId])) {
                    $filteredIds[] = $companyId;
                    $filteredReasons[$companyId] = 'Filtered contact';
                }
            }
        }

        $filteredIds = array_unique($filteredIds);
        $filteredCount = count($filteredIds);
        $showFiltered = (bool) $request->get('show_filtered');

        if ($showFiltered) {
            if (empty($filteredIds)) {
                $query->whereRaw('1=0');
            } else {
                $query->whereIn('companies.id', $filteredIds);
            }
        } else {
            $query->whereNotIn('companies.id', $filteredIds ?: [-1]);
        }

        $companies = $query->paginate(20)->withQueryString();

        // Pre-compute per-company display data
        foreach ($companies as $company) {
            $company->_primaryDomain = $company->domains->firstWhere('is_primary', true) ?? $company->domains->first();
            $company->_extraDomains  = $company->domains->filter(fn ($d) => $d->id !== $company->_primaryDomain?->id);
            $company->_contacts      = $company->people->filter(fn ($p) => ! $p->is_our_org && is_null($p->merged_into_id));
            $totalContacts           = $company->_contacts->count();
            $company->_visiblePeople = $totalContacts > 5 ? $company->_contacts->take(4) : $company->_contacts;
            $company->_extraPeople   = $totalContacts > 5 ? $totalContacts - 4 : 0;
            $company->_convChannels  = $company->conversations->unique('channel_type')->values();
        }

        $scoreColorMap = [
            1  => '#ef4444', 2  => '#f97316', 3  => '#f59e0b', 4  => '#eab308',
            5  => '#84cc16', 6  => '#4ade80', 7  => '#22c55e', 8  => '#16a34a',
            9  => '#15803d', 10 => '#166534',
        ];

        $convTypeMap = [
            'email'    => ['cls' => 'bg-sky-100 text-sky-700',         'icon' => 'mail'],
            'mail'     => ['cls' => 'bg-sky-100 text-sky-700',         'icon' => 'mail'],
            'ticket'   => ['cls' => 'bg-amber-100 text-amber-700',     'icon' => 'ticket'],
            'support'  => ['cls' => 'bg-amber-100 text-amber-700',     'icon' => 'ticket'],
            'discord'  => ['cls' => 'text-white', 'style' => 'background:#5865F2', 'icon' => 'discord'],
            'slack'    => ['cls' => 'text-white', 'style' => 'background:#4A154B', 'icon' => 'slack'],
            'chat'     => ['cls' => 'bg-purple-100 text-purple-700',   'icon' => 'chat'],
            'call'     => ['cls' => 'bg-orange-100 text-orange-700',   'icon' => 'phone'],
            'sms'      => ['cls' => 'bg-teal-100 text-teal-700',       'icon' => 'phone'],
            'whatsapp' => ['cls' => 'bg-green-100 text-green-700',     'icon' => 'chat'],
        ];

        $convIcons = [
            'mail'   => ['stroke' => true,  'd' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            'ticket' => ['stroke' => true,  'd' => 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z'],
            'chat'   => ['stroke' => true,  'd' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
            'phone'  => ['stroke' => true,  'd' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
            'slack'  => ['stroke' => false, 'd' => 'M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52zm1.271 0a2.527 2.527 0 0 1 2.521-2.52 2.527 2.527 0 0 1 2.521 2.52v6.313A2.528 2.528 0 0 1 8.834 24a2.528 2.528 0 0 1-2.521-2.522v-6.313zM8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834 0a2.528 2.528 0 0 1 2.521 2.522v2.52H8.834zm0 1.271a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H2.522A2.528 2.528 0 0 1 0 8.834a2.528 2.528 0 0 1 2.522-2.521h6.312zm10.122 2.521a2.528 2.528 0 0 1 2.522-2.521A2.528 2.528 0 0 1 24 8.834a2.528 2.528 0 0 1-2.522 2.521h-2.522V8.834zm-1.268 0a2.528 2.528 0 0 1-2.523 2.521 2.527 2.527 0 0 1-2.52-2.521V2.522A2.527 2.527 0 0 1 15.165 0a2.528 2.528 0 0 1 2.523 2.522v6.312zm-2.523 10.122a2.528 2.528 0 0 1 2.523 2.522A2.528 2.528 0 0 1 15.165 24a2.527 2.527 0 0 1-2.52-2.522v-2.522h2.52zm0-1.268a2.527 2.527 0 0 1-2.52-2.523 2.526 2.526 0 0 1 2.52-2.52h6.313A2.527 2.527 0 0 1 24 15.165a2.528 2.528 0 0 1-2.522 2.523h-6.313z'],
            'discord'=> ['stroke' => false, 'd' => 'M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.01.04.028.054a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994.021-.041.001-.09-.041-.106a13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z'],
        ];

        $sortUrl = fn ($col) => route('companies.index', array_merge($request->query(), [
            'sort' => $col,
            'dir'  => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
        ]));
        $sortIcon = fn ($col) => $sort === $col
            ? ($dir === 'asc' ? ' ↑' : ' ↓')
            : '';

        $fmtDate = fn ($dt) => $dt?->format('D, j M Y \a\t H:i') ?? '';

        $activeFilterCount = collect(['f_domain', 'f_people_min', 'f_conv_type', 'f_updated_from', 'f_updated_to'])
            ->filter(fn ($k) => (string) $request->get($k) !== '')
            ->count();
        foreach ($brandProducts as $bp) {
            foreach (["f_bp_{$bp->id}_stage", "f_bp_{$bp->id}_score_min", "f_bp_{$bp->id}_score_max"] as $k) {
                if ((string) $request->get($k) !== '') {
                    $activeFilterCount++;
                }
            }
        }
        $hasFilters = $search || $activeFilterCount > 0;

        $tabCounts = [
            'clients' => Company::notMerged()->count(),
            'our_org' => Company::notMerged()->whereHas('people', fn ($p) => $p->where('is_our_org', true))->count(),
        ];

        return view('companies.index', compact(
            'companies', 'search', 'sort', 'dir', 'brandProducts', 'channelTypes',
            'filteredCount', 'filteredReasons', 'showFiltered',
            'scoreColorMap', 'convTypeMap', 'convIcons',
            'sortUrl', 'sortIcon', 'fmtDate',
            'activeFilterCount', 'hasFilters', 'tab', 'tabCounts'
        ));
    }

    public function create(): View
    {
        return view('companies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'primary_domain' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:100',
        ]);

        $company = Company::create($data);

        if ($company->primary_domain) {
            CompanyDomain::create([
                'company_id' => $company->id,
                'domain' => $company->primary_domain,
                'is_primary' => true,
            ]);
        }

        AuditLog::record('created', $company, "Created company: {$company->name}", ['name' => $company->name]);

        return redirect()->route('companies.show', $company)->with('success', 'Company created.');
    }

    public function show(Request $request, Company $company): View
    {
        // Eager load everything — no N+1
        $company->load([
            'domains',
            'aliases',
            'accounts',
            'people.identities',
            'brandStatuses.brandProduct',
            'mergedInto',
            'mergedCompanies.domains',
            'mergedCompanies.accounts',
            'mergedCompanies.people',
        ]);

        $notes = Note::with('user')->whereHas('links', fn ($q) => $q->where('linkable_type', Company::class)->where('linkable_id', $company->id)
        )->orderByDesc('created_at')->limit(10)->get();

        // Include merged companies in conversation groups
        $allCompanyIds = collect([$company->id])
            ->merge($company->mergedCompanies->pluck('id'))
            ->unique()->values()->all();

        $allCompanyIdsPlaceholders = implode(',', array_fill(0, count($allCompanyIds), '?'));

        // Group conversations by (channel_type, system_slug) — one row per source
        $convGroups = collect(DB::select("
            SELECT DISTINCT ON (channel_type, system_slug)
                channel_type, system_slug, id AS last_conv_id,
                subject AS last_subject, last_message_at,
                COUNT(*) OVER (PARTITION BY channel_type, system_slug) AS conv_count
            FROM conversations
            WHERE company_id IN ({$allCompanyIdsPlaceholders})
            ORDER BY channel_type, system_slug, last_message_at DESC
        ", $allCompanyIds))->sortByDesc('last_message_at');

        $conversationCount = $convGroups->sum('conv_count');

        $filterDomains  = SystemSetting::get('filter_domains', []);
        $filterEmails   = SystemSetting::get('filter_emails', []);
        $filterSubjects = SystemSetting::get('filter_subjects', []);
        $filteredExtIds = $this->filteredConversationExtIds($filterDomains, $filterEmails, $filterSubjects);

        // First page of timeline
        $timelineQuery = $company->activities()
            ->with('person')
            ->orderByDesc('occurred_at');
        $this->excludeFilteredActivities($timelineQuery, $filterDomains, $filterEmails, $filterSubjects);
        $timelinePage = $timelineQuery->cursorPaginate(25);

        $convSubjectMap = $this->buildConvSubjectMap($timelinePage->items());
        $this->prepareTimelineDisplay($timelinePage->items(), $convSubjectMap);

        // Conversation systems for filter dropdown
        $convSystems = DB::table('activities')
            ->where('company_id', $company->id)
            ->where('type', 'conversation')
            ->whereRaw("meta_json->>'channel_type' IS NOT NULL")
            ->select(
                DB::raw("meta_json->>'channel_type' as channel_type"),
                DB::raw("meta_json->>'system_slug' as system_slug"),
                DB::raw("meta_json->>'system_type' as system_type")
            )
            ->distinct()->get()->sortBy('channel_type')->values();

        $filteredConvCount = empty($filteredExtIds) ? 0 : DB::table('conversations')
            ->where('company_id', $company->id)
            ->whereIn('external_thread_id', $filteredExtIds)
            ->count();

        $activityTypes = DB::table('activities')
            ->where('company_id', $company->id)
            ->where('type', '!=', 'conversation')
            ->distinct()->pluck('type')->sort()->values();

        $availableBrands = BrandProduct::orderBy('name')->get()
            ->reject(fn ($bp) => $company->brandStatuses->pluck('brand_product_id')->contains($bp->id));

        $backLink = $this->resolveBackLink($request);

        $primaryDomain = $company->domains->firstWhere('is_primary', true) ?? $company->domains->first();
        $otherDomains  = $company->domains->filter(fn ($d) => $d->id !== $primaryDomain?->id);
        $primaryAlias  = $company->aliases->firstWhere('is_primary', true);

        $allTypes = ['payment', 'renewal', 'cancellation', 'ticket', 'conversation', 'note', 'status_change', 'campaign_run', 'followup'];

        $scoreColorMap = [
            1  => '#ef4444', 2  => '#f97316', 3  => '#f59e0b', 4  => '#eab308',
            5  => '#84cc16', 6  => '#4ade80', 7  => '#22c55e', 8  => '#16a34a',
            9  => '#15803d', 10 => '#166534',
        ];

        $typeColors = [
            'payment'       => 'bg-green-400',
            'renewal'       => 'bg-blue-400',
            'cancellation'  => 'bg-red-500',
            'ticket'        => 'bg-yellow-400',
            'conversation'  => 'bg-purple-400',
            'note'          => 'bg-gray-400',
            'status_change' => 'bg-slate-300',
            'campaign_run'  => 'bg-slate-300',
            'followup'      => 'bg-slate-300',
        ];

        $contacts = $company->people->filter(fn ($p) => ! $p->is_our_org && is_null($p->merged_into_id));
        $mergedCompanies = $company->mergedCompanies;
        $mergedPrimaryDomains = $mergedCompanies->mapWithKeys(fn ($mc) => [
            $mc->id => $mc->domains->firstWhere('is_primary', true) ?? $mc->domains->first(),
        ]);

        // Group services by system_slug (each WHMCS instance = separate tab)
        // Include accounts from merged companies as well
        $serviceSystems = [];
        $allAccountCollections = collect([$company->accounts])->merge(
            $company->mergedCompanies->map(fn ($mc) => $mc->accounts)
        );
        foreach ($allAccountCollections as $accountCollection) {
            foreach ($accountCollection as $acc) {
                if (empty($acc->meta_json['services'])) {
                    continue;
                }
                $slug = $acc->system_slug;
                if (! isset($serviceSystems[$slug])) {
                    $serviceSystems[$slug] = ['system_type' => $acc->system_type, 'services' => []];
                }
                foreach ($acc->meta_json['services'] as $svc) {
                    $serviceSystems[$slug]['services'][] = $svc;
                }
            }
        }
        foreach ($serviceSystems as $slug => &$sys) {
            $svcs = $sys['services'];
            $sys['revenue'] = array_sum(array_column($svcs, 'total_revenue'));
            $sys['active']  = count(array_filter($svcs, fn ($s) => strtolower($s['status'] ?? '') === 'active'));
            $sys['total']   = count($svcs);
            usort($sys['services'], fn ($a, $b) => (strtolower($b['status'] ?? '') === 'active') <=> (strtolower($a['status'] ?? '') === 'active')
                ?: strcmp($a['product_name'] ?? '', $b['product_name'] ?? ''));
        }
        unset($sys);

        // Pre-compute integration widget data per service system
        $svcWidgets = [];
        foreach ($serviceSystems as $slug => $sys) {
            $svcIntegration = \App\Integrations\IntegrationRegistry::get($sys['system_type'] ?? '');
            $svcWidgets[$slug] = [
                'view' => $svcIntegration->servicesWidgetView(),
                'data' => $svcIntegration->prepareWidgetData($sys, $slug),
            ];
        }

        return view('companies.show', compact(
            'company',
            'notes',
            'convGroups',
            'conversationCount',
            'timelinePage',
            'convSubjectMap',
            'convSystems',
            'filteredConvCount',
            'activityTypes',
            'availableBrands',
            'backLink',
            'primaryDomain',
            'otherDomains',
            'primaryAlias',
            'allTypes',
            'scoreColorMap',
            'typeColors',
            'contacts',
            'serviceSystems',
            'svcWidgets',
            'mergedCompanies',
            'mergedPrimaryDomains',
        ));
    }

    public function timeline(Request $request, Company $company)
    {
        $filterDomains  = SystemSetting::get('filter_domains', []);
        $filterEmails   = SystemSetting::get('filter_emails', []);
        $filterSubjects = SystemSetting::get('filter_subjects', []);
        $filteredExtIds = $this->filteredConversationExtIds($filterDomains, $filterEmails, $filterSubjects);

        $query = $company->activities()
            ->with('person')
            ->orderByDesc('occurred_at');

        if ($request->boolean('is_filtered')) {
            $this->includeOnlyFilteredActivities($query, $filterDomains, $filterEmails, $filterSubjects);
        } else {
            $this->excludeFilteredActivities($query, $filterDomains, $filterEmails, $filterSubjects);
        }

        if ($types = $request->get('types')) {
            $query->whereIn('type', (array) $types);
        }
        if ($systems = $request->get('systems')) {
            $pairs = array_filter((array) $systems);
            if (! empty($pairs)) {
                $query->where(function ($q) use ($pairs) {
                    foreach ($pairs as $pair) {
                        [$ch, $slug] = array_pad(explode('|', $pair, 2), 2, '');
                        $q->orWhere(function ($sq) use ($ch, $slug) {
                            $sq->whereRaw("meta_json->>'channel_type' = ?", [$ch])
                                ->whereRaw("meta_json->>'system_slug' = ?", [$slug]);
                        });
                    }
                });
            }
        }
        if ($from = $request->get('from')) {
            $query->whereDate('occurred_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('occurred_at', '<=', $to);
        }

        $page = $query->cursorPaginate(25, ['*'], 'cursor', $request->get('cursor'));
        $convSubjectMap = $this->buildConvSubjectMap($page->items());
        $this->prepareTimelineDisplay($page->items(), $convSubjectMap);

        return view('companies.partials.timeline-items', [
            'activities' => $page->items(),
            'nextCursor' => $page->nextCursor()?->encode(),
            'convSubjectMap' => $convSubjectMap,
        ]);
    }

    public function edit(Company $company): View
    {
        return view('companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'primary_domain' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:100',
        ]);

        $company->update($data);
        AuditLog::record('updated', $company, "Updated company: {$company->name}", $data);

        return redirect()->route('companies.show', $company)->with('success', 'Company updated.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        AuditLog::record('deleted', $company, "Deleted company: {$company->name}");
        $company->delete();

        return redirect()->route('companies.index')->with('success', 'Company deleted.');
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $companies = Company::where('name', 'ilike', "%{$q}%")
            ->whereNull('merged_into_id')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        return response()->json($companies);
    }

    // ── Merge ───────────────────────────────────────────────────

    public function mergeModal(Request $request): View
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->get('ids', [])))));
        abort_if(count($ids) < 2, 400, 'Select at least 2 companies to merge.');

        $companies = Company::with(['domains', 'aliases', 'accounts', 'people', 'conversations'])
            ->whereIn('id', $ids)
            ->whereNull('merged_into_id')
            ->get();

        abort_if($companies->count() < 2, 400, 'Not enough valid (non-merged) companies selected.');

        return view('companies.merge-modal', compact('companies'));
    }

    public function merge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'primary_id'  => 'required|integer|exists:companies,id',
            'merge_ids'   => 'required|array|min:1',
            'merge_ids.*' => 'integer|exists:companies,id',
        ]);

        $primaryId = (int) $data['primary_id'];
        $mergeIds  = array_values(array_filter(array_map('intval', $data['merge_ids']), fn ($id) => $id !== $primaryId));

        abort_if(empty($mergeIds), 400, 'No companies to merge.');

        Company::whereIn('id', $mergeIds)->update(['merged_into_id' => $primaryId]);

        $primary = Company::find($primaryId);
        AuditLog::record('merged', $primary,
            "Merged companies [" . implode(', ', $mergeIds) . "] into {$primaryId} ({$primary->name})",
            ['primary_id' => $primaryId, 'merged_ids' => $mergeIds]
        );

        return response()->json(['ok' => true, 'redirect' => route('companies.show', $primaryId)]);
    }

    public function unmerge(Company $company): RedirectResponse
    {
        $company->update(['merged_into_id' => null]);
        AuditLog::record('unmerged', $company, "Unmerged company: {$company->name}");

        return back()->with('success', "{$company->name} has been unmerged.");
    }

    // ── Domains ────────────────────────────────────────────────

    public function storeDomain(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate(['domain' => 'required|string|max:255']);

        $company->domains()->create(['domain' => $data['domain'], 'is_primary' => false]);
        AuditLog::record('added_domain', $company, "Added domain {$data['domain']} to {$company->name}", $data);

        return back()->with('success', 'Domain added.');
    }

    public function destroyDomain(Company $company, CompanyDomain $domain): RedirectResponse
    {
        $domain->delete();

        return back()->with('success', 'Domain removed.');
    }

    public function setPrimaryDomain(Company $company, CompanyDomain $domain): RedirectResponse
    {
        $company->domains()->update(['is_primary' => false]);
        $domain->update(['is_primary' => true]);
        $company->update(['primary_domain' => $domain->domain]);

        return back()->with('success', 'Primary domain updated.');
    }

    // ── Aliases ────────────────────────────────────────────────

    public function storeAlias(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'alias' => 'required|string|max:255',
        ]);

        $company->aliases()->create($data);
        AuditLog::record('added_alias', $company, "Added alias \"{$data['alias']}\" to {$company->name}", $data);

        return back()->with('success', 'Alias added.');
    }

    public function setPrimaryAlias(Company $company, CompanyAlias $alias): RedirectResponse
    {
        $company->aliases()->update(['is_primary' => false]);
        $alias->update(['is_primary' => true]);

        return back()->with('success', 'Primary alias set.');
    }

    public function destroyAlias(Company $company, CompanyAlias $alias): RedirectResponse
    {
        $alias->delete();

        return back()->with('success', 'Alias removed.');
    }

    // ── Brand statuses ─────────────────────────────────────────

    public function storeBrandStatus(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'brand_product_id' => 'required|exists:brand_products,id',
            'stage' => 'required|string|max:100',
            'evaluation_score' => 'nullable|integer|min:1|max:10',
            'evaluation_notes' => 'nullable|string',
        ]);

        $company->brandStatuses()->create($data);

        return back()->with('success', 'Brand status added.');
    }

    // ── External Accounts ───────────────────────────────────────

    public function storeAccount(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'system_type' => 'required|string|max:100',
            'system_slug' => 'nullable|string|max:100',
            'external_id' => 'required|string|max:255',
        ]);

        $data['system_slug'] = $data['system_slug'] ?: 'default';

        try {
            $company->accounts()->create($data);
            AuditLog::record('added_account', $company, "Added {$data['system_type']} account to {$company->name}", $data);

            return back()->with('success', 'Account added.');
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return back()->withErrors(['external_id' => 'This account already exists.']);
        }
    }

    public function destroyAccount(Company $company, Account $account): RedirectResponse
    {
        AuditLog::record('removed_account', $company, "Removed {$account->system_type} account from {$company->name}");
        $account->delete();

        return back()->with('success', 'Account removed.');
    }

    public function updateBrandStatus(Request $request, Company $company, CompanyBrandStatus $status): RedirectResponse
    {
        $data = $request->validate([
            'stage' => 'required|string|max:100',
            'evaluation_score' => 'nullable|integer|min:1|max:10',
            'evaluation_notes' => 'nullable|string',
        ]);

        $status->update($data + ['last_evaluated_at' => now()]);

        return back()->with('success', 'Brand status updated.');
    }

    public function destroyBrandStatus(Company $company, CompanyBrandStatus $status): RedirectResponse
    {
        $status->delete();

        return back()->with('success', 'Brand status removed.');
    }

}
