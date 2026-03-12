@extends('layouts.app')
@section('title', 'Activities')

@php
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
@endphp

@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Activities</h1>
</div>

<div class="flex gap-5 items-start">

{{-- ─── LEFT: Timeline ─── --}}
<div class="flex-1 min-w-0">
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

    {{-- Tab bar --}}
    <div class="flex items-center border-b border-gray-100 px-4 pt-1">
        @foreach(['all' => 'All', 'conversations' => 'Conversations', 'activity' => 'Activity'] as $tabKey => $tabLabel)
            <button id="tl-tab-{{ $tabKey }}" onclick="setTab('{{ $tabKey }}')"
                    class="px-4 py-2.5 text-sm font-medium border-b-2 transition whitespace-nowrap mr-1
                           {{ $tabKey === 'all' ? 'border-brand-500 text-brand-700' : 'border-transparent text-gray-400 hover:text-gray-700' }}">
                {{ $tabLabel }}
            </button>
        @endforeach
    </div>

    {{-- Filter bar (always visible) --}}
    <div class="px-5 pt-3 pb-3 border-b border-gray-100">
    <div class="flex flex-wrap items-center gap-3">

        {{-- Conversations system filter --}}
        @if($convSystems->isNotEmpty())
        <div class="relative" id="tl-conv-wrapper">
            <button onclick="tlToggleDropdown('conv', event)"
                    class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 bg-white
                           text-xs text-gray-600 hover:border-gray-300 transition min-w-[130px]">
                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/>
                </svg>
                <span id="tl-conv-label" class="flex-1 text-left">Channels</span>
                <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div id="tl-conv-menu" class="hidden absolute left-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1.5 w-64">
                <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                    <input type="checkbox" id="tl-conv-all" class="rounded border-gray-300" checked onchange="tlConvAll(this)">
                    <span class="text-sm text-gray-700 font-medium">All channels</span>
                </label>
                <div class="border-t border-gray-100 my-1"></div>
                @foreach($convSystems as $sys)
                    @php
                        $sysIntCls = get_class(\App\Integrations\IntegrationRegistry::get($sys->system_type ?? ''));
                        $chnIntCls = get_class(\App\Integrations\IntegrationRegistry::get($sys->channel_type));
                        $showSysLogo = $sys->system_type && $sysIntCls !== $chnIntCls;
                    @endphp
                    <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                        <input type="checkbox" class="tl-conv-item rounded border-gray-300"
                               value="{{ $sys->channel_type }}|{{ $sys->system_slug }}" onchange="tlConvItem(this)">
                        <span class="inline-flex items-center gap-1">
                            <x-channel-badge :type="$sys->channel_type" :label="false" />
                            @if($showSysLogo)
                                {!! \App\Integrations\IntegrationRegistry::get($sys->system_type)->iconHtml('w-4 h-4', false) !!}
                            @endif
                        </span>
                        <span class="text-xs text-gray-700 truncate">{{ $sys->system_slug }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Activity type filter --}}
        @if($activityTypes->isNotEmpty())
        <div class="relative" id="tl-act-wrapper">
            <button onclick="tlToggleDropdown('act', event)"
                    class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 bg-white
                           text-xs text-gray-600 hover:border-gray-300 transition min-w-[130px]">
                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/>
                </svg>
                <span id="tl-act-label" class="flex-1 text-left">Activity type</span>
                <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div id="tl-act-menu" class="hidden absolute left-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1.5 w-52">
                <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                    <input type="checkbox" id="tl-act-all" class="rounded border-gray-300" checked onchange="tlActAll(this)">
                    <span class="text-sm text-gray-700 font-medium">All types</span>
                </label>
                <div class="border-t border-gray-100 my-1"></div>
                @foreach($activityTypes as $t)
                    <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                        <input type="checkbox" class="tl-act-item rounded border-gray-300"
                               value="{{ $t }}" onchange="tlActItem(this)">
                        <span class="w-2 h-2 rounded-full {{ $typeColors[$t] ?? 'bg-slate-300' }} shrink-0"></span>
                        <span class="text-sm text-gray-700">{{ $t === 'note' ? 'Other' : ucfirst(str_replace('_', ' ', $t)) }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        @endif

        <div class="flex-1"></div>

        <button id="tl-clear-btn" onclick="resetFilters()"
                class="hidden text-xs text-gray-400 hover:text-gray-600 transition whitespace-nowrap">
            ✕ Clear
        </button>

        <div class="flex items-center gap-1">
            <input id="tl-date-range" type="text" placeholder="Date range…"
                   class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-600 bg-white
                          focus:outline-none focus:ring-2 focus:ring-brand-300 cursor-pointer w-44">
            <button id="tl-date-clear" type="button" onclick="clearDateFilter()"
                    class="hidden text-lg leading-none text-gray-400 hover:text-gray-600 transition px-1">×</button>
        </div>

    </div>
    </div>

    {{-- Timeline body --}}
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
</div>{{-- /LEFT --}}

{{-- ─── RIGHT: Stats widget ─── --}}
<div class="w-56 shrink-0 sticky top-4">
    <div id="stats-widget" class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Breakdown</p>
        <div id="stats-body">
            @include('partials.activity-stats', [
                'typeCounts' => $typeCounts,
                'convCounts' => $convCounts,
                'totalConv'  => $totalConv,
            ])
        </div>
    </div>
</div>{{-- /RIGHT --}}

</div>{{-- /flex --}}

@endsection

@push('scripts')
<script>
(function () {
    const container = document.getElementById('timeline-container');
    const loading   = document.getElementById('timeline-loading');
    const statsBody = document.getElementById('stats-body');
    const clearBtn  = document.getElementById('tl-clear-btn');
    const dateClear = document.getElementById('tl-date-clear');

    let fetching       = false;
    let reqId          = 0;
    let statsReqId     = 0;
    let activeSystems  = [];
    let activeActTypes = [];
    let dateFrom       = '';
    let dateTo         = '';
    let fp             = null;
    let activeTab      = 'all';

    function localDateStr(d) {
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    }

    // ── Tab shortcuts ──
    window.setTab = function (tab) {
        ['all', 'conversations', 'activity'].forEach(k => {
            const btn = document.getElementById('tl-tab-' + k);
            if (!btn) return;
            if (k === tab) {
                btn.classList.add('border-brand-500', 'text-brand-700');
                btn.classList.remove('border-transparent', 'text-gray-400', 'hover:text-gray-700');
            } else {
                btn.classList.remove('border-brand-500', 'text-brand-700');
                btn.classList.add('border-transparent', 'text-gray-400', 'hover:text-gray-700');
            }
        });

        // Tab presets — clear everything then pre-select
        clearCheckboxes();
        activeSystems  = [];
        activeActTypes = [];
        activeTab      = tab;

        updateLabels();
        reload();
    };

    function clearCheckboxes() {
        const convAll = document.getElementById('tl-conv-all');
        if (convAll) convAll.checked = true;
        document.querySelectorAll('.tl-conv-item').forEach(c => c.checked = false);
        const actAll = document.getElementById('tl-act-all');
        if (actAll) actAll.checked = true;
        document.querySelectorAll('.tl-act-item').forEach(c => c.checked = false);
    }

    // ── Conversations dropdown ──
    window.tlConvAll = function (cb) {
        if (cb.checked) {
            activeSystems = [];
            document.querySelectorAll('.tl-conv-item').forEach(c => c.checked = false);
        } else {
            cb.checked = true;
        }
        updateLabels();
        reload();
    };

    window.tlConvItem = function (cb) {
        const allCb = document.getElementById('tl-conv-all');
        if (allCb) allCb.checked = false;
        activeSystems = Array.from(document.querySelectorAll('.tl-conv-item:checked')).map(c => c.value);
        if (!activeSystems.length) {
            activeSystems = [];
            if (allCb) allCb.checked = true;
        }
        updateLabels();
        reload();
    };

    // ── Activity type dropdown ──
    window.tlActAll = function (cb) {
        if (cb.checked) {
            activeActTypes = [];
            document.querySelectorAll('.tl-act-item').forEach(c => c.checked = false);
        } else {
            cb.checked = true;
        }
        updateLabels();
        reload();
    };

    window.tlActItem = function (cb) {
        const allCb = document.getElementById('tl-act-all');
        if (allCb) allCb.checked = false;
        activeActTypes = Array.from(document.querySelectorAll('.tl-act-item:checked')).map(c => c.value);
        if (!activeActTypes.length) {
            if (allCb) allCb.checked = true;
        }
        updateLabels();
        reload();
    };

    function updateLabels() {
        const convLabel = document.getElementById('tl-conv-label');
        if (convLabel) {
            convLabel.textContent = !activeSystems.length ? 'Channels'
                : (activeSystems.length === 1 ? activeSystems[0].split('|')[1] : activeSystems.length + ' channels');
        }
        const actLabel = document.getElementById('tl-act-label');
        if (actLabel) {
            actLabel.textContent = !activeActTypes.length ? 'Activity type'
                : (activeActTypes.length === 1 ? activeActTypes[0].replace(/_/g, ' ')
                : activeActTypes.length + ' types');
        }
    }

    // ── Dropdown toggle ──
    window.tlToggleDropdown = function (which, e) {
        e.stopPropagation();
        const menuId = which === 'conv' ? 'tl-conv-menu' : 'tl-act-menu';
        document.getElementById(menuId)?.classList.toggle('hidden');
    };
    document.addEventListener('click', () => {
        document.getElementById('tl-conv-menu')?.classList.add('hidden');
        document.getElementById('tl-act-menu')?.classList.add('hidden');
    });

    document.addEventListener('DOMContentLoaded', () => {
        fp = flatpickr('#tl-date-range', {
            mode: 'range',
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'j M Y',
            allowInput: false,
            onChange(selectedDates) {
                if (selectedDates.length === 2) {
                    dateFrom = localDateStr(selectedDates[0]);
                    dateTo   = localDateStr(selectedDates[1]);
                    dateClear.classList.remove('hidden');
                    reload();
                } else if (selectedDates.length === 0) {
                    dateFrom = dateTo = '';
                    dateClear.classList.add('hidden');
                    reload();
                }
            }
        });
    });

    function buildParams(cursor) {
        const p = new URLSearchParams();
        if (cursor)   p.set('cursor', cursor);
        if (dateFrom) p.set('from', dateFrom);
        if (dateTo)   p.set('to', dateTo);

        if (activeSystems.length) {
            // Channel filter → scope to conversations
            p.append('types[]', 'conversation');
            activeSystems.forEach(s => p.append('systems[]', s));
        } else if (activeActTypes.length) {
            activeActTypes.forEach(t => p.append('types[]', t));
        } else if (activeTab === 'conversations') {
            p.append('types[]', 'conversation');
        } else if (activeTab === 'activity') {
            p.set('exclude_type', 'conversation');
        }
        // else: 'all' tab — no type filter → show everything

        return p;
    }

    function hasFilters() {
        return activeSystems.length > 0 || activeActTypes.length > 0 || dateFrom || dateTo;
    }
    function updateClearBtn() { clearBtn.classList.toggle('hidden', !hasFilters()); }

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

    function loadStats() {
        const myId = ++statsReqId;
        const p = buildParams(null);
        p.delete('cursor');
        fetch('/activities/stats?' + p, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                if (myId !== statsReqId) return;
                statsBody.innerHTML = html;
            })
            .catch(() => {});
    }

    function reload() {
        const savedY = window.scrollY;
        const savedH = container.offsetHeight;
        container.style.minHeight = savedH + 'px';
        observer.disconnect();
        reqId++;
        container.innerHTML = '';
        fetching = false;
        updateClearBtn();
        loadMore(null);
        loadStats();
        window.scrollTo({ top: savedY, behavior: 'instant' });
        setTimeout(() => { container.style.minHeight = ''; }, 600);
    }

    window.clearDateFilter     = function () { fp?.clear(); };
    window.resetFilters        = function () {
        clearCheckboxes();
        activeSystems = [];
        activeActTypes = [];
        updateLabels();
        fp?.clear();
        // clear() triggers onChange which calls reload(); if no date was set, reload manually
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
