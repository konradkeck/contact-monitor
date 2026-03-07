<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\BrandProduct;
use App\Models\Company;
use App\Models\CompanyAlias;
use App\Models\CompanyBrandStatus;
use App\Models\CompanyDomain;
use App\Models\Note;
use App\Models\NoteLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->get('q');
        $sort   = $request->get('sort', 'updated_at');
        $dir    = $request->get('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $brandProducts = BrandProduct::orderBy('name')->get();
        $channelTypes  = \App\Models\Conversation::distinct()->orderBy('channel_type')->pluck('channel_type');

        $allowedSorts = ['name', 'domain', 'contacts', 'updated_at', 'last_conv'];
        foreach ($brandProducts as $bp) {
            $allowedSorts[] = 'bp_score_' . $bp->id;
            $allowedSorts[] = 'bp_stage_' . $bp->id;
        }
        if (!in_array($sort, $allowedSorts)) $sort = 'updated_at';

        $query = Company::query()
            ->when($search, function ($q) use ($search) {
                $term = '%' . strtolower($search) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->whereRaw('LOWER(companies.name) LIKE ?', [$term])
                        ->orWhereHas('aliases', fn ($a) => $a->whereRaw('LOWER(alias_normalized) LIKE ?', [$term]))
                        ->orWhereHas('domains', fn ($d) => $d->whereRaw('LOWER(domain) LIKE ?', [$term]));
                });
            })
            ->when($request->get('f_domain'), fn ($q, $v) =>
                $q->whereHas('domains', fn ($d) => $d->whereRaw('LOWER(domain) LIKE ?', ['%'.strtolower($v).'%']))
            )
            ->when($request->get('f_people_min'), fn ($q, $v) => $q->has('people', '>=', (int) $v))
            ->when($request->get('f_conv_type'), fn ($q, $v) =>
                $q->whereHas('conversations', fn ($c) => $c->where('channel_type', $v))
            )
            ->when($request->get('f_updated_from'), fn ($q, $v) => $q->whereDate('updated_at', '>=', $v))
            ->when($request->get('f_updated_to'),   fn ($q, $v) => $q->whereDate('updated_at', '<=', $v))
            ->with([
                'domains',
                'aliases',
                'people.identities',
                'brandStatuses.brandProduct',
                'conversations' => fn ($q) => $q->orderByDesc('last_message_at'),
                'notes'         => fn ($q) => $q->orderByDesc('created_at'),
            ]);

        // Brand product filters
        foreach ($brandProducts as $bp) {
            if ($stage = $request->get("f_bp_{$bp->id}_stage")) {
                $query->whereHas('brandStatuses', fn ($q) =>
                    $q->where('brand_product_id', $bp->id)->where('stage', $stage)
                );
            }
            if ($min = $request->get("f_bp_{$bp->id}_score_min")) {
                $query->whereHas('brandStatuses', fn ($q) =>
                    $q->where('brand_product_id', $bp->id)->where('evaluation_score', '>=', (int) $min)
                );
            }
            if ($max = $request->get("f_bp_{$bp->id}_score_max")) {
                $query->whereHas('brandStatuses', fn ($q) =>
                    $q->where('brand_product_id', $bp->id)->where('evaluation_score', '<=', (int) $max)
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

        $companies = $query->paginate(20)->withQueryString();

        return view('companies.index', compact(
            'companies', 'search', 'sort', 'dir', 'brandProducts', 'channelTypes'
        ));
    }

    public function create(): View
    {
        return view('companies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'primary_domain' => 'nullable|string|max:255',
            'timezone'       => 'nullable|string|max:100',
        ]);

        $company = Company::create($data);

        if ($company->primary_domain) {
            CompanyDomain::create([
                'company_id' => $company->id,
                'domain'     => $company->primary_domain,
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
        ]);

        $notes = Note::whereHas('links', fn ($q) =>
            $q->where('linkable_type', Company::class)->where('linkable_id', $company->id)
        )->orderByDesc('created_at')->limit(10)->get();

        // Group conversations by (channel_type, system_slug) — one row per source
        $convGroups = collect(DB::select("
            SELECT DISTINCT ON (channel_type, system_slug)
                channel_type, system_slug, id AS last_conv_id,
                subject AS last_subject, last_message_at,
                COUNT(*) OVER (PARTITION BY channel_type, system_slug) AS conv_count
            FROM conversations
            WHERE company_id = ?
            ORDER BY channel_type, system_slug, last_message_at DESC
        ", [$company->id]))->sortByDesc('last_message_at');

        $conversationCount = $convGroups->sum('conv_count');

        // First page of timeline
        $timelinePage = $company->activities()
            ->with('person')
            ->orderByDesc('occurred_at')
            ->cursorPaginate(25);

        $availableBrands = BrandProduct::orderBy('name')->get()
            ->reject(fn ($bp) => $company->brandStatuses->pluck('brand_product_id')->contains($bp->id));

        $backLink = $this->resolveBackLink($request);

        return view('companies.show', compact(
            'company',
            'notes',
            'convGroups',
            'conversationCount',
            'timelinePage',
            'availableBrands',
            'backLink',
        ));
    }

    public function timeline(Request $request, Company $company)
    {
        $query = $company->activities()
            ->with('person')
            ->orderByDesc('occurred_at');

        if ($types = $request->get('types')) {
            $query->whereIn('type', (array) $types);
        }
        if ($from = $request->get('from')) {
            $query->whereDate('occurred_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('occurred_at', '<=', $to);
        }

        $page = $query->cursorPaginate(25, ['*'], 'cursor', $request->get('cursor'));

        return view('companies.partials.timeline-items', [
            'activities'  => $page->items(),
            'nextCursor'  => $page->nextCursor()?->encode(),
        ]);
    }

    public function edit(Company $company): View
    {
        return view('companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'primary_domain' => 'nullable|string|max:255',
            'timezone'       => 'nullable|string|max:100',
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
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        return response()->json($companies);
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
            'brand_product_id'  => 'required|exists:brand_products,id',
            'stage'             => 'required|string|max:100',
            'evaluation_score'  => 'nullable|integer|min:1|max:10',
            'evaluation_notes'  => 'nullable|string',
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
            'stage'            => 'required|string|max:100',
            'evaluation_score' => 'nullable|integer|min:1|max:10',
            'evaluation_notes' => 'nullable|string',
        ]);

        $status->update($data + ['last_evaluated_at' => now()]);

        return back()->with('success', 'Brand status updated.');
    }
}
