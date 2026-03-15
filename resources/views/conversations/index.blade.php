@extends('layouts.app')
@section('title', 'Conversations')

@section('content')

{{-- ─── HEADER ─── --}}
<div class="page-header">
    <h1 class="page-title">Conversations</h1>
</div>

{{-- ─── TABS / COMPANY FILTER INDICATOR ─── --}}
@if($companyId)
    <div class="mb-5">
        <a href="{{ route('conversations.index') }}" class="text-sm text-gray-400 hover:text-gray-600">← All conversations</a>
        <span class="text-sm text-gray-500 ml-2">Filtered by company</span>
    </div>
@else
    <div class="flex gap-0 border-b border-gray-200 mb-5" role="tablist" aria-label="Conversation status">
        @foreach(['assigned' => 'Assigned', 'unassigned' => 'Unassigned', 'filtered' => 'Filtered'] as $tabKey => $tabLabel)
            <a href="{{ request()->fullUrlWithQuery(['tab' => $tabKey, 'page' => null]) }}"
               role="tab" aria-selected="{{ $tab === $tabKey ? 'true' : 'false' }}"
               class="px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition
                      {{ $tab === $tabKey ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                {{ $tabLabel }}
                <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-xs
                             {{ $tab === $tabKey ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ number_format($tabCounts[$tabKey]) }}
                </span>
            </a>
        @endforeach
    </div>
@endif

{{-- ─── SEARCH + FILTER FORM ─── --}}
<form method="GET" id="conv-filter-form">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="hidden" name="f_date_from" id="f-date-from" value="{{ $f_date_from }}">
    <input type="hidden" name="f_date_to" id="f-date-to" value="{{ $f_date_to }}">
    <div class="flex gap-2 mb-4 items-center">
        <button type="button" onclick="toggleConvFilterPanel()"
                class="btn {{ $activeConvFilterCount > 0 ? 'btn-primary' : 'btn-secondary' }}">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/>
            </svg>
            Filters
            @if($activeConvFilterCount > 0)
                <span class="ml-0.5 bg-white/25 text-white text-xs font-bold rounded-full w-4 h-4 flex items-center justify-center leading-none">
                    {{ $activeConvFilterCount }}
                </span>
            @endif
        </button>
        <input type="text" name="q" value="{{ $q }}" placeholder="Search by company or channel…"
               class="input max-w-[280px]">
        <button type="submit" class="btn btn-secondary">Search</button>
        @if($q || $activeConvFilterCount > 0)
            <a href="{{ route('conversations.index', array_filter(['tab' => $tab])) }}" class="btn btn-muted">Clear</a>
        @endif
    </div>
    {{-- Collapsible filter panel --}}
    <div id="conv-filter-panel" class="{{ $activeConvFilterCount > 0 ? '' : 'hidden' }} card p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {{-- Last message date range --}}
            <div class="min-w-0">
                <label class="label mb-1">Last message</label>
                <div id="conv-date-wrap" class="flex items-center gap-1.5">
                    <input id="conv-date-range" type="text" placeholder="Date range…" readonly
                           class="input cursor-pointer flex-1 min-w-0">
                    <button type="button" class="drp-clear {{ $f_date_from ? '' : 'hidden' }} text-base leading-none text-gray-400 hover:text-gray-600 px-1">×</button>
                </div>
            </div>
            {{-- Channels --}}
            @if($convSystems->isNotEmpty() && !$companyId)
            <div class="sm:col-span-1 lg:col-span-2 min-w-0">
                <label class="label mb-1">Channels</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($convSystems as $sys)
                        <label class="flex items-center gap-1.5 cursor-pointer select-none border border-gray-200 rounded-lg px-2 py-1 hover:border-gray-300 hover:bg-gray-50 transition text-xs
                                      {{ in_array($sys->channel_type . '|' . $sys->system_slug, $activeSystems) ? 'border-brand-400 bg-brand-50' : '' }}">
                            <input type="checkbox" name="systems[]"
                                   value="{{ $sys->channel_type }}|{{ $sys->system_slug }}"
                                   class="rounded border-gray-300"
                                   {{ in_array($sys->channel_type . '|' . $sys->system_slug, $activeSystems) ? 'checked' : '' }}>
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
        </div>
        <div class="mt-3 flex justify-end gap-2">
            @if($activeConvFilterCount > 0)
                <a href="{{ route('conversations.index', array_filter(['tab' => $tab])) }}"
                   class="btn btn-muted">Clear filters</a>
            @endif
            <button type="submit" class="btn btn-primary">Apply</button>
        </div>
    </div>
</form>

{{-- ─── TABLE ─── --}}
<form id="conv-bulk-form" method="POST" action="#">
@csrf
<div class="card overflow-hidden">
    {{-- Bulk action bar --}}
    <div id="conv-bulk-bar" class="hidden items-center gap-3 px-4 py-2 border-b bulk-bar">
        <span id="conv-bulk-count" class="text-sm font-medium bulk-bar-text" aria-live="polite"></span>
        @can('data_write')
        <button type="button" onclick="convOpenFilterModal()" class="btn btn-danger btn-sm">Filter…</button>
        @endcan
        <button type="button" onclick="convClearSelection()" class="text-xs text-gray-500 hover:text-gray-700">Clear</button>
    </div>
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th scope="col" class="px-3 py-2.5 w-8">
                    <input type="checkbox" id="conv-select-all" aria-label="Select all" class="rounded border-gray-300 cursor-pointer"
                           onchange="convToggleAll(this)">
                </th>
                <th scope="col" class="px-4 py-2.5 text-left">Channel</th>
                <th scope="col" class="px-4 py-2.5 text-left">Subject</th>
                <th scope="col" class="col-mobile-hidden px-4 py-2.5 text-left">Company</th>
                <th scope="col" class="px-4 py-2.5 text-left">People</th>
                <th scope="col" class="col-mobile-hidden px-4 py-2.5 text-left">Team</th>
                <th scope="col" class="col-mobile-hidden px-4 py-2.5 text-center">Msgs</th>
                <th scope="col" class="px-4 py-2.5"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($conversations as $conv)
                <tr class="tbl-row">
                    <td class="px-3 py-3">
                        <input type="checkbox" name="ids[]" value="{{ $conv->id }}"
                               class="conv-row-check rounded border-gray-300 cursor-pointer"
                               onchange="convUpdateBulkBar()">
                    </td>
                    {{-- Channel --}}
                    <td class="px-4 py-3">
                        <a href="{{ route('conversations.show', $conv) }}"
                           class="flex items-center gap-1.5 hover:underline">
                            <x-channel-badge :type="$conv->channel_type" :label="false" />
                            @if($conv->system_type && get_class(\App\Integrations\IntegrationRegistry::get($conv->system_type)) !== get_class(\App\Integrations\IntegrationRegistry::get($conv->channel_type)))
                                {!! \App\Integrations\IntegrationRegistry::get($conv->system_type)->iconHtml('w-4 h-4', false) !!}
                            @endif
                            <span class="hidden md:inline text-xs text-gray-700">{{ $conv->system_slug }}</span>
                        </a>
                    </td>

                    {{-- Subject + when --}}
                    <td class="px-4 py-3 max-w-[200px]">
                        @if($conv->subject)
                            <a href="{{ route('conversations.show', $conv) }}"
                               class="link text-xs truncate block" title="{{ $conv->subject }}">
                                {{ $conv->subject }}
                            </a>
                        @else
                            <a href="{{ route('conversations.show', $conv) }}"
                               class="text-gray-300 text-xs truncate block">—</a>
                        @endif
                        @if($conv->last_message_at)
                            <span class="block text-[10px] text-gray-400 mt-0.5">{{ $conv->last_message_at->diffForHumans() }}</span>
                        @endif
                    </td>

                    {{-- Company --}}
                    <td class="col-mobile-hidden px-4 py-3 max-w-[160px]">
                        @if($conv->company)
                            <a href="{{ route('companies.show', $conv->company) }}"
                               class="link text-xs truncate block">
                                {{ $conv->company->name }}
                            </a>
                        @else
                            <span class="text-gray-300 text-xs">—</span>
                        @endif
                    </td>

                    {{-- People (customer side) — max 2 shown, "+x" opens popup --}}
                    <td class="px-4 py-3">
                        @if(count(($convParticipants[$conv->id] ?? ['customer' => []])['customer']) > 0)
                            <div class="flex items-center -space-x-1.5">
                                @foreach(array_slice(($convParticipants[$conv->id] ?? ['customer' => []])['customer'], 0, 2) as $entry)
                                    <span title="{{ $entry['_title'] }}" class="relative inline-block">
                                        @if($entry['_imgSrc'])
                                            <img src="{{ $entry['_imgSrc'] }}" alt="{{ $entry['_title'] }}" class="w-7 h-7 rounded-full ring-2 ring-white object-cover">
                                        @else
                                            <span class="flex items-center justify-center w-7 h-7 rounded-full ring-2 ring-white text-xs font-semibold {{ $entry['_person'] ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-600' }}">{{ $entry['_label'] }}</span>
                                        @endif
                                    </span>
                                @endforeach
                                @if(count(($convParticipants[$conv->id] ?? ['customer' => []])['customer']) > 2)
                                    <button type="button" onclick="openConvPeoplePopup('conv-people-{{ $conv->id }}')"
                                            class="flex items-center justify-center w-7 h-7 rounded-full ring-2 ring-white text-xs font-semibold bg-gray-100 text-gray-500 hover:bg-gray-200 transition">
                                        +{{ count(($convParticipants[$conv->id] ?? ['customer' => []])['customer']) - 2 }}
                                    </button>
                                @endif
                            </div>
                        @else
                            <span class="text-gray-300 text-xs">—</span>
                        @endif
                    </td>

                    {{-- Team (internal side) --}}
                    <td class="col-mobile-hidden px-4 py-3">
                        @if(count(($convParticipants[$conv->id] ?? ['team' => []])['team']) > 0)
                            <div class="flex items-center -space-x-1.5">
                                @foreach(array_slice(($convParticipants[$conv->id])['team'], 0, 4) as $entry)
                                    <span title="{{ $entry['_title'] }}" class="relative inline-block">
                                        @if($entry['_imgSrc'])
                                            <img src="{{ $entry['_imgSrc'] }}"
                                                 alt="{{ $entry['_title'] }}"
                                                 class="w-7 h-7 rounded-full ring-2 ring-white object-cover">
                                        @else
                                            <span class="flex items-center justify-center w-7 h-7 rounded-full ring-2 ring-white text-xs font-semibold bg-violet-100 text-violet-700">
                                                {{ $entry['_label'] }}
                                            </span>
                                        @endif
                                    </span>
                                @endforeach
                                @if(count(($convParticipants[$conv->id])['team']) > 4)
                                    <span title="{{ implode(', ', array_map(fn($e) => $e['display_name'], array_slice(($convParticipants[$conv->id])['team'], 4))) }}"
                                          class="flex items-center justify-center w-7 h-7 rounded-full ring-2 ring-white text-xs font-semibold bg-gray-100 text-gray-500 cursor-default">
                                        +{{ count(($convParticipants[$conv->id])['team']) - 4 }}
                                    </span>
                                @endif
                            </div>
                        @else
                            <span class="text-gray-300">---</span>
                        @endif
                    </td>

                    {{-- Messages --}}
                    <td class="col-mobile-hidden px-4 py-3 text-center text-gray-500 tabular-nums">{{ $conv->message_count }}</td>

                    {{-- Actions --}}
                    <td class="px-4 py-3 text-right">
                        {{-- Desktop --}}
                        <div class="row-actions-desktop items-center justify-end gap-1.5">
                            @can('data_write')
                            <button type="button"
                                    onclick="openFilterModalFor([{{ $conv->id }}])"
                                    class="btn btn-sm btn-danger"
                                    title="Filter">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
                                Filter
                            </button>
                            @endcan
                        </div>
                        {{-- Mobile "..." dropdown --}}
                        <div class="row-actions-mobile relative" x-data="{ open: false }" @click.outside="open = false" @close-row-dropdowns.window="open = false">
                            <button @click="let o=open; $dispatch('close-row-dropdowns'); open=!o"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition font-bold text-base">
                                ···
                            </button>
                            <div x-show="open" x-cloak
                                 class="absolute right-0 top-full mt-1 w-32 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50 text-sm">
                                @can('data_write')
                                <button type="button" onclick="openFilterModalFor([{{ $conv->id }}])"
                                        class="flex w-full px-3 py-2 text-red-600 hover:bg-red-50 transition text-xs text-left">Filter</button>
                                @endcan
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center empty-state italic">No conversations.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if($conversations->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $conversations->links() }}</div>
    @endif
</div>
</form>

{{-- People popups --}}
<div id="cv-popup-backdrop" class="hidden fixed inset-0 bg-black/40 z-40" onclick="closeConvPeoplePopups()"></div>
@foreach($conversations as $conv)
    @if(count(($convParticipants[$conv->id] ?? ['customer' => []])['customer']) > 2)
        <div id="conv-people-{{ $conv->id }}"
             class="hidden fixed z-50 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
                    w-[320px] bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800 text-sm">Participants</h3>
                <button type="button" onclick="closeConvPeoplePopups()"
                        class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
            </div>
            <ul class="divide-y divide-gray-50 max-h-72 overflow-y-auto">
                @foreach(($convParticipants[$conv->id] ?? ['customer' => []])['customer'] as $entry)
                    <li class="flex items-center gap-3 px-4 py-2.5">
                        @if($entry['_imgSrc'])
                            <img src="{{ $entry['_imgSrc'] }}" alt="{{ $entry['_title'] }}" class="w-8 h-8 rounded-full object-cover shrink-0">
                        @else
                            <span class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold shrink-0 {{ $entry['_person'] ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-600' }}">{{ $entry['_label'] }}</span>
                        @endif
                        @if($entry['_person'])
                            <a href="{{ route('people.show', $entry['_person']) }}" class="text-sm text-gray-700 hover:text-brand-700 truncate">{{ $entry['_title'] }}</a>
                        @else
                            <span class="text-sm text-gray-700 truncate">{{ $entry['_title'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endforeach

<script>
function convUpdateBulkBar() {
    const checked = document.querySelectorAll('.conv-row-check:checked');
    const bar = document.getElementById('conv-bulk-bar');
    const count = document.getElementById('conv-bulk-count');
    if (checked.length > 0) {
        bar.classList.remove('hidden'); bar.classList.add('flex');
        count.textContent = checked.length + ' selected';
    } else {
        bar.classList.add('hidden'); bar.classList.remove('flex');
    }
    const all = document.getElementById('conv-select-all');
    const total = document.querySelectorAll('.conv-row-check').length;
    all.indeterminate = checked.length > 0 && checked.length < total;
    all.checked = checked.length === total && total > 0;
}
function convToggleAll(cb) {
    document.querySelectorAll('.conv-row-check').forEach(c => c.checked = cb.checked);
    convUpdateBulkBar();
}
function convClearSelection() {
    document.querySelectorAll('.conv-row-check, #conv-select-all').forEach(c => c.checked = false);
    convUpdateBulkBar();
}
function convOpenFilterModal(ids) {
    if (!ids) ids = [...document.querySelectorAll('.conv-row-check:checked')].map(c => c.value);
    if (!ids.length) return;
    const qs = ids.map(id => 'ids[]=' + encodeURIComponent(id)).join('&');
    const src = '{{ route('conversations.filter-modal') }}?' + qs;
    openActivityModal({ dataset: { modalSrc: src } });
}
function openConvPeoplePopup(id) {
    document.querySelectorAll('[id^="conv-people-"]').forEach(el => el.classList.add('hidden'));
    document.getElementById(id)?.classList.remove('hidden');
    document.getElementById('cv-popup-backdrop')?.classList.remove('hidden');
}
function closeConvPeoplePopups() {
    document.querySelectorAll('[id^="conv-people-"]').forEach(el => el.classList.add('hidden'));
    document.getElementById('cv-popup-backdrop')?.classList.add('hidden');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeConvPeoplePopups(); });

window.toggleConvFilterPanel = function() {
    document.getElementById('conv-filter-panel')?.classList.toggle('hidden');
};
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('conv-date-range')) {
        drp.init('conv-date-range', function(from, to) {
            document.getElementById('f-date-from').value = from;
            document.getElementById('f-date-to').value = to;
        }, {
            defaultFrom: '{{ $f_date_from }}',
            defaultTo: '{{ $f_date_to }}',
        });
    }
});

function openFilterModalFor(ids) {
    convOpenFilterModal(ids);
}

</script>

@endsection
