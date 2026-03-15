@extends('layouts.app')
@section('title', 'Companies')

@section('content')

<div class="page-header">
    <h1 class="page-title">Companies</h1>
    <div class="flex items-center gap-2">
        @if($showFiltered)
            <a href="{{ request()->fullUrlWithQuery(['show_filtered' => null]) }}"
               class="btn btn-danger btn-sm">
                ← All Companies
            </a>
        @else
            <a href="{{ request()->fullUrlWithQuery(['show_filtered' => 1]) }}"
               class="btn btn-secondary btn-sm">
                Filtered
                @if($filteredCount > 0)
                    <span class="ml-1 inline-flex items-center justify-center bg-brand-600 text-white text-xs font-bold rounded-full w-4 h-4 leading-none">
                        {{ $filteredCount }}
                    </span>
                @else
                    <span class="ml-1 text-xs text-gray-400">(0)</span>
                @endif
            </a>
        @endif
        @can('data_write')
        <a href="{{ route('companies.create') }}" class="btn btn-primary">+ New Company</a>
        @endcan
    </div>
</div>

<div class="flex gap-0 border-b border-gray-200 mb-5" role="tablist" aria-label="Company categories">
    <a href="{{ request()->fullUrlWithQuery(['tab' => 'clients', 'page' => null]) }}"
       role="tab" aria-selected="{{ $tab === 'clients' ? 'true' : 'false' }}"
       class="flex items-center gap-1.5 px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition
              {{ $tab === 'clients' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
        Clients
        <span class="px-1.5 py-0.5 rounded-full text-xs {{ $tab === 'clients' ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-500' }}">
            {{ number_format($tabCounts['clients']) }}
        </span>
    </a>
    <a href="{{ request()->fullUrlWithQuery(['tab' => 'our_org', 'page' => null]) }}"
       role="tab" aria-selected="{{ $tab === 'our_org' ? 'true' : 'false' }}"
       class="flex items-center gap-1.5 px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition
              {{ $tab === 'our_org' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
        Our Organization
        <span class="px-1.5 py-0.5 rounded-full text-xs {{ $tab === 'our_org' ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-500' }}">
            {{ number_format($tabCounts['our_org']) }}
        </span>
    </a>
</div>

<form method="GET" id="filter-form">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="hidden" name="sort" value="{{ $sort }}">
    <input type="hidden" name="dir"  value="{{ $dir }}">

    {{-- Search + Filter toggle --}}
    <div class="flex gap-2 mb-4 items-center">
        <div class="flex gap-2 flex-1 max-w-md">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search by name, domain, alias…"
                   class="input max-w-[280px]">
            <button type="submit" class="btn btn-secondary">Search</button>
            @if($hasFilters)
                <a href="{{ route('companies.index') }}" class="btn btn-muted">Clear</a>
            @endif
        </div>

        <button type="button" onclick="toggleFilterPanel()"
                class="btn {{ $activeFilterCount > 0 ? 'btn-primary' : 'btn-secondary' }}">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/>
            </svg>
            Filters
            @if($activeFilterCount > 0)
                <span class="ml-0.5 bg-white/25 text-white text-xs font-bold rounded-full w-4 h-4 flex items-center justify-center leading-none">
                    {{ $activeFilterCount }}
                </span>
            @endif
        </button>
    </div>

    {{-- Collapsible filter panel --}}
    <div id="filter-panel"
         class="{{ $activeFilterCount > 0 ? '' : 'hidden' }} card p-4 mb-4">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">Domain</label>
                <input type="text" name="f_domain" value="{{ request('f_domain') }}" placeholder="filter…"
                       class="input">
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">Min contacts</label>
                <input type="number" name="f_people_min" value="{{ request('f_people_min') }}" placeholder="e.g. 2"
                       min="0"
                       class="input">
            </div>
            @foreach($brandProducts as $bp)
                <div>
                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">
                        {{ $bp->name }}{{ $bp->variant ? ' · '.$bp->variant : '' }} — stage
                    </label>
                    <select name="f_bp_{{ $bp->id }}_stage"
                            class="input">
                        <option value="">any</option>
                        @foreach(['lead','prospect','trial','active','churned'] as $stage)
                            <option value="{{ $stage }}" {{ request("f_bp_{$bp->id}_stage") === $stage ? 'selected' : '' }}>
                                {{ ucfirst($stage) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">
                        {{ $bp->name }}{{ $bp->variant ? ' · '.$bp->variant : '' }} — score
                    </label>
                    <div class="flex items-center gap-1">
                        <input type="number" name="f_bp_{{ $bp->id }}_score_min"
                               value="{{ request("f_bp_{$bp->id}_score_min") }}"
                               placeholder="min" min="1" max="10"
                               class="input">
                        <span class="text-gray-300 shrink-0">–</span>
                        <input type="number" name="f_bp_{{ $bp->id }}_score_max"
                               value="{{ request("f_bp_{$bp->id}_score_max") }}"
                               placeholder="max" min="1" max="10"
                               class="input">
                    </div>
                </div>
            @endforeach
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">Updated from</label>
                <input type="date" name="f_updated_from" value="{{ request('f_updated_from') }}"
                       class="input">
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">Updated to</label>
                <input type="date" name="f_updated_to" value="{{ request('f_updated_to') }}"
                       class="input">
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">Channel type</label>
                <select name="f_conv_type"
                        class="input">
                    <option value="">any</option>
                    @foreach($channelTypes as $ct)
                        <option value="{{ $ct }}" {{ request('f_conv_type') === $ct ? 'selected' : '' }}>
                            {{ $ct }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3 flex justify-end gap-2">
            @if($activeFilterCount > 0)
                <a href="{{ route('companies.index', array_merge(request()->only(['q','sort','dir']))) }}"
                   class="btn btn-muted">Clear filters</a>
            @endif
            <button type="submit" class="btn btn-primary">Apply</button>
        </div>
    </div>

    {{-- Table — overflow-visible so tooltips escape the container --}}
    <div class="card overflow-visible relative">
        {{-- Bulk action bar --}}
        <div id="companies-bulk-bar" class="hidden items-center gap-3 px-4 py-2 border-b bulk-bar">
            <span id="companies-bulk-count" class="text-sm font-medium bulk-bar-text" aria-live="polite"></span>
            <button type="button" onclick="companiesOpenFilterModal()" class="btn btn-danger btn-sm">Filter…</button>
            <button type="button" onclick="companiesClearSelection()" class="text-xs text-gray-500 hover:text-gray-700">Clear</button>
        </div>
        <div class="overflow-x-auto">
        <table class="text-sm table-fixed" style="width:{{ 36 + 220 + 160 + 150 + ($brandProducts->isEmpty() ? 320 : $brandProducts->count() * 160) + 110 + 120 }}px; min-width:100%">
            <colgroup>
                <col style="width:36px">
                <col style="width:220px">
                <col style="width:160px">
                <col style="width:150px">
                @if($brandProducts->isEmpty())
                    <col style="width:320px">{{-- placeholder col --}}
                @else
                    @foreach($brandProducts as $bp)
                        <col style="width:160px">
                    @endforeach
                @endif
                <col style="width:110px">
                <col style="width:120px">
            </colgroup>
            <thead class="tbl-header">
                <tr>
                    <th scope="col" class="px-3 py-2.5 w-8">
                        <input type="checkbox" id="companies-select-all" aria-label="Select all" class="rounded border-gray-300 cursor-pointer"
                               onchange="companiesToggleAll(this)">
                    </th>
                    <th scope="col" class="px-4 py-2.5 text-left">
                        <a href="{{ $sortUrl('name') }}" class="flex items-center justify-between gap-2 hover:text-gray-900">
                            <span>Company</span><span class="shrink-0 opacity-60">{{ $sortIcon('name') }}</span>
                        </a>
                    </th>
                    <th scope="col" class="px-4 py-2.5 text-left">
                        <a href="{{ $sortUrl('domain') }}" class="flex items-center justify-between gap-2 hover:text-gray-900">
                            <span>Domain</span><span class="shrink-0 opacity-60">{{ $sortIcon('domain') }}</span>
                        </a>
                    </th>
                    <th scope="col" class="px-4 py-2.5 text-left">
                        <a href="{{ $sortUrl('contacts') }}" class="flex items-center justify-between gap-2 hover:text-gray-900">
                            <span>Contacts</span><span class="shrink-0 opacity-60">{{ $sortIcon('contacts') }}</span>
                        </a>
                    </th>
                    @if($brandProducts->isEmpty())
                        <th scope="col" class="px-4 py-2.5 text-left">
                            <span class="text-xs text-gray-500 font-normal italic">
                                Configure <a href="{{ route('segmentation.index') }}"
                                             class="underline hover:text-gray-700 transition">Segmentation</a> to evaluate
                            </span>
                        </th>
                    @else
                        @foreach($brandProducts as $bp)
                            <th scope="col" class="px-2 py-2.5 text-left">
                                <a href="{{ $sortUrl('bp_score_'.$bp->id) }}"
                                   class="flex items-center justify-between gap-1 hover:text-gray-900 text-xs">
                                    <span class="leading-tight truncate">{{ $bp->name }}{{ $bp->variant ? ' · '.$bp->variant : '' }}</span>
                                    <span class="shrink-0 opacity-60">{{ $sortIcon('bp_score_'.$bp->id) }}</span>
                                </a>
                            </th>
                        @endforeach
                    @endif
                    <th scope="col" class="px-4 py-2.5 text-left">
                        <a href="{{ $sortUrl('updated_at') }}" class="flex items-center justify-between gap-2 hover:text-gray-900">
                            <span>Updated</span><span class="shrink-0 opacity-60">{{ $sortIcon('updated_at') }}</span>
                        </a>
                    </th>
                    <th scope="col" class="px-4 py-2.5 text-left">
                        <a href="{{ $sortUrl('last_conv') }}" class="flex items-center justify-between gap-2 hover:text-gray-900">
                            <span>Channels</span><span class="shrink-0 opacity-60">{{ $sortIcon('last_conv') }}</span>
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse($companies as $company)
                    <tr class="tbl-row group/row">
                        <td class="px-3 py-3">
                            <input type="checkbox" value="{{ $company->id }}"
                                   class="companies-row-check rounded border-gray-300 cursor-pointer"
                                   onchange="companiesUpdateBulkBar()">
                        </td>

                        {{-- Company name + alias count + note icon --}}
                        <td class="px-4 py-3 overflow-hidden">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <a href="{{ route('companies.show', $company) }}"
                                   title="{{ $company->name }}"
                                   class="font-semibold text-gray-900 hover:text-brand-700 transition truncate">
                                    {{ $company->name }}
                                </a>
                                @if($company->aliases->filter(fn($a) => !$a->is_primary)->isNotEmpty())
                                    <div class="relative group inline-block shrink-0">
                                        <span class="text-xs text-gray-400 cursor-default leading-none">
                                            +{{ $company->aliases->filter(fn($a) => !$a->is_primary)->count() }}
                                        </span>
                                        <div class="absolute left-0 top-full mt-1 bg-gray-900 text-white text-xs
                                                    rounded-lg px-3 py-2 invisible opacity-0
                                                    group-hover:visible group-hover:opacity-100 transition z-30
                                                    min-w-max shadow-lg space-y-0.5 pointer-events-none">
                                            @foreach($company->aliases->filter(fn($a) => !$a->is_primary) as $alias)
                                                <div>{{ $alias->alias }}</div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                @if($showFiltered && isset($filteredReasons[$company->id]))
                                    <span title="{{ $filteredReasons[$company->id] }}"
                                          class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-red-100 text-red-600 text-[10px] font-bold shrink-0 cursor-default leading-none">
                                        i
                                    </span>
                                @endif
                                <div class="flex-1"></div>
                                <button type="button"
                                        onclick="companiesOpenFilterModal([{{ $company->id }}])"
                                        title="Filter"
                                        class="shrink-0 text-gray-300 hover:text-red-500 transition leading-none opacity-0 group-hover/row:opacity-100 focus:opacity-100">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
                                </button>
                                <x-notes-popup :notes="$company->notes" linkable-type="company" :linkable-id="$company->id" :entity-name="$company->name" />
                            </div>
                        </td>

                        {{-- Domain: primary + hover tooltip for extra domains --}}
                        <td class="px-4 py-3 overflow-hidden">
                            @if($company->_primaryDomain)
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="font-mono text-xs text-gray-600 truncate" title="{{ $company->_primaryDomain->domain }}">{{ $company->_primaryDomain->domain }}</span>
                                    @if($company->_extraDomains->isNotEmpty())
                                        <div class="relative group inline-block shrink-0">
                                            <span class="text-xs text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded font-medium leading-none cursor-default">
                                                +{{ $company->_extraDomains->count() }}
                                            </span>
                                            <div class="absolute left-0 top-full mt-1 bg-gray-900 text-white text-xs
                                                        rounded-lg px-3 py-2 invisible opacity-0
                                                        group-hover:visible group-hover:opacity-100 transition z-30
                                                        min-w-max shadow-lg space-y-0.5 pointer-events-none">
                                                @foreach($company->_extraDomains as $d)
                                                    <div class="font-mono">{{ $d->domain }}</div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>

                        {{-- Contacts: stacked avatars (team members excluded) --}}
                        <td class="px-4 py-3">
                            @if($company->_contacts->isEmpty())
                                <span class="text-gray-300 text-xs">—</span>
                            @else
                                <button type="button" onclick="openPopup('popup-people-{{ $company->id }}')"
                                        class="flex items-center justify-start cursor-pointer group">
                                    @foreach($company->_visiblePeople as $i => $person)
                                        <div class="w-7 h-7 rounded-full bg-brand-100 text-brand-700 border-2 border-white
                                                    flex items-center justify-center text-xs font-bold shrink-0
                                                    group-hover:border-brand-200 transition
                                                    {{ $i > 0 ? '-ml-1.5' : '' }}">
                                            {{ strtoupper(substr($person->first_name,0,1)) }}{{ strtoupper(substr($person->last_name??'',0,1)) }}
                                        </div>
                                    @endforeach
                                    @if($company->_extraPeople > 0)
                                        <div class="w-7 h-7 rounded-full bg-gray-100 text-gray-500 border-2 border-white
                                                    flex items-center justify-center text-xs font-semibold shrink-0 -ml-1.5
                                                    group-hover:bg-gray-200 transition">
                                            +{{ $company->_extraPeople }}
                                        </div>
                                    @endif
                                </button>
                            @endif
                        </td>

                        {{-- Segmentation columns: dot + score + stage badge inline --}}
                        @if($brandProducts->isEmpty())
                            <td></td>
                        @endif
                        @foreach($brandProducts as $bp)
                            @if($company->brandStatuses->first(fn($s) => $s->brand_product_id === $bp->id))
                                <x-brand-status-cell
                                    :status="$company->brandStatuses->first(fn($s) => $s->brand_product_id === $bp->id)"
                                    :company-id="$company->id"
                                    :bp-id="$bp->id" />
                            @else
                                <td class="px-2 py-2 text-center text-gray-300 text-xs">—</td>
                            @endif
                        @endforeach

                        {{-- Last update with full-date tooltip --}}
                        <td class="px-4 py-3 text-xs text-gray-400 whitespace-nowrap">
                            <span title="{{ $fmtDate($company->updated_at) }}">
                                {{ $company->updated_at->diffForHumans() }}
                            </span>
                        </td>

                        {{-- Conv channels: one icon per unique channel type --}}
                        <td class="px-4 py-3">
                            @if($company->_convChannels->isEmpty())
                                <span class="text-gray-300 text-xs">—</span>
                            @else
                                <div class="flex items-center gap-1 flex-wrap">
                                    @foreach($company->_convChannels as $conv)
                                        <x-conv-channel-icon :channel-type="$conv->channel_type" :company-id="$company->id" />
                                    @endforeach
                                </div>
                            @endif
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 6 + max(1, $brandProducts->count()) }}"
                            class="px-4 py-10 text-center text-gray-400 italic">
                            No companies found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>{{-- /overflow-x-auto --}}

        @if($companies->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $companies->links() }}
            </div>
        @endif
    </div>
</form>

{{-- ── Contacts popups ── --}}
<div id="popup-backdrop" class="hidden fixed inset-0 bg-black/40 z-40" onclick="closeAllPopups()"></div>

@foreach($companies as $company)
    @if($company->_contacts->isNotEmpty())
        <div id="popup-people-{{ $company->id }}"
             class="hidden fixed z-50 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
                    w-[360px] bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">
                    Contacts — {{ $company->name }}
                </h3>
                <button type="button" onclick="closeAllPopups()"
                        class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
            </div>
            <ul class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                @foreach($company->_contacts as $person)
                    <li>
                        <a href="{{ route('people.show', $person) }}"
                           class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition">
                            <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 flex items-center
                                        justify-center text-xs font-bold shrink-0">
                                {{ strtoupper(substr($person->first_name,0,1)) }}{{ strtoupper(substr($person->last_name??'',0,1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $person->full_name }}</p>
                                @if($person->pivot->role)
                                    <p class="text-xs text-gray-400">{{ $person->pivot->role }}</p>
                                @endif
                            </div>
                            <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endforeach

{{-- ── Brand product quick popups ── --}}
@foreach($companies as $company)
    @foreach($brandProducts as $bp)
        @if($company->brandStatuses->first(fn($s) => $s->brand_product_id === $bp->id))
            <x-brand-status-popup
                :status="$company->brandStatuses->first(fn($s) => $s->brand_product_id === $bp->id)"
                :bp="$bp"
                :company="$company" />
        @endif
    @endforeach
@endforeach


<script>
function openPopup(id) {
    document.querySelectorAll('[id^="popup-"]:not(#popup-backdrop)').forEach(el => el.classList.add('hidden'));
    document.getElementById(id)?.classList.remove('hidden');
    document.getElementById('popup-backdrop')?.classList.remove('hidden');
}
function closeAllPopups() {
    document.querySelectorAll('[id^="popup-"]:not(#popup-backdrop)').forEach(el => el.classList.add('hidden'));
    document.getElementById('popup-backdrop')?.classList.add('hidden');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAllPopups(); });

function toggleFilterPanel() {
    document.getElementById('filter-panel').classList.toggle('hidden');
}

function companiesUpdateBulkBar() {
    const checked = document.querySelectorAll('.companies-row-check:checked');
    const bar   = document.getElementById('companies-bulk-bar');
    const count = document.getElementById('companies-bulk-count');
    if (checked.length > 0) {
        bar.classList.remove('hidden');
        bar.classList.add('flex');
        count.textContent = checked.length + ' selected';
    } else {
        bar.classList.add('hidden');
        bar.classList.remove('flex');
    }
    const all = document.querySelectorAll('.companies-row-check');
    document.getElementById('companies-select-all').indeterminate = checked.length > 0 && checked.length < all.length;
    document.getElementById('companies-select-all').checked = checked.length === all.length && all.length > 0;
}
function companiesToggleAll(cb) {
    document.querySelectorAll('.companies-row-check').forEach(c => c.checked = cb.checked);
    companiesUpdateBulkBar();
}
function companiesClearSelection() {
    document.querySelectorAll('.companies-row-check, #companies-select-all').forEach(c => c.checked = false);
    companiesUpdateBulkBar();
}
function companiesOpenFilterModal(ids) {
    if (!ids) ids = [...document.querySelectorAll('.companies-row-check:checked')].map(c => c.value);
    if (!ids.length) return;
    const qs = ids.map(id => 'ids[]=' + id).join('&');
    const src = '{{ route('companies.filter-modal') }}?' + qs;
    openActivityModal({ dataset: { modalSrc: src } });
}
</script>

@endsection
