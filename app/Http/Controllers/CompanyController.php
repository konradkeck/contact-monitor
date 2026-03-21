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

use Inertia\Inertia;

class CompanyController extends Controller
{
    use BuildsConvSubjectMap;

    public function index(Request $request)
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
                $query->whereHas('brandStatuses', fn ($q) => $q->where('brand_product_id', $bp->id)->where('stage', $stage));
            }
            if ($min = $request->get("f_bp_{$bp->id}_score_min")) {
                $query->whereHas('brandStatuses', fn ($q) => $q->where('brand_product_id', $bp->id)->where('evaluation_score', '>=', (int) $min));
            }
            if ($max = $request->get("f_bp_{$bp->id}_score_max")) {
                $query->whereHas('brandStatuses', fn ($q) => $q->where('brand_product_id', $bp->id)->where('evaluation_score', '<=', (int) $max));
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

        // Pre-compute per-company display data for Vue serialization
        foreach ($companies as $company) {
            $primaryDomain = $company->domains->firstWhere('is_primary', true) ?? $company->domains->first();
            $contacts = $company->people->filter(fn ($p) => ! $p->is_our_org && is_null($p->merged_into_id));

            $company->primary_domain_name = $primaryDomain?->domain;
            $extraDomains = $company->domains->filter(fn ($d) => $d->id !== ($primaryDomain?->id));
            $company->extra_domains = $extraDomains->map(fn ($d) => $d->domain)->values()->toArray();
            $company->extra_domains_count = $extraDomains->count();
            $company->contacts_count = $contacts->count();
            $company->visible_people = ($contacts->count() > 5 ? $contacts->take(4) : $contacts)->map(fn ($p) => [
                'id' => $p->id,
                'full_name' => trim($p->first_name . ' ' . $p->last_name),
                'initials' => strtoupper(substr($p->first_name, 0, 1)).strtoupper(substr($p->last_name ?? '', 0, 1)),
            ])->values()->toArray();
            $company->extra_people_count = $contacts->count() > 5 ? $contacts->count() - 4 : 0;
            $company->all_contacts = $contacts->map(fn ($p) => [
                'id' => $p->id,
                'full_name' => trim($p->first_name . ' ' . $p->last_name),
                'initials' => strtoupper(substr($p->first_name, 0, 1)).strtoupper(substr($p->last_name ?? '', 0, 1)),
                'role' => $p->pivot->role ?? null,
            ])->values()->toArray();
            $company->conv_channels = $company->conversations->unique('channel_type')->pluck('channel_type')->values()->toArray();
            $company->non_primary_aliases = $company->aliases->filter(fn ($a) => ! $a->is_primary)->pluck('alias')->values()->toArray();
            $company->notes_count = $company->notes->count();
            $company->updated_at_human = $company->updated_at?->diffForHumans();
            $company->updated_at_full = $company->updated_at?->format('D, j M Y H:i');
            $company->filtered_reason = $filteredReasons[$company->id] ?? null;

            // Brand status display data
            $company->brand_display = $company->brandStatuses->map(fn ($bs) => [
                'brand_product_id' => $bs->brand_product_id,
                'brand_name' => $bs->brandProduct->name ?? '',
                'stage' => $bs->stage,
                'score' => $bs->evaluation_score,
                'notes' => $bs->notes,
                'last_evaluated_at' => $bs->last_evaluated_at ? \Carbon\Carbon::parse($bs->last_evaluated_at)->diffForHumans() : null,
            ])->values()->toArray();
        }

        $scoreColorMap = [
            1  => '#ef4444', 2  => '#f97316', 3  => '#f59e0b', 4  => '#eab308',
            5  => '#84cc16', 6  => '#4ade80', 7  => '#22c55e', 8  => '#16a34a',
            9  => '#15803d', 10 => '#166534',
        ];

        $convTypeMap = [
            'email'    => ['cls' => 'bg-sky-100 text-sky-700'],
            'mail'     => ['cls' => 'bg-sky-100 text-sky-700'],
            'ticket'   => ['cls' => 'bg-amber-100 text-amber-700'],
            'support'  => ['cls' => 'bg-amber-100 text-amber-700'],
            'discord'  => ['cls' => 'text-white', 'style' => 'background:#5865F2'],
            'slack'    => ['cls' => 'text-white', 'style' => 'background:#4A154B'],
            'chat'     => ['cls' => 'bg-purple-100 text-purple-700'],
            'call'     => ['cls' => 'bg-orange-100 text-orange-700'],
            'sms'      => ['cls' => 'bg-teal-100 text-teal-700'],
            'whatsapp' => ['cls' => 'bg-green-100 text-green-700'],
        ];

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

        $tabCounts = [
            'clients' => Company::notMerged()->count(),
            'our_org' => Company::notMerged()->whereHas('people', fn ($p) => $p->where('is_our_org', true))->count(),
        ];

        $hasFilters = $search || $activeFilterCount > 0;

        // Collect current brand filter values for Vue
        $brandFilters = [];
        foreach ($brandProducts as $bp) {
            $brandFilters[$bp->id] = [
                'stage' => $request->get("f_bp_{$bp->id}_stage", ''),
                'score_min' => $request->get("f_bp_{$bp->id}_score_min", ''),
                'score_max' => $request->get("f_bp_{$bp->id}_score_max", ''),
            ];
        }

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'brandProducts' => $brandProducts->map(fn ($bp) => [
                'id' => $bp->id,
                'name' => $bp->name,
                'variant' => $bp->variant,
            ])->values(),
            'channelTypes' => $channelTypes,
            'filteredCount' => $filteredCount,
            'showFiltered' => $showFiltered,
            'scoreColorMap' => $scoreColorMap,
            'convTypeMap' => $convTypeMap,
            'activeFilterCount' => $activeFilterCount,
            'hasFilters' => $hasFilters,
            'tab' => $tab,
            'tabCounts' => $tabCounts,
            'f_domain' => $request->get('f_domain', ''),
            'f_people_min' => $request->get('f_people_min', ''),
            'f_conv_type' => $request->get('f_conv_type', ''),
            'f_updated_from' => $request->get('f_updated_from', ''),
            'f_updated_to' => $request->get('f_updated_to', ''),
            'brandFilters' => $brandFilters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Companies/Create');
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

    public function show(Request $request, Company $company): \Inertia\Response
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

        // Conversation systems for timeline filter dropdown
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
        $nonPrimaryAliasCount = $company->aliases->filter(fn ($a) => ! $a->is_primary)->count();

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

        // Serialize data for Inertia
        $serializedNotes = $notes->map(fn ($n) => [
            'id'         => $n->id,
            'content'    => $n->content,
            'user_name'  => $n->user?->name,
            'created_at' => $n->created_at?->toIso8601String(),
        ])->all();

        $serializedDomains = $company->domains->map(fn ($d) => [
            'id'         => $d->id,
            'domain'     => $d->domain,
            'is_primary' => $d->is_primary,
        ])->all();

        $serializedAliases = $company->aliases->map(fn ($a) => [
            'id'         => $a->id,
            'alias'      => $a->alias,
            'is_primary' => $a->is_primary,
        ])->all();

        $serializedAccounts = $company->accounts->map(fn ($a) => [
            'id'          => $a->id,
            'system_type' => $a->system_type,
            'system_slug' => $a->system_slug,
            'external_id' => $a->external_id,
        ])->all();

        $serializedBrandStatuses = $company->brandStatuses->map(fn ($bs) => [
            'id'                => $bs->id,
            'stage'             => $bs->stage,
            'evaluation_score'  => $bs->evaluation_score,
            'evaluation_notes'  => $bs->evaluation_notes,
            'last_evaluated_at' => $bs->last_evaluated_at?->format('d M Y'),
            'brand_product'     => $bs->brandProduct ? [
                'id'      => $bs->brandProduct->id,
                'name'    => $bs->brandProduct->name,
                'variant' => $bs->brandProduct->variant,
            ] : null,
        ])->all();

        $serializedContacts = $contacts->map(fn ($p) => [
            'id'        => $p->id,
            'full_name' => $p->full_name,
            'role'      => $p->pivot->role ?? null,
            'avatar_url' => $p->gravatarUrl(40),
            'initials'  => mb_strtoupper(mb_substr($p->first_name ?? '', 0, 1) . mb_substr($p->last_name ?? '', 0, 1)),
        ])->values()->all();

        $serializedMerged = $mergedCompanies->map(function ($mc) {
            $primaryDom = $mc->domains->firstWhere('is_primary', true) ?? $mc->domains->first();
            return [
                'id'             => $mc->id,
                'name'           => $mc->name,
                'primary_domain' => $primaryDom?->domain,
                'accounts_count' => $mc->accounts->count(),
                'people_count'   => $mc->people->count(),
            ];
        })->all();

        $serializedAvailableBrands = $availableBrands->map(fn ($bp) => [
            'id'      => $bp->id,
            'name'    => $bp->name,
            'variant' => $bp->variant,
        ])->values()->all();

        $serializedConvSystems = $convSystems->map(fn ($s) => [
            'channel_type' => $s->channel_type,
            'system_slug'  => $s->system_slug,
            'system_type'  => $s->system_type,
        ])->all();

        return Inertia::render('Companies/Show', [
            'company' => [
                'id'              => $company->id,
                'name'            => $company->name,
                'merged_into_id'  => $company->merged_into_id,
                'merged_into'     => $company->mergedInto ? [
                    'id'   => $company->mergedInto->id,
                    'name' => $company->mergedInto->name,
                ] : null,
            ],
            'domains'         => $serializedDomains,
            'aliases'         => $serializedAliases,
            'accounts'        => $serializedAccounts,
            'brandStatuses'   => $serializedBrandStatuses,
            'contacts'        => $serializedContacts,
            'mergedCompanies' => $serializedMerged,
            'notes'           => $serializedNotes,
            'primaryDomain'   => $primaryDomain ? ['id' => $primaryDomain->id, 'domain' => $primaryDomain->domain] : null,
            'otherDomainCount' => $otherDomains->count(),
            'primaryAlias'    => $primaryAlias ? ['alias' => $primaryAlias->alias] : null,
            'nonPrimaryAliasCount' => $nonPrimaryAliasCount,
            'serviceSystems'  => $serviceSystems,
            'availableBrands' => $serializedAvailableBrands,
            'timeline' => [
                'items'      => $this->serializeTimelineItems($timelinePage->items()),
                'nextCursor' => $timelinePage->nextCursor()?->encode(),
            ],
            'convSystems'      => $serializedConvSystems,
            'filteredConvCount' => $filteredConvCount,
            'activityTypes'    => $activityTypes->all(),
            'typeColors'       => $typeColors,
            'backLink'         => $backLink,
            'filterModalUrl'   => route('companies.filter-modal'),
        ]);
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

        return response()->json([
            'items'      => $this->serializeTimelineItems($page->items()),
            'nextCursor' => $page->nextCursor()?->encode(),
        ]);
    }

    public function edit(Company $company)
    {
        return Inertia::render('Companies/Edit', [
            'company' => $company,
        ]);
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

    public function mergeModal(Request $request): JsonResponse
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->get('ids', [])))));
        abort_if(count($ids) < 2, 400, 'Select at least 2 companies to merge.');

        $companies = Company::with(['domains', 'aliases', 'accounts', 'people', 'conversations'])
            ->whereIn('id', $ids)
            ->whereNull('merged_into_id')
            ->get();

        abort_if($companies->count() < 2, 400, 'Not enough valid (non-merged) companies selected.');

        return response()->json([
            'companies' => $companies->map(fn ($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'primary_domain' => $c->domains->firstWhere('is_primary', true)?->domain,
                'contacts_count' => $c->people->count(),
                'conversations_count' => $c->conversations->count(),
                'accounts_count' => $c->accounts->count(),
                'domains_count'  => $c->domains->count(),
                'system_types'   => $c->accounts->pluck('system_type')->unique()->values()->all(),
            ])->values()->all(),
        ]);
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
