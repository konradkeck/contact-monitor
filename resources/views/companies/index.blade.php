@extends('layouts.app')
@section('title', 'Companies')

@section('content')

@php
    $scoreColorMap = [
        1  => '#ef4444', 2  => '#f97316', 3  => '#f59e0b', 4  => '#eab308',
        5  => '#84cc16', 6  => '#4ade80', 7  => '#22c55e', 8  => '#16a34a',
        9  => '#15803d', 10 => '#166534',
    ];

    // Conv type → badge color + icon key
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
    // icon definitions: stroke-based (Heroicons) or fill-based (brand icons)
    $convIcons = [
        'mail'   => ['stroke' => true,  'd' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
        'ticket' => ['stroke' => true,  'd' => 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z'],
        'chat'   => ['stroke' => true,  'd' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
        'phone'  => ['stroke' => true,  'd' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
        // Brand icons (Simple Icons, fill-based, viewBox 0 0 24 24)
        'slack'  => ['stroke' => false, 'd' => 'M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52zm1.271 0a2.527 2.527 0 0 1 2.521-2.52 2.527 2.527 0 0 1 2.521 2.52v6.313A2.528 2.528 0 0 1 8.834 24a2.528 2.528 0 0 1-2.521-2.522v-6.313zM8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834 0a2.528 2.528 0 0 1 2.521 2.522v2.52H8.834zm0 1.271a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H2.522A2.528 2.528 0 0 1 0 8.834a2.528 2.528 0 0 1 2.522-2.521h6.312zm10.122 2.521a2.528 2.528 0 0 1 2.522-2.521A2.528 2.528 0 0 1 24 8.834a2.528 2.528 0 0 1-2.522 2.521h-2.522V8.834zm-1.268 0a2.528 2.528 0 0 1-2.523 2.521 2.527 2.527 0 0 1-2.52-2.521V2.522A2.527 2.527 0 0 1 15.165 0a2.528 2.528 0 0 1 2.523 2.522v6.312zm-2.523 10.122a2.528 2.528 0 0 1 2.523 2.522A2.528 2.528 0 0 1 15.165 24a2.527 2.527 0 0 1-2.52-2.522v-2.522h2.52zm0-1.268a2.527 2.527 0 0 1-2.52-2.523 2.526 2.526 0 0 1 2.52-2.52h6.313A2.527 2.527 0 0 1 24 15.165a2.528 2.528 0 0 1-2.522 2.523h-6.313z'],
        'discord'=> ['stroke' => false, 'd' => 'M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.01.04.028.054a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994.021-.041.001-.09-.041-.106a13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z'],
    ];

    $sortUrl = fn($col) => route('companies.index', array_merge(request()->query(), [
        'sort' => $col,
        'dir'  => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
    ]));
    $sortIcon = fn($col) => $sort === $col
        ? ($dir === 'asc' ? ' ↑' : ' ↓')
        : '';

    // Full date formatter for title attributes
    $fmtDate = fn($dt) => $dt?->format('D, j M Y \a\t H:i') ?? '';

    // Count active filters
    $activeFilterCount = collect(['f_domain','f_people_min','f_conv_type','f_updated_from','f_updated_to'])
        ->filter(fn($k) => (string) request($k) !== '')
        ->count();
    foreach ($brandProducts as $bp) {
        foreach (["f_bp_{$bp->id}_stage", "f_bp_{$bp->id}_score_min", "f_bp_{$bp->id}_score_max"] as $k) {
            if ((string) request($k) !== '') $activeFilterCount++;
        }
    }
    $hasFilters = $search || $activeFilterCount > 0;
@endphp

<div class="page-header">
    <span class="page-title">Companies</span>
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
                    <span class="ml-1 inline-flex items-center justify-center bg-red-500 text-white text-xs font-bold rounded-full w-4 h-4 leading-none">
                        {{ $filteredCount }}
                    </span>
                @else
                    <span class="ml-1 text-xs text-gray-400">(0)</span>
                @endif
            </a>
        @endif
        <a href="{{ route('companies.create') }}" class="btn btn-primary">+ New Company</a>
    </div>
</div>

<form method="GET" id="filter-form">
    <input type="hidden" name="sort" value="{{ $sort }}">
    <input type="hidden" name="dir"  value="{{ $dir }}">

    {{-- Search + Filter toggle --}}
    <div class="flex gap-2 mb-4 items-center">
        <div class="flex gap-2 flex-1 max-w-md">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search by name, domain, alias…"
                   class="input" style="max-width:280px">
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
                @php $bpLabel = $bp->name . ($bp->variant ? ' · '.$bp->variant : ''); @endphp
                <div>
                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">
                        {{ $bpLabel }} — stage
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
                        {{ $bpLabel }} — score
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
        <div id="companies-bulk-bar" class="hidden items-center gap-3 px-4 py-2 border-b" style="background:#fff8e1; border-color:#fde68a">
            <span id="companies-bulk-count" class="text-sm font-medium" style="color:#92400e"></span>
            <button type="button" onclick="companiesOpenFilterModal()" class="btn btn-danger btn-sm">Filter…</button>
            <button type="button" onclick="companiesClearSelection()" class="text-xs text-gray-500 hover:text-gray-700">Clear</button>
        </div>
        @php
            $tableWidth = 36 + 220 + 160 + 150 + ($brandProducts->isEmpty() ? 320 : $brandProducts->count() * 160) + 110 + 120;
        @endphp
        <div class="overflow-x-auto">
        <table class="text-sm table-fixed" style="width:{{ $tableWidth }}px; min-width:100%">
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
                    <th class="px-3 py-2.5 w-8">
                        <input type="checkbox" id="companies-select-all" class="rounded border-gray-300 cursor-pointer"
                               onchange="companiesToggleAll(this)">
                    </th>
                    <th class="px-4 py-2.5 text-left">
                        <a href="{{ $sortUrl('name') }}" class="flex items-center justify-between gap-2 hover:text-gray-900">
                            <span>Company</span><span class="shrink-0 opacity-60">{{ $sortIcon('name') }}</span>
                        </a>
                    </th>
                    <th class="px-4 py-2.5 text-left">
                        <a href="{{ $sortUrl('domain') }}" class="flex items-center justify-between gap-2 hover:text-gray-900">
                            <span>Domain</span><span class="shrink-0 opacity-60">{{ $sortIcon('domain') }}</span>
                        </a>
                    </th>
                    <th class="px-4 py-2.5 text-left">
                        <a href="{{ $sortUrl('contacts') }}" class="flex items-center justify-between gap-2 hover:text-gray-900">
                            <span>Contacts</span><span class="shrink-0 opacity-60">{{ $sortIcon('contacts') }}</span>
                        </a>
                    </th>
                    @if($brandProducts->isEmpty())
                        <th class="px-4 py-2.5 text-left">
                            <span class="text-xs text-gray-500 font-normal italic">
                                Configure <a href="{{ route('segmentation.index') }}"
                                             class="underline hover:text-gray-700 transition">Segmentation</a> to evaluate
                            </span>
                        </th>
                    @else
                        @foreach($brandProducts as $bp)
                            <th class="px-2 py-2.5 text-left">
                                <a href="{{ $sortUrl('bp_score_'.$bp->id) }}"
                                   class="flex items-center justify-between gap-1 hover:text-gray-900 text-xs">
                                    <span class="leading-tight truncate">{{ $bp->name }}{{ $bp->variant ? ' · '.$bp->variant : '' }}</span>
                                    <span class="shrink-0 opacity-60">{{ $sortIcon('bp_score_'.$bp->id) }}</span>
                                </a>
                            </th>
                        @endforeach
                    @endif
                    <th class="px-4 py-2.5 text-left">
                        <a href="{{ $sortUrl('updated_at') }}" class="flex items-center justify-between gap-2 hover:text-gray-900">
                            <span>Updated</span><span class="shrink-0 opacity-60">{{ $sortIcon('updated_at') }}</span>
                        </a>
                    </th>
                    <th class="px-4 py-2.5 text-left">
                        <a href="{{ $sortUrl('last_conv') }}" class="flex items-center justify-between gap-2 hover:text-gray-900">
                            <span>Channels</span><span class="shrink-0 opacity-60">{{ $sortIcon('last_conv') }}</span>
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse($companies as $company)
                    @php
                        $primaryDomain = $company->domains->firstWhere('is_primary', true) ?? $company->domains->first();
                        $extraDomains  = $company->domains->filter(fn($d) => $d->id !== $primaryDomain?->id);
                        $contacts      = $company->people->filter(fn($p) => !$p->identities->contains('is_team_member', true));
                        $totalContacts = $contacts->count();
                        $visiblePeople = $totalContacts > 5 ? $contacts->take(4) : $contacts;
                        $extraPeople   = $totalContacts > 5 ? $totalContacts - 4 : 0;
                        // Unique channel types for conv icons
                        $convChannels  = $company->conversations->unique('channel_type')->values();
                    @endphp

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
                                @php $nonPrimaryAliases = $company->aliases->filter(fn($a) => !$a->is_primary); @endphp
                                @if($nonPrimaryAliases->isNotEmpty())
                                    <div class="relative group inline-block shrink-0">
                                        <span class="text-xs text-gray-400 cursor-default leading-none">
                                            +{{ $nonPrimaryAliases->count() }}
                                        </span>
                                        <div class="absolute left-0 top-full mt-1 bg-gray-900 text-white text-xs
                                                    rounded-lg px-3 py-2 invisible opacity-0
                                                    group-hover:visible group-hover:opacity-100 transition z-30
                                                    min-w-max shadow-lg space-y-0.5 pointer-events-none">
                                            @foreach($nonPrimaryAliases as $alias)
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
                                        title="Filtered"
                                        class="shrink-0 text-xs text-gray-300 hover:text-red-500 transition leading-none opacity-0 group-hover/row:opacity-100 focus:opacity-100">
                                    🚫
                                </button>
                                <x-notes-popup :notes="$company->notes" linkable-type="company" :linkable-id="$company->id" :entity-name="$company->name" />
                            </div>
                        </td>

                        {{-- Domain: primary + hover tooltip for extra domains --}}
                        <td class="px-4 py-3 overflow-hidden">
                            @if($primaryDomain)
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="font-mono text-xs text-gray-600 truncate" title="{{ $primaryDomain->domain }}">{{ $primaryDomain->domain }}</span>
                                    @if($extraDomains->isNotEmpty())
                                        <div class="relative group inline-block shrink-0">
                                            <span class="text-xs text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded font-medium leading-none cursor-default">
                                                +{{ $extraDomains->count() }}
                                            </span>
                                            <div class="absolute left-0 top-full mt-1 bg-gray-900 text-white text-xs
                                                        rounded-lg px-3 py-2 invisible opacity-0
                                                        group-hover:visible group-hover:opacity-100 transition z-30
                                                        min-w-max shadow-lg space-y-0.5 pointer-events-none">
                                                @foreach($extraDomains as $d)
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
                            @if($contacts->isEmpty())
                                <span class="text-gray-300 text-xs">—</span>
                            @else
                                <button type="button" onclick="openPopup('popup-people-{{ $company->id }}')"
                                        class="flex items-center justify-start cursor-pointer group">
                                    @foreach($visiblePeople as $i => $person)
                                        <div class="w-7 h-7 rounded-full bg-brand-100 text-brand-700 border-2 border-white
                                                    flex items-center justify-center text-xs font-bold shrink-0
                                                    group-hover:border-brand-200 transition
                                                    {{ $i > 0 ? '-ml-1.5' : '' }}">
                                            {{ strtoupper(substr($person->first_name,0,1)) }}{{ strtoupper(substr($person->last_name??'',0,1)) }}
                                        </div>
                                    @endforeach
                                    @if($extraPeople > 0)
                                        <div class="w-7 h-7 rounded-full bg-gray-100 text-gray-500 border-2 border-white
                                                    flex items-center justify-center text-xs font-semibold shrink-0 -ml-1.5
                                                    group-hover:bg-gray-200 transition">
                                            +{{ $extraPeople }}
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
                            @php
                                $status = $company->brandStatuses->first(fn($s) => $s->brand_product_id === $bp->id);
                            @endphp
                            @if($status)
                                @php
                                    [$stageBadge, $cellBg] = match(strtolower($status->stage)) {
                                        'lead'     => ['bg-blue-100 text-blue-700',    'bg-blue-50'],
                                        'prospect' => ['bg-purple-100 text-purple-700','bg-purple-50'],
                                        'trial'    => ['bg-yellow-100 text-yellow-800','bg-yellow-50'],
                                        'active'   => ['bg-green-100 text-green-700',  'bg-green-50'],
                                        'churned'  => ['bg-red-100 text-red-700',      'bg-red-50'],
                                        default    => ['bg-gray-100 text-gray-600',    'bg-gray-50'],
                                    };
                                    $sc       = $status->evaluation_score;
                                    $scClr    = $sc ? ($scoreColorMap[$sc] ?? '#e5e7eb') : '#e5e7eb';
                                    $scTxtClr = ($sc && $sc >= 3 && $sc <= 6) ? '#374151' : '#ffffff';
                                @endphp
                                <td class="px-2 py-2 {{ $cellBg }} cursor-pointer"
                                    onclick="openPopup('popup-bp-{{ $company->id }}-{{ $bp->id }}')">
                                    <div class="flex items-center justify-start gap-1.5 flex-wrap">
                                        {{-- Small filled score circle --}}
                                        <span class="inline-flex w-6 h-6 rounded-full items-center justify-center
                                                     text-[11px] font-bold shrink-0 leading-none"
                                              style="background:{{ $scClr }};color:{{ $scTxtClr }}">
                                            {{ $sc ?? '—' }}
                                        </span>
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium {{ $stageBadge }} shrink-0">
                                            {{ $status->stage }}
                                        </span>
                                    </div>
                                </td>
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
                            @if($convChannels->isEmpty())
                                <span class="text-gray-300 text-xs">—</span>
                            @else
                                <div class="flex items-center gap-1 flex-wrap">
                                    @foreach($convChannels as $conv)
                                        @php
                                            $ct      = strtolower($conv->channel_type ?? '');
                                            $ts      = $convTypeMap[$ct] ?? null;
                                            $cls     = $ts ? ($ts['cls'] ?? 'bg-gray-100 text-gray-600') : 'bg-gray-100 text-gray-600';
                                            $bStyle  = $ts['style'] ?? '';
                                            $iconDef = $convIcons[$ts['icon'] ?? 'chat'] ?? $convIcons['chat'];
                                        @endphp
                                        <a href="{{ route('conversations.index', ['company_id' => $company->id, 'channel_type' => $conv->channel_type]) }}"
                                           title="{{ $conv->channel_type }}"
                                           class="inline-flex items-center justify-center w-6 h-6 rounded shrink-0 hover:opacity-80 transition {{ $cls }}"
                                           @if($bStyle) style="{{ $bStyle }}" @endif>
                                            @if($iconDef['stroke'])
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                                    <path d="{{ $iconDef['d'] }}"/>
                                                </svg>
                                            @else
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="{{ $iconDef['d'] }}"/>
                                                </svg>
                                            @endif
                                        </a>
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
    @php $popupContacts = $company->people->filter(fn($p) => !$p->identities->contains('is_team_member', true)); @endphp
    @if($popupContacts->isNotEmpty())
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
                @foreach($popupContacts as $person)
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
        @php
            $bpStatus = $company->brandStatuses->first(fn($s) => $s->brand_product_id === $bp->id);
        @endphp
        @if($bpStatus)
            @php
                $bpSc    = $bpStatus->evaluation_score;
                $bpClr   = $bpSc ? ($scoreColorMap[$bpSc] ?? '#e5e7eb') : '#e5e7eb';
                $bpR     = 26;
                $bpCirc  = 2 * M_PI * $bpR;
                $bpOff   = $bpSc ? $bpCirc * (1 - $bpSc / 10) : $bpCirc;
                $bpBadge = match(strtolower($bpStatus->stage)) {
                    'lead'     => 'bg-blue-100 text-blue-700',
                    'prospect' => 'bg-purple-100 text-purple-700',
                    'trial'    => 'bg-yellow-100 text-yellow-800',
                    'active'   => 'bg-green-100 text-green-700',
                    'churned'  => 'bg-red-100 text-red-700',
                    default    => 'bg-gray-100 text-gray-600',
                };
            @endphp
            <div id="popup-bp-{{ $company->id }}-{{ $bp->id }}"
                 class="hidden fixed z-50 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
                        w-[340px] bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-800 truncate">
                            {{ $bp->name }}{{ $bp->variant ? ' · '.$bp->variant : '' }}
                        </h3>
                        <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $company->name }}</p>
                    </div>
                    <button type="button" onclick="closeAllPopups()"
                            class="text-gray-400 hover:text-gray-700 text-2xl leading-none ml-3 shrink-0">&times;</button>
                </div>
                <div class="px-5 py-4">
                    <div class="flex items-center gap-4">
                        {{-- Score ring --}}
                        <div class="relative w-16 h-16 shrink-0">
                            <svg width="64" height="64" viewBox="0 0 64 64" style="transform:rotate(-90deg)">
                                <circle cx="32" cy="32" r="{{ $bpR }}" fill="none" stroke="#e5e7eb" stroke-width="5"/>
                                @if($bpSc)
                                    <circle cx="32" cy="32" r="{{ $bpR }}" fill="none"
                                            stroke="{{ $bpClr }}" stroke-width="5"
                                            stroke-linecap="round"
                                            style="stroke-dasharray:{{ number_format($bpCirc,3) }};stroke-dashoffset:{{ number_format($bpOff,3) }}"/>
                                @endif
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-xl font-bold text-gray-900">{{ $bpSc ?? '—' }}</span>
                            </div>
                        </div>
                        <div>
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $bpBadge }}">
                                {{ $bpStatus->stage }}
                            </span>
                            @if($bpStatus->last_evaluated_at)
                                <p class="text-xs text-gray-400 mt-1"
                                   title="{{ $bpStatus->last_evaluated_at->format('D, j M Y \a\t H:i') }}">
                                    Evaluated {{ $bpStatus->last_evaluated_at->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                    </div>
                    @if($bpStatus->evaluation_notes)
                        <div class="mt-3 bg-gray-50 rounded-lg px-3 py-2.5 text-sm text-gray-700 leading-relaxed">
                            {{ $bpStatus->evaluation_notes }}
                        </div>
                    @endif
                </div>
                <div class="px-5 pb-4">
                    <a href="{{ route('companies.show', $company) }}"
                       class="text-sm text-brand-600 hover:text-brand-700 font-medium transition">
                        View {{ $company->name }} →
                    </a>
                </div>
            </div>
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
