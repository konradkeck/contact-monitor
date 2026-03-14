@extends('layouts.app')
@section('title', 'People')

@section('content')
@php
    // SVG icon definitions for identity types — same stroke/fill pattern as companies
    $identityIcons = [
        'email' => [
            'stroke' => true,
            'd'      => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
            'cls'    => 'bg-sky-100 text-sky-700',
            'href'   => fn($v) => "mailto:{$v}",
            'title'  => 'Email',
        ],
        'slack_id' => [
            'stroke' => false,
            'd'      => 'M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52zm1.271 0a2.527 2.527 0 0 1 2.521-2.52 2.527 2.527 0 0 1 2.521 2.52v6.313A2.528 2.528 0 0 1 8.834 24a2.528 2.528 0 0 1-2.521-2.522v-6.313zM8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834 0a2.528 2.528 0 0 1 2.521 2.522v2.52H8.834zm0 1.271a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H2.522A2.528 2.528 0 0 1 0 8.834a2.528 2.528 0 0 1 2.522-2.521h6.312zm10.122 2.521a2.528 2.528 0 0 1 2.522-2.521A2.528 2.528 0 0 1 24 8.834a2.528 2.528 0 0 1-2.522 2.521h-2.522V8.834zm-1.268 0a2.528 2.528 0 0 1-2.523 2.521 2.527 2.527 0 0 1-2.52-2.521V2.522A2.527 2.527 0 0 1 15.165 0a2.528 2.528 0 0 1 2.523 2.522v6.312zm-2.523 10.122a2.528 2.528 0 0 1 2.523 2.522A2.528 2.528 0 0 1 15.165 24a2.527 2.527 0 0 1-2.52-2.522v-2.522h2.52zm0-1.268a2.527 2.527 0 0 1-2.52-2.523 2.526 2.526 0 0 1 2.52-2.52h6.313A2.527 2.527 0 0 1 24 15.165a2.528 2.528 0 0 1-2.522 2.523h-6.313z',
            'style'  => 'background:#4A154B',
            'cls'    => 'text-white',
            'href'   => null,
            'title'  => 'Slack',
        ],
        'discord_id' => [
            'stroke' => false,
            'd'      => 'M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.01.04.028.054a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994.021-.041.001-.09-.041-.106a13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z',
            'style'  => 'background:#5865F2',
            'cls'    => 'text-white',
            'href'   => null,
            'title'  => 'Discord',
        ],
        'phone' => [
            'stroke' => true,
            'd'      => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
            'cls'    => 'bg-green-100 text-green-700',
            'href'   => fn($v) => "tel:{$v}",
            'title'  => 'Phone',
        ],
        'linkedin' => [
            'stroke' => false,
            'd'      => 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
            'cls'    => 'bg-sky-50 text-sky-700',
            'href'   => fn($v) => str_starts_with($v, 'http') ? $v : "https://linkedin.com/in/{$v}",
            'title'  => 'LinkedIn',
        ],
        'twitter' => [
            'stroke' => false,
            'd'      => 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z',
            'cls'    => 'bg-gray-100 text-gray-800',
            'href'   => fn($v) => "https://x.com/{$v}",
            'title'  => 'Twitter/X',
        ],
    ];

    // Badge colors for contact activity types
    $contactBadge = [
        'ticket'       => 'bg-yellow-100 text-yellow-800',
        'conversation' => 'bg-purple-100 text-purple-800',
        'followup'     => 'bg-slate-100 text-slate-700',
    ];

    $sortLink = fn (string $col) =>
        route('people.index', array_merge(request()->query(), [
            'sort' => $col,
            'dir'  => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
        ]));
    $sortIcon = fn (string $col) =>
        $sort === $col ? ($dir === 'asc' ? '↑' : '↓') : '↕';
@endphp

<div class="page-header">
    <span class="page-title">People</span>
    <div class="flex items-center gap-2">
        @if($showFiltered)
            <a href="{{ request()->fullUrlWithQuery(['show_filtered' => null]) }}"
               class="btn btn-danger btn-sm">
                ← All People
            </a>
        @else
            <a href="{{ request()->fullUrlWithQuery(['show_filtered' => 1]) }}"
               class="btn btn-secondary btn-sm">
                Filtered
                @if($filteredCount > 0)
                    <span class="ml-1 inline-flex items-center justify-center bg-red-500 text-white text-xs font-bold rounded-full w-4 h-4 leading-none">
                        {{ $filteredCount }}
                    </span>
                @else
                    <span class="ml-1 text-xs text-gray-400">(0)</span>
                @endif
            </a>
        @endif
        <a href="{{ route('people.create') }}" class="btn btn-primary">+ New Person</a>
    </div>
</div>

<form method="GET" class="mb-4">
    <input type="hidden" name="sort" value="{{ $sort }}">
    <input type="hidden" name="dir"  value="{{ $dir }}">
    <div class="flex gap-2 max-w-sm">
        <input type="text" name="q" value="{{ $search }}" placeholder="Search by name or identity…" class="input" style="max-width:240px">
        <button type="submit" class="btn btn-secondary">Search</button>
        @if($search)
            <a href="{{ route('people.index', ['sort' => $sort, 'dir' => $dir]) }}" class="btn btn-muted">Clear</a>
        @endif
    </div>
</form>

<form id="people-bulk-form" method="POST" action="{{ route('filtering.contacts.bulk-add') }}">
@csrf
<div class="card overflow-hidden">
    {{-- Bulk action bar --}}
    <div id="people-bulk-bar" class="hidden items-center gap-3 px-4 py-2 border-b" style="background:#fff8e1; border-color:#fde68a">
        <span id="people-bulk-count" class="text-sm font-medium" style="color:#92400e"></span>
        <button type="button" onclick="peopleOpenFilterModal()" class="btn btn-danger btn-sm">Filter…</button>
        <button type="button" onclick="peopleOpenAssignCompanyModal()" class="btn btn-secondary btn-sm">Assign Company…</button>
        <button type="button" onclick="peopleBulkMarkOurOrg()" class="btn btn-secondary btn-sm">Mark as our company</button>
        <button type="button" onclick="peopleClearSelection()" class="text-xs text-gray-500 hover:text-gray-700">Clear</button>
    </div>
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th class="px-3 py-2.5 w-8">
                    <input type="checkbox" id="people-select-all" class="rounded border-gray-300 cursor-pointer"
                           onchange="peopleToggleAll(this)">
                </th>
                <th class="px-4 py-2.5 text-left w-56">
                    <a href="{{ $sortLink('first_name') }}" class="flex items-center justify-between gap-2 hover:text-gray-700">
                        Name <span class="shrink-0 opacity-50">{{ $sortIcon('first_name') }}</span>
                    </a>
                </th>
                <th class="px-4 py-2.5 text-left w-36">Communication</th>
                <th class="px-4 py-2.5 text-left">Companies</th>
                <th class="px-4 py-2.5 text-left w-[28rem]">
                    <a href="{{ $sortLink('updated_at') }}" class="flex items-center justify-between gap-2 hover:text-gray-700">
                        Last Contact <span class="shrink-0 opacity-50">{{ $sortIcon('updated_at') }}</span>
                    </a>
                </th>
                <th class="px-4 py-2.5 w-12"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($people as $person)
                @php
                    $lastConv = $person->last_conv;
                    $channelBadge = [
                        'email'   => 'bg-sky-100 text-sky-700',
                        'ticket'  => 'bg-amber-100 text-amber-700',
                        'slack'   => 'bg-purple-100 text-purple-700',
                        'discord' => 'bg-indigo-100 text-indigo-700',
                    ];
                    $lastConvBadge = $channelBadge[$lastConv?->channel_type ?? ''] ?? 'bg-slate-100 text-slate-700';
                @endphp
                <tr class="tbl-row">
                    <td class="px-3 py-3">
                        <input type="checkbox" name="ids[]" value="{{ $person->id }}"
                               class="people-row-check rounded border-gray-300 cursor-pointer"
                               onchange="peopleUpdateBulkBar()">
                    </td>

                    {{-- Name + note icon (always visible) --}}
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <a href="{{ route('people.show', $person) }}" class="shrink-0">
                                <x-person-avatar :person="$person" size="8" class="border border-gray-100 bg-gray-100" />
                            </a>
                            <a href="{{ route('people.show', $person) }}" class="font-medium text-brand-700 hover:underline truncate">
                                {{ $person->full_name }}
                            </a>
                            @if($showFiltered && isset($filteredReasons[$person->id]))
                                <span title="{{ $filteredReasons[$person->id] }}"
                                      class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-red-100 text-red-600 text-[10px] font-bold shrink-0 cursor-default leading-none">
                                    i
                                </span>
                            @endif
                            <div class="flex-1"></div>
                            <x-notes-popup :notes="$person->notes" linkable-type="person" :linkable-id="$person->id" :entity-name="$person->full_name" />
                        </div>
                    </td>

                    {{-- Communication icons --}}
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1">
                            @foreach($person->identities->take(6) as $identity)
                                @php
                                    $cfg  = $identityIcons[$identity->type] ?? null;
                                    $href = ($cfg && is_callable($cfg['href'] ?? null)) ? ($cfg['href'])($identity->value) : null;
                                @endphp
                                @if($cfg)
                                    @if($href)
                                        <a href="{{ $href }}" target="_blank" rel="noopener"
                                           title="{{ $cfg['title'] }}: {{ $identity->value }}"
                                           class="inline-flex items-center justify-center w-5 h-5 rounded text-xs shrink-0 {{ $cfg['cls'] }}"
                                           @if($cfg['style'] ?? null) style="{{ $cfg['style'] }}" @endif>
                                            <svg class="w-3 h-3" @if($cfg['stroke']) fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" @else fill="currentColor" @endif viewBox="0 0 24 24">
                                                <path d="{{ $cfg['d'] }}"/>
                                            </svg>
                                        </a>
                                    @else
                                        <span title="{{ $cfg['title'] }}: {{ $identity->value }}"
                                              class="inline-flex items-center justify-center w-5 h-5 rounded text-xs shrink-0 {{ $cfg['cls'] }}"
                                              @if($cfg['style'] ?? null) style="{{ $cfg['style'] }}" @endif>
                                            <svg class="w-3 h-3" @if($cfg['stroke']) fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" @else fill="currentColor" @endif viewBox="0 0 24 24">
                                                <path d="{{ $cfg['d'] }}"/>
                                            </svg>
                                        </span>
                                    @endif
                                @endif
                            @endforeach
                            @if($person->identities_count > 6)
                                <span class="text-xs text-gray-400 ml-0.5">+{{ $person->identities_count - 6 }}</span>
                            @endif
                        </div>
                    </td>

                    {{-- Companies (clickable) --}}
                    <td class="px-4 py-3 text-xs">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            @foreach($person->companies as $company)
                                <a href="{{ route('companies.show', $company) }}"
                                   class="text-brand-700 hover:underline whitespace-nowrap">{{ $company->name }}</a>
                            @endforeach
                            @if($person->companies->isEmpty())
                                <span class="text-gray-300">—</span>
                            @endif
                            <button type="button"
                                    onclick="peopleOpenAssignCompanyModal([{{ $person->id }}])"
                                    class="text-[10px] text-gray-400 hover:text-brand-600 transition border border-gray-200 hover:border-brand-300 rounded px-1 py-0 leading-4 cursor-pointer">
                                Assign
                            </button>
                        </div>
                    </td>

                    {{-- Last Contact: latest conversation message involving this person --}}
                    <td class="px-4 py-3">
                        @if($lastConv)
                            @if($lastConv->last_conv_id)
                                @php
                                    $modalSrc = route('conversations.modal', ['conversation' => $lastConv->last_conv_id]);
                                    if (!empty($lastConv->activity_date)) {
                                        $modalSrc .= '?date=' . $lastConv->activity_date;
                                    }
                                @endphp
                                <button type="button"
                                        onclick="openActivityModal(this)"
                                        data-modal-src="{{ $modalSrc }}"
                                        class="flex items-center gap-1.5 text-left hover:opacity-75 transition w-full">
                            @else
                                <div class="flex items-center gap-1.5">
                            @endif
                                <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium {{ $lastConvBadge }} shrink-0">
                                    {{ ucfirst($lastConv->channel_type) }}
                                </span>
                                <span class="text-xs text-gray-500 truncate"
                                      title="{{ $lastConv->conv_subject }}">{{ $lastConv->conv_subject ?: '—' }}</span>
                                <span class="text-xs text-gray-300 shrink-0"
                                      title="{{ \Carbon\Carbon::parse($lastConv->occurred_at)->format('D, j M Y H:i') }}">
                                    {{ \Carbon\Carbon::parse($lastConv->occurred_at)->diffForHumans() }}
                                </span>
                            @if($lastConv->last_conv_id)
                                </button>
                            @else
                                </div>
                            @endif
                        @else
                            <span class="text-xs text-gray-300">No contact</span>
                        @endif
                    </td>

                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <button type="button"
                                    onclick="peopleOpenFilterModal([{{ $person->id }}])"
                                    class="btn btn-sm btn-danger"
                                    title="Filter">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
                                Filter
                            </button>
                            @if($person->is_our_org)
                                <span class="btn btn-sm bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M4 21V7l8-4 8 4v14M9 21v-6h6v6"/></svg>
                                    Our Org
                                </span>
                            @else
                                <button type="button"
                                        onclick="peopleMarkOurOrg({{ $person->id }}, this)"
                                        class="btn btn-sm btn-muted"
                                        title="Mark as Our Organization">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M4 21V7l8-4 8 4v14M9 21v-6h6v6"/></svg>
                                    Our Org
                                </button>
                            @endif
                            <a href="{{ route('people.edit', $person) }}" class="text-xs text-gray-400 hover:text-gray-700 ml-1">Edit</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">No people found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($people->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $people->links() }}
        </div>
    @endif
</div>
</form>

<script>
function peopleUpdateBulkBar() {
    const checked = document.querySelectorAll('.people-row-check:checked');
    const bar = document.getElementById('people-bulk-bar');
    const count = document.getElementById('people-bulk-count');
    if (checked.length > 0) {
        bar.classList.remove('hidden');
        bar.classList.add('flex');
        count.textContent = checked.length + ' selected';
    } else {
        bar.classList.add('hidden');
        bar.classList.remove('flex');
    }
    document.getElementById('people-select-all').indeterminate =
        checked.length > 0 && checked.length < document.querySelectorAll('.people-row-check').length;
    document.getElementById('people-select-all').checked =
        checked.length === document.querySelectorAll('.people-row-check').length;
}
function peopleToggleAll(cb) {
    document.querySelectorAll('.people-row-check').forEach(c => c.checked = cb.checked);
    peopleUpdateBulkBar();
}
function peopleClearSelection() {
    document.querySelectorAll('.people-row-check, #people-select-all').forEach(c => c.checked = false);
    peopleUpdateBulkBar();
}
function peopleOpenFilterModal(ids) {
    if (!ids) ids = [...document.querySelectorAll('.people-row-check:checked')].map(c => c.value);
    if (!ids.length) return;
    const qs = ids.map(id => 'ids[]=' + id).join('&');
    openActivityModal({ dataset: { modalSrc: '{{ route('people.filter-modal') }}?' + qs } });
}
function peopleOpenAssignCompanyModal(ids) {
    if (!ids) ids = [...document.querySelectorAll('.people-row-check:checked')].map(c => c.value);
    if (!ids.length) return;
    const qs = ids.map(id => 'ids[]=' + id).join('&');
    openActivityModal({ dataset: { modalSrc: '{{ route('people.assign-company-modal') }}?' + qs } });
}
function peopleBulkMarkOurOrg() {
    const ids = [...document.querySelectorAll('.people-row-check:checked')].map(c => c.value);
    if (!ids.length) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('{{ route('people.bulk-mark-our-org') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ ids }),
    }).then(r => r.json()).then(d => { if (d.ok) { peopleClearSelection(); } })
    .catch(() => {});
}
function peopleMarkOurOrg(id, btn) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    btn.disabled = true;
    fetch(`/people/${id}/mark-our-org`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({}),
    }).then(r => r.json()).then(d => { if (d.ok) btn.style.color = '#3b82f6'; else btn.disabled = false; })
    .catch(() => { btn.disabled = false; });
}
</script>
@endsection
