@extends('layouts.app')
@section('title', 'Activities')

@section('content')

<div class="page-header">
    <h1 class="page-title">Activities</h1>
</div>

{{-- ─── TABS ─── --}}
<div class="flex gap-0 border-b border-gray-200 mb-5" role="tablist" aria-label="Activity view">
    @foreach(['all' => 'All', 'conversations' => 'Conversations', 'activity' => 'Activity'] as $tabKey => $tabLabel)
        <a id="tl-tab-{{ $tabKey }}" href="#" onclick="setTab('{{ $tabKey }}'); return false;"
                role="tab"
                aria-selected="{{ $tabKey === 'all' ? 'true' : 'false' }}"
                aria-controls="timeline-container"
                class="px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition whitespace-nowrap
                       {{ $tabKey === 'all' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            {{ $tabLabel }}
        </a>
    @endforeach
</div>

{{-- ─── SEARCH + FILTER BAR ─── --}}
<div class="flex gap-2 mb-4 items-center flex-wrap">
    <button type="button" id="tl-filters-btn" onclick="tlToggleFilterPanel()"
            class="btn btn-secondary">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/>
        </svg>
        Filters
        <span id="tl-filter-count-badge" class="hidden ml-0.5 bg-white/25 text-white text-xs font-bold rounded-full w-4 h-4 flex items-center justify-center leading-none"></span>
    </button>
    <input type="text" id="tl-search-input" placeholder="Search activities…" class="input max-w-[280px]">
    <button type="button" onclick="tlApplySearch()" class="btn btn-secondary">Search</button>
    <button id="tl-clear-btn" onclick="resetFilters()"
            class="hidden btn btn-muted">Clear</button>
</div>

{{-- ─── COLLAPSIBLE FILTER PANEL ─── --}}
<div id="tl-filter-panel" class="hidden card p-4 mb-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        {{-- Date range --}}
        <div class="min-w-0">
            <label class="label mb-1">Date range</label>
            <div class="drp-wrap flex items-center gap-1.5">
                <input id="tl-date-range" type="text" placeholder="Date range…"
                       class="input cursor-pointer flex-1 min-w-0">
                <button type="button" class="drp-clear hidden text-base leading-none text-gray-400 hover:text-gray-600 px-1">×</button>
            </div>
        </div>

        {{-- Channels --}}
        @if($convSystems->isNotEmpty())
        <div class="min-w-0 sm:col-span-1 lg:col-span-2">
            <label class="label mb-1">Channels</label>
            <div class="flex flex-wrap gap-2">
                @foreach($convSystems as $sys)
                    <label class="flex items-center gap-1.5 cursor-pointer select-none border border-gray-200 rounded-lg px-2 py-1 hover:border-gray-300 hover:bg-gray-50 transition text-xs tl-sys-label">
                        <input type="checkbox" class="tl-conv-item rounded border-gray-300"
                               value="{{ $sys->channel_type }}|{{ $sys->system_slug }}"
                               onchange="tlConvItem(this)">
                        <span class="inline-flex items-center gap-1">
                            <x-channel-badge :type="$sys->channel_type" :label="false" />
                            @if($sys->system_type && get_class(\App\Integrations\IntegrationRegistry::get($sys->system_type)) !== get_class(\App\Integrations\IntegrationRegistry::get($sys->channel_type)))
                                {!! \App\Integrations\IntegrationRegistry::get($sys->system_type)->iconHtml('w-4 h-4', false) !!}
                            @endif
                        </span>
                        <span class="text-gray-600">{{ $sys->system_slug }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Activity type --}}
        @if($activityTypes->isNotEmpty())
        <div class="min-w-0">
            <label class="label mb-1">Activity type</label>
            <div class="flex flex-wrap gap-2">
                @foreach($activityTypes as $t)
                    <label class="flex items-center gap-1.5 cursor-pointer select-none border border-gray-200 rounded-lg px-2 py-1 hover:border-gray-300 hover:bg-gray-50 transition text-xs tl-act-label">
                        <input type="checkbox" class="tl-act-item rounded border-gray-300"
                               value="{{ $t }}" onchange="tlActItem(this)">
                        <span class="w-2 h-2 rounded-full {{ $typeColors[$t] ?? 'bg-slate-300' }} shrink-0"></span>
                        <span class="text-gray-600">{{ $t === 'note' ? 'Other' : ucfirst(str_replace('_', ' ', $t)) }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        @endif

    </div>
    <div class="mt-3 flex justify-end gap-2">
        <button type="button" onclick="resetFilters()" class="btn btn-muted" id="tl-panel-clear-btn">Clear filters</button>
        <button type="button" onclick="tlApplyFilters()" class="btn btn-primary">Apply</button>
    </div>
</div>

{{-- ─── TIMELINE CARD ─── --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="relative px-4 py-2 min-h-[120px]">
        <div class="absolute inset-y-0 left-1/2 -translate-x-1/2 w-px bg-gray-200 pointer-events-none z-0"></div>
        <div id="timeline-container" class="grid grid-cols-[1fr_2rem_1fr] relative z-10">
            @include('activity.partials.timeline-items', [
                'activities'     => $timelinePage->items(),
                'nextCursor'     => $timelinePage->nextCursor()?->encode(),
                'convSubjectMap' => $convSubjectMap,
            ])
        </div>
        <div id="timeline-loading" class="hidden py-5 text-center">
            <div class="inline-block w-5 h-5 border-2 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const container = document.getElementById('timeline-container');
    const loading   = document.getElementById('timeline-loading');
    const clearBtn  = document.getElementById('tl-clear-btn');
    const filterBtn = document.getElementById('tl-filters-btn');
    const countBadge = document.getElementById('tl-filter-count-badge');

    let fetching       = false;
    let reqId          = 0;
    let activeSystems  = [];
    let activeActTypes = [];
    let dateFrom       = '';
    let dateTo         = '';
    let searchQuery    = '';
    let fp             = null;
    let activeTab      = 'all';

    // ── Filter panel toggle ──
    window.tlToggleFilterPanel = function() {
        document.getElementById('tl-filter-panel')?.classList.toggle('hidden');
    };

    // ── Tab shortcuts ──
    window.setTab = function (tab) {
        ['all', 'conversations', 'activity'].forEach(k => {
            const btn = document.getElementById('tl-tab-' + k);
            if (!btn) return;
            const active = k === tab;
            btn.setAttribute('aria-selected', String(active));
            if (active) {
                btn.classList.add('border-brand-600', 'text-brand-700');
                btn.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            } else {
                btn.classList.remove('border-brand-600', 'text-brand-700');
                btn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            }
        });
        clearCheckboxes();
        activeSystems  = [];
        activeActTypes = [];
        activeTab      = tab;
        updateFilterBtn();
        reload();
    };

    function clearCheckboxes() {
        document.querySelectorAll('.tl-conv-item').forEach(c => { c.checked = false; });
        document.querySelectorAll('.tl-act-item').forEach(c => { c.checked = false; });
        // Reset channel label highlights
        document.querySelectorAll('.tl-sys-label, .tl-act-label').forEach(l => {
            l.classList.remove('border-brand-400', 'bg-brand-50');
        });
    }

    // ── Checkboxes ──
    window.tlConvItem = function (cb) {
        activeSystems = Array.from(document.querySelectorAll('.tl-conv-item:checked')).map(c => c.value);
        cb.closest('label')?.classList.toggle('border-brand-400', cb.checked);
        cb.closest('label')?.classList.toggle('bg-brand-50', cb.checked);
        updateFilterBtn();
    };

    window.tlActItem = function (cb) {
        activeActTypes = Array.from(document.querySelectorAll('.tl-act-item:checked')).map(c => c.value);
        cb.closest('label')?.classList.toggle('border-brand-400', cb.checked);
        cb.closest('label')?.classList.toggle('bg-brand-50', cb.checked);
        updateFilterBtn();
    };

    // ── Apply filters (from panel Apply button) ──
    window.tlApplyFilters = function() {
        document.getElementById('tl-filter-panel')?.classList.add('hidden');
        reload();
    };

    // ── Search ──
    window.tlApplySearch = function() {
        searchQuery = document.getElementById('tl-search-input')?.value.trim() || '';
        updateFilterBtn();
        reload();
    };

    // Allow Enter key in search input
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('tl-search-input')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') tlApplySearch();
        });
    });

    function updateFilterBtn() {
        const count = activeSystems.length + activeActTypes.length + (dateFrom ? 1 : 0) + (searchQuery ? 1 : 0);
        const hasAny = hasFilters();
        clearBtn?.classList.toggle('hidden', !hasAny);

        if (count > 0) {
            filterBtn?.classList.remove('btn-secondary');
            filterBtn?.classList.add('btn-primary');
            if (countBadge) {
                countBadge.textContent = count;
                countBadge.classList.remove('hidden');
            }
        } else {
            filterBtn?.classList.add('btn-secondary');
            filterBtn?.classList.remove('btn-primary');
            if (countBadge) countBadge.classList.add('hidden');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        fp = drp.init('tl-date-range', function(from, to) {
            dateFrom = from; dateTo = to;
            updateFilterBtn();
            reload();
        });
    });

    function buildParams(cursor) {
        const p = new URLSearchParams();
        if (cursor)      p.set('cursor', cursor);
        if (dateFrom)    p.set('from', dateFrom);
        if (dateTo)      p.set('to', dateTo);
        if (searchQuery) p.set('q', searchQuery);

        if (activeSystems.length) {
            p.append('types[]', 'conversation');
            activeSystems.forEach(s => p.append('systems[]', s));
        } else if (activeActTypes.length) {
            activeActTypes.forEach(t => p.append('types[]', t));
        } else if (activeTab === 'conversations') {
            p.append('types[]', 'conversation');
        } else if (activeTab === 'activity') {
            p.set('exclude_type', 'conversation');
        }

        return p;
    }

    function hasFilters() {
        return activeSystems.length > 0 || activeActTypes.length > 0 || dateFrom || dateTo || searchQuery;
    }

    function sentinel() { return document.getElementById('timeline-sentinel'); }

    function loadMore(cursor) {
        if (fetching) return;
        fetching = true;
        const myId = ++reqId;
        loading.classList.remove('hidden');

        fetch('/activities/timeline?' + buildParams(cursor), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                if (myId !== reqId) return;
                sentinel()?.remove();
                const tmp = document.createElement('div');
                tmp.innerHTML = html;
                Array.from(tmp.children).forEach(c => container.appendChild(c));
                loading.classList.add('hidden');
                fetching = false;
                const s = sentinel();
                if (s) observer.observe(s);
            })
            .catch(() => {
                if (myId === reqId) { loading.classList.add('hidden'); fetching = false; }
            });
    }

    function reload() {
        const savedY = window.scrollY;
        const savedH = container.offsetHeight;
        container.style.minHeight = savedH + 'px';
        observer.disconnect();
        reqId++;
        container.innerHTML = '';
        fetching = false;
        loadMore(null);
        window.scrollTo({ top: savedY, behavior: 'instant' });
        setTimeout(() => { container.style.minHeight = ''; }, 600);
    }

    window.clearDateFilter = function () { fp?.clear(); };
    window.resetFilters    = function () {
        clearCheckboxes();
        activeSystems  = [];
        activeActTypes = [];
        searchQuery    = '';
        const searchInp = document.getElementById('tl-search-input');
        if (searchInp) searchInp.value = '';
        updateFilterBtn();
        fp?.clear();
        if (!dateFrom && !dateTo) reload();
    };

    const observer = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting && e.target.dataset.nextCursor) {
                observer.unobserve(e.target);
                loadMore(e.target.dataset.nextCursor);
            }
        });
    }, { rootMargin: '300px' });

    const s = sentinel();
    if (s) observer.observe(s);
})();
</script>
@endpush
