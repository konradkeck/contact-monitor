@extends('layouts.app')
@section('title', 'Conversations')

@section('content')

{{-- ─── HEADER ─── --}}
<div class="page-header">
    <span class="page-title">Conversations</span>
</div>

{{-- ─── SEARCH ─── --}}
<form method="GET" class="mb-4 flex gap-2 items-center">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="text" name="q" value="{{ $q }}" placeholder="Search by company or channel…"
           class="input" style="max-width:280px">
    <button type="submit" class="btn btn-secondary">Search</button>
    @if($q)
        <a href="{{ request()->fullUrlWithQuery(['q' => '', 'page' => null]) }}" class="btn btn-muted">Clear</a>
    @endif
</form>

{{-- ─── TABS / COMPANY FILTER INDICATOR ─── --}}
@if($companyId)
    <div class="mb-5">
        <a href="{{ route('conversations.index') }}" class="text-sm text-gray-400 hover:text-gray-600">← All conversations</a>
        <span class="text-sm text-gray-500 ml-2">Filtered by company</span>
    </div>
@else
    <div class="flex gap-0 border-b border-gray-200 mb-5" role="tablist" aria-label="Conversation status">
        @foreach(['unassigned' => 'Unassigned', 'assigned' => 'Assigned', 'filtered' => 'Filtered'] as $tabKey => $tabLabel)
            @php $isActive = $tab === $tabKey; @endphp
            <a href="{{ request()->fullUrlWithQuery(['tab' => $tabKey, 'page' => null]) }}"
               role="tab" aria-selected="{{ $isActive ? 'true' : 'false' }}"
               class="px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition
                      {{ $isActive ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                {{ $tabLabel }}
                <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-xs
                             {{ $isActive ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ number_format($tabCounts[$tabKey]) }}
                </span>
            </a>
        @endforeach
    </div>
@endif

{{-- ─── FILTER BAR ─── --}}
@if($convSystems->isNotEmpty() && !$companyId)
<div class="flex flex-wrap items-center gap-3 mb-3">

    {{-- Channels dropdown --}}
    <div class="relative" id="cv-conv-wrapper">
        <button onclick="cvToggle(event)"
                class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 bg-white
                       text-xs text-gray-600 hover:border-gray-300 transition min-w-[130px]">
            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/>
            </svg>
            <span id="cv-conv-label" class="flex-1 text-left">
                @if(count($activeSystems) === 1)
                    {{ explode('|', $activeSystems[0])[1] ?? 'Channels' }}
                @elseif(count($activeSystems) > 1)
                    {{ count($activeSystems) }} channels
                @else
                    Channels
                @endif
            </span>
            <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div id="cv-conv-menu" class="hidden absolute left-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1.5 w-64">
            <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                <input type="checkbox" id="cv-conv-all" class="rounded border-gray-300"
                       {{ empty($activeSystems) ? 'checked' : '' }}
                       onchange="cvAll(this)">
                <span class="text-sm text-gray-700 font-medium">All channels</span>
            </label>
            <div class="border-t border-gray-100 my-1"></div>
            @foreach($convSystems as $sys)
                @php
                    $val = $sys->channel_type . '|' . $sys->system_slug;
                    $sysIntCls = get_class(\App\Integrations\IntegrationRegistry::get($sys->system_type ?? ''));
                    $chnIntCls = get_class(\App\Integrations\IntegrationRegistry::get($sys->channel_type));
                    $showSysLogo = $sys->system_type && $sysIntCls !== $chnIntCls;
                @endphp
                <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                    <input type="checkbox" class="cv-item rounded border-gray-300"
                           value="{{ $val }}"
                           {{ in_array($val, $activeSystems) ? 'checked' : '' }}
                           onchange="cvItem()">
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

    @if(count($activeSystems) > 0)
        <a href="{{ request()->fullUrlWithQuery(['systems' => null, 'page' => null]) }}"
           class="text-xs text-gray-400 hover:text-gray-600 transition">✕ Clear</a>
    @endif

</div>
@endif

{{-- ─── TABLE ─── --}}
<div class="card overflow-hidden">
    {{-- Bulk action bar (hidden until selection) --}}
    <div id="bulk-bar" class="hidden items-center gap-3 px-4 py-2 border-b" style="background:#fff8e1; border-color:#fde68a">
        <span id="bulk-count" class="text-sm font-medium" style="color:#92400e"></span>
        <button type="button" onclick="openFilterModal()" class="btn btn-danger btn-sm">Filter…</button>
        <button type="button" onclick="clearSelection()" class="text-xs text-gray-500 hover:text-gray-700">Clear</button>
    </div>
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th class="px-3 py-2.5 w-8">
                    <input type="checkbox" id="select-all" class="rounded border-gray-300 cursor-pointer"
                           onchange="toggleAll(this)">
                </th>
                <th class="px-4 py-2.5 text-left">Channel</th>
                <th class="px-4 py-2.5 text-left">Company</th>
                <th class="px-4 py-2.5 text-left">People</th>
                <th class="px-4 py-2.5 text-left">Team</th>
                <th class="px-4 py-2.5 text-center">Msgs</th>
                <th class="px-4 py-2.5 text-left">Last activity</th>
                <th class="px-4 py-2.5"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($conversations as $conv)
                @php
                    $parts    = $convParticipants[$conv->id] ?? ['customer' => [], 'team' => []];
                    $customer = $parts['customer'];
                    $team     = $parts['team'];
                @endphp
                <tr class="tbl-row">
                    {{-- Checkbox --}}
                    <td class="px-3 py-3">
                        <input type="checkbox" name="ids[]" value="{{ $conv->id }}"
                               class="row-check rounded border-gray-300 cursor-pointer"
                               onchange="updateBulkBar()">
                    </td>

                    {{-- Channel --}}
                    @php
                        $sysIntCls = get_class(\App\Integrations\IntegrationRegistry::get($conv->system_type ?? ''));
                        $chnIntCls = get_class(\App\Integrations\IntegrationRegistry::get($conv->channel_type));
                        $showSysLogo = $conv->system_type && $sysIntCls !== $chnIntCls;
                    @endphp
                    <td class="px-4 py-3">
                        <a href="{{ route('conversations.show', $conv) }}"
                           class="flex items-center gap-1.5 hover:underline">
                            <x-channel-badge :type="$conv->channel_type" :label="false" />
                            @if($showSysLogo)
                                {!! \App\Integrations\IntegrationRegistry::get($conv->system_type)->iconHtml('w-4 h-4', false) !!}
                            @endif
                            <span class="text-xs text-gray-700">{{ $conv->system_slug }}</span>
                        </a>
                    </td>

                    {{-- Company --}}
                    <td class="px-4 py-3 font-medium max-w-[180px]">
                        @if($conv->company)
                            <a href="{{ route('companies.show', $conv->company) }}"
                               class="text-brand-700 hover:underline truncate block">
                                {{ $conv->company->name }}
                            </a>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>

                    {{-- People (customer side) --}}
                    <td class="px-4 py-3">
                        @if(count($customer) > 0)
                            @php
                                $shown  = array_slice($customer, 0, 4);
                                $hidden = array_slice($customer, 4);
                                $hiddenNames = implode(', ', array_map(fn($e) => $e['display_name'], $hidden));
                            @endphp
                            <div class="flex items-center -space-x-1.5">
                                @foreach($shown as $entry)
                                    @php
                                        $person   = $entry['identity']?->person;
                                        $label    = $person ? $person->initials() : mb_strtoupper(mb_substr($entry['display_name'] ?? '?', 0, 2));
                                        $title    = $person ? trim($person->first_name . ' ' . $person->last_name) : ($entry['display_name'] ?? '');
                                        $imgSrc   = $entry['avatar_url'] ?? ($entry['gravatar_hash'] ? 'https://www.gravatar.com/avatar/' . $entry['gravatar_hash'] . '?d=identicon&s=56' : null);
                                    @endphp
                                    <span title="{{ $title }}" class="relative inline-block">
                                        @if($imgSrc)
                                            <img src="{{ $imgSrc }}"
                                                 alt="{{ $title }}"
                                                 class="w-7 h-7 rounded-full ring-2 ring-white object-cover">
                                        @else
                                            <span class="flex items-center justify-center w-7 h-7 rounded-full ring-2 ring-white text-xs font-semibold
                                                         {{ $person ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-600' }}">
                                                {{ $label }}
                                            </span>
                                        @endif
                                    </span>
                                @endforeach
                                @if(count($hidden) > 0)
                                    <span title="{{ $hiddenNames }}"
                                          class="flex items-center justify-center w-7 h-7 rounded-full ring-2 ring-white text-xs font-semibold bg-gray-100 text-gray-500 cursor-default">
                                        +{{ count($hidden) }}
                                    </span>
                                @endif
                            </div>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>

                    {{-- Team (internal side) --}}
                    <td class="px-4 py-3">
                        @if(count($team) > 0)
                            @php
                                $shownT  = array_slice($team, 0, 4);
                                $hiddenT = array_slice($team, 4);
                                $hiddenTNames = implode(', ', array_map(fn($e) => $e['display_name'], $hiddenT));
                            @endphp
                            <div class="flex items-center -space-x-1.5">
                                @foreach($shownT as $entry)
                                    @php
                                        $person   = $entry['identity']?->person;
                                        $label    = $person ? $person->initials() : mb_strtoupper(mb_substr($entry['display_name'] ?? '?', 0, 2));
                                        $title    = $person ? trim($person->first_name . ' ' . $person->last_name) : ($entry['display_name'] ?? '');
                                        $imgSrc   = $entry['avatar_url'] ?? ($entry['gravatar_hash'] ? 'https://www.gravatar.com/avatar/' . $entry['gravatar_hash'] . '?d=identicon&s=56' : null);
                                    @endphp
                                    <span title="{{ $title }}" class="relative inline-block">
                                        @if($imgSrc)
                                            <img src="{{ $imgSrc }}"
                                                 alt="{{ $title }}"
                                                 class="w-7 h-7 rounded-full ring-2 ring-white object-cover">
                                        @else
                                            <span class="flex items-center justify-center w-7 h-7 rounded-full ring-2 ring-white text-xs font-semibold bg-violet-100 text-violet-700">
                                                {{ $label }}
                                            </span>
                                        @endif
                                    </span>
                                @endforeach
                                @if(count($hiddenT) > 0)
                                    <span title="{{ $hiddenTNames }}"
                                          class="flex items-center justify-center w-7 h-7 rounded-full ring-2 ring-white text-xs font-semibold bg-gray-100 text-gray-500 cursor-default">
                                        +{{ count($hiddenT) }}
                                    </span>
                                @endif
                            </div>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>

                    {{-- Messages --}}
                    <td class="px-4 py-3 text-center text-gray-500 tabular-nums">{{ $conv->message_count }}</td>

                    {{-- Last activity --}}
                    <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">
                        {{ $conv->last_message_at?->diffForHumans() ?? '—' }}
                    </td>

                    {{-- Actions --}}
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('conversations.show', $conv) }}"
                           class="text-xs text-brand-600 hover:underline mr-2">View</a>
                        <button type="button"
                                onclick="openFilterModalFor([{{ $conv->id }}])"
                                class="text-xs text-gray-400 hover:text-red-600 transition"
                                title="Filter">
                            <svg xmlns="http://www.w3.org/2000/svg" class="inline w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-400 italic">No conversations.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if($conversations->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $conversations->links() }}</div>
    @endif
</div>

<script>
function updateBulkBar() {
    const checked = document.querySelectorAll('.row-check:checked');
    const bar = document.getElementById('bulk-bar');
    const count = document.getElementById('bulk-count');
    if (checked.length > 0) {
        bar.classList.remove('hidden');
        bar.classList.add('flex');
        count.textContent = checked.length + ' selected';
    } else {
        bar.classList.add('hidden');
        bar.classList.remove('flex');
    }
    document.getElementById('select-all').indeterminate =
        checked.length > 0 && checked.length < document.querySelectorAll('.row-check').length;
    document.getElementById('select-all').checked =
        checked.length === document.querySelectorAll('.row-check').length;
}
function toggleAll(cb) {
    document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked);
    updateBulkBar();
}
function clearSelection() {
    document.querySelectorAll('.row-check, #select-all').forEach(c => c.checked = false);
    updateBulkBar();
}

function openFilterModal() {
    const ids = Array.from(document.querySelectorAll('.row-check:checked')).map(c => c.value);
    openFilterModalFor(ids);
}

function openFilterModalFor(ids) {
    if (!ids || ids.length === 0) return;
    const qs = ids.map(id => 'ids[]=' + encodeURIComponent(id)).join('&');
    const src = '{{ route('conversations.filter-modal') }}?' + qs;
    openActivityModal({ dataset: { modalSrc: src } });
}

// ── Channel filter dropdown ──
function cvNavigate() {
    const checked = Array.from(document.querySelectorAll('.cv-item:checked')).map(c => c.value);
    const url = new URL(window.location.href);
    url.searchParams.delete('systems[]');
    url.searchParams.delete('page');
    checked.forEach(v => url.searchParams.append('systems[]', v));
    window.location.href = url.toString();
}
window.cvAll = function(cb) {
    if (cb.checked) {
        document.querySelectorAll('.cv-item').forEach(c => c.checked = false);
        const url = new URL(window.location.href);
        url.searchParams.delete('systems[]');
        url.searchParams.delete('page');
        window.location.href = url.toString();
    } else {
        cb.checked = true;
    }
};
window.cvItem = function() {
    const allCb = document.getElementById('cv-conv-all');
    const checked = document.querySelectorAll('.cv-item:checked');
    if (allCb) allCb.checked = checked.length === 0;
    cvNavigate();
};
window.cvToggle = function(e) {
    e.stopPropagation();
    document.getElementById('cv-conv-menu')?.classList.toggle('hidden');
};
document.addEventListener('click', () => {
    document.getElementById('cv-conv-menu')?.classList.add('hidden');
});
</script>

@endsection
