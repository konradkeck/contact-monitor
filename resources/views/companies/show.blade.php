@extends('layouts.app')
@section('title', $company->name)

@section('content')

{{-- Page header --}}
<div class="page-header">
    <div>
        <nav aria-label="Breadcrumb" class="page-breadcrumb">
            @if($backLink ?? null)
                <a href="{{ $backLink['url'] }}">{{ $backLink['label'] }}</a>
                <span class="sep">/</span>
            @endif
            <a href="{{ route('companies.index') }}">Companies</a>
            <span class="sep">/</span>
            <span class="cur" aria-current="page">{{ $company->name }}</span>
        </nav>
        <h1 class="page-title mt-1">{{ $company->name }}</h1>
    </div>
    <div class="flex items-center gap-2">
        <button type="button" onclick="showCompanyFilterModal()" class="btn btn-secondary btn-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
            Filter
        </button>
    </div>
</div>

{{-- MAIN GRID --}}
<div class="grid grid-cols-3 gap-5">

    {{-- ── LEFT COLUMN ── --}}
    <div class="space-y-4">

        {{-- Company Card (with meta + conversations inside) --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

            {{-- Dark header --}}
            <div class="bg-gradient-to-br from-gray-900 to-gray-700 px-5 pt-5 pb-10">
                <div class="flex items-baseline gap-2 flex-wrap">
                    <h2 class="text-white font-bold text-xl leading-tight">
                        {{ $primaryAlias?->alias ?? $company->name }}
                    </h2>
                    @if($company->aliases->filter(fn($a) => !$a->is_primary)->count() > 0)
                        <button onclick="openPopup('popup-aliases')"
                                class="text-xs text-blue-300 hover:text-blue-200 font-medium transition cursor-pointer">
                            [+{{ $nonPrimaryAliasCount }} more]
                        </button>
                    @else
                        <button onclick="openPopup('popup-aliases')"
                                class="text-xs text-gray-500 hover:text-gray-400 transition cursor-pointer">[manage]</button>
                    @endif
                </div>
                @if($primaryAlias && $primaryAlias->alias !== $company->name)
                    <p class="text-gray-400 text-xs mt-0.5 italic">{{ $company->name }}</p>
                @endif
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1.5">
                    @if($primaryDomain)
                        <span class="text-gray-300 text-sm font-mono">{{ $primaryDomain->domain }}</span>
                    @endif
                    @if($otherDomains->isNotEmpty() || !$primaryDomain)
                        <button onclick="openPopup('popup-domains')"
                                class="text-xs text-blue-300 hover:text-blue-200 font-medium transition cursor-pointer">
                            @if($otherDomains->isNotEmpty())[+{{ $otherDomains->count() }} more]@else[+ add domain]@endif
                        </button>
                    @else
                        <button onclick="openPopup('popup-domains')"
                                class="text-xs text-gray-500 hover:text-gray-400 transition cursor-pointer">[manage]</button>
                    @endif
                </div>
            </div>

            {{-- Analysis placeholder --}}
            <div class="-mt-4 mx-4 mb-4 bg-white rounded-lg border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Company Analysis</p>
                <p class="text-xs text-gray-300 italic">AI summary coming soon…</p>
            </div>

        </div>{{-- /Company Card --}}

        {{-- Contacts (exclude team members) --}}
        <div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">Contacts</p>
            @if($contacts->isEmpty())
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-4 text-sm text-gray-400 italic">No contacts linked.</div>
            @else
                <div class="space-y-2">
                    @foreach($contacts as $person)
                        <a href="{{ route('people.show', $person) }}"
                           class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 px-4 py-3
                                  hover:border-brand-300 hover:shadow-sm transition group">
                            <x-person-avatar :person="$person" size="8" class="border border-gray-100 bg-gray-100 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-800 text-sm truncate group-hover:text-brand-700 transition">
                                    {{ $person->full_name }}
                                </p>
                                @if($person->pivot->role)
                                    <p class="text-xs text-gray-400">{{ $person->pivot->role }}</p>
                                @endif
                            </div>
                            <span class="text-xs text-brand-600 font-medium opacity-0 group-hover:opacity-100 transition shrink-0">Manage →</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- External Accounts --}}
        <div>
            <div class="flex items-center justify-between mb-2 px-1">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">External Accounts</p>
                @can('data_write')
                <button onclick="openPopup('popup-add-account')"
                        class="text-xs font-medium text-brand-600 hover:text-brand-700 border border-brand-200
                               hover:border-brand-400 px-3 py-1 rounded-full transition">
                    + Add
                </button>
                @endcan
            </div>
            @if($company->accounts->isEmpty())
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-4 text-sm text-gray-400 italic">
                    No external accounts linked.
                </div>
            @else
                <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                    @foreach($company->accounts as $account)
                        <div class="flex items-center gap-2 px-4 py-2.5">
                            <x-channel-badge :type="$account->system_type" :label="false" />
                            @if($account->system_slug !== 'default')
                                <span class="text-xs text-gray-700 shrink-0">{{ $account->system_slug }}</span>
                            @endif
                            <span class="font-mono text-sm text-gray-700 truncate flex-1">{{ $account->external_id }}</span>
                            @can('data_write')
                            <form action="{{ route('companies.accounts.destroy', [$company, $account]) }}" method="POST"
                                  onsubmit="return confirm('Remove this account?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-400 hover:text-red-600 font-bold shrink-0">✕</button>
                            </form>
                            @endcan
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Notes --}}
        <x-notes-section :notes="$notes" linkable-type="company" :linkable-id="$company->id" />

    </div>{{-- /LEFT --}}

    {{-- ── RIGHT COLUMN (2/3) ── --}}
    <div class="col-span-2 space-y-5">

        {{-- Segmentation --}}
        <div class="w-full">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Segmentation</p>
                @if($availableBrands->isNotEmpty())
                    <button onclick="openPopup('popup-add-brand')"
                            class="text-xs font-medium text-brand-600 hover:text-brand-700 border border-brand-200
                                   hover:border-brand-400 px-3 py-1 rounded-full transition">
                        + Add Segmentation
                    </button>
                @endif
            </div>
            @if($company->brandStatuses->isEmpty())
                <p class="text-sm text-gray-400 italic">No brand statuses yet.</p>
            @else
                <div class="grid grid-cols-2 xl:grid-cols-3 gap-3">
                    @foreach($company->brandStatuses as $status)
                        <div class="{{ match(strtolower($status->stage)) { 'lead' => 'bg-blue-50 border-blue-200', 'prospect' => 'bg-purple-50 border-purple-200', 'trial' => 'bg-yellow-50 border-yellow-200', 'active' => 'bg-green-50 border-green-200', 'churned' => 'bg-red-50 border-red-200', default => 'bg-white border-gray-200' } }} rounded-xl border p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <p class="font-semibold text-gray-900 text-sm">{{ $status->brandProduct?->name ?? '(deleted)' }}</p>
                                    @if($status->brandProduct?->variant)
                                        <p class="text-xs text-gray-400">{{ $status->brandProduct->variant }}</p>
                                    @endif
                                </div>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ match(strtolower($status->stage)) { 'lead' => 'bg-blue-100 text-blue-700', 'prospect' => 'bg-purple-100 text-purple-700', 'trial' => 'bg-yellow-100 text-yellow-800', 'active' => 'bg-green-100 text-green-700', 'churned' => 'bg-red-100 text-red-700', default => 'bg-gray-100 text-gray-700' } }}">
                                    {{ $status->stage }}
                                </span>
                            </div>
                            <div class="flex items-end justify-between">
                                <div>
                                    @if($status->evaluation_score !== null)
                                        <x-score-ring :score="$status->evaluation_score" />
                                    @else
                                        <div class="w-16 h-16 rounded-full border-4 border-gray-100 flex items-center justify-center">
                                            <span class="text-2xl font-bold text-gray-200">—</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="text-right text-xs text-gray-400">
                                    @if($status->last_evaluated_at)
                                        <p>{{ $status->last_evaluated_at->format('d M Y') }}</p>
                                    @endif
                                    @can('data_write')
                                    <div class="flex items-center gap-3">
                                        @if($status->brandProduct)
                                        <button onclick="document.getElementById('edit-bs-{{ $status->id }}').classList.toggle('hidden')"
                                                class="text-brand-600 hover:underline mt-1">Edit</button>
                                        @endif
                                        <form action="{{ route('companies.brand-statuses.destroy', [$company, $status]) }}" method="POST" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-400 hover:text-red-600 text-xs mt-1">
                                                {{ $status->brandProduct ? 'Remove' : 'Remove (deleted product)' }}
                                            </button>
                                        </form>
                                    </div>
                                    @endcan
                                </div>
                            </div>
                            @if($status->evaluation_notes)
                                <p class="text-xs text-gray-500 mt-2 line-clamp-2">{{ $status->evaluation_notes }}</p>
                            @endif
                            @can('data_write')
                            <div id="edit-bs-{{ $status->id }}" class="hidden mt-3 pt-3 border-t border-gray-100">
                                <form action="{{ route('companies.brand-statuses.update', [$company, $status]) }}" method="POST" class="space-y-2">
                                    @csrf @method('PATCH')
                                    <div class="flex gap-2">
                                        <select name="stage" class="flex-1 text-xs border border-gray-200 rounded px-2 py-1.5 bg-white">
                                            @foreach(['lead','prospect','trial','active','churned'] as $s)
                                                <option value="{{ $s }}" @selected($status->stage === $s)>{{ $s }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" name="evaluation_score" min="1" max="10"
                                               value="{{ $status->evaluation_score }}" placeholder="Score"
                                               class="w-16 text-xs border border-gray-200 rounded px-2 py-1.5">
                                    </div>
                                    <input type="text" name="evaluation_notes" value="{{ $status->evaluation_notes }}"
                                           placeholder="Notes…" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5">
                                    <button class="w-full py-1.5 bg-brand-600 text-white text-xs rounded hover:bg-brand-700 transition">Save</button>
                                </form>
                            </div>
                            @endcan
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── Services ── --}}
        @if(!empty($serviceSystems))
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            {{-- Tab bar (left-aligned, same style as activity widget) --}}
            <div class="flex items-center border-b border-gray-100 px-4 pt-1">
                @foreach($serviceSystems as $slug => $sys)
                    <button onclick="showSvcTab('{{ $slug }}')"
                            id="svc-tab-{{ $slug }}"
                            class="flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 transition whitespace-nowrap mr-1
                                   {{ $loop->first ? 'border-brand-500 text-brand-700' : 'border-transparent text-gray-400 hover:text-gray-700' }}">
                        <x-channel-badge :type="$sys['system_type'] ?? 'generic'" :label="false" />
                        {{ $slug }}
                    </button>
                @endforeach
            </div>

            @foreach($serviceSystems as $slug => $sys)
                <div id="svc-panel-{{ $slug }}" class="{{ $loop->first ? '' : 'hidden' }}">
                    @if($svcWidgets[$slug]['view'])
                        @include($svcWidgets[$slug]['view'], $svcWidgets[$slug]['data'])
                    @endif
                </div>
            @endforeach
        </div>
        <script>
        function showSvcTab(key) {
            document.querySelectorAll('[id^="svc-panel-"]').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('[id^="svc-tab-"]').forEach(b => {
                b.classList.remove('border-brand-500', 'text-brand-700');
                b.classList.add('border-transparent', 'text-gray-400', 'hover:text-gray-700');
            });
            document.getElementById('svc-panel-' + key)?.classList.remove('hidden');
            const btn = document.getElementById('svc-tab-' + key);
            if (btn) {
                btn.classList.add('border-brand-500', 'text-brand-700');
                btn.classList.remove('border-transparent', 'text-gray-400', 'hover:text-gray-700');
            }
        }
        </script>
        @endif

        {{-- Timeline (boxed) --}}
        <div id="timeline-box" class="bg-white rounded-xl border border-gray-200 overflow-hidden">

            {{-- Tab bar --}}
            <div class="flex items-center border-b border-gray-100 px-4 pt-1">
                @foreach(['conversations' => 'Conversations', 'activity' => 'Activity', 'all' => 'All'] as $tabKey => $tabLabel)
                    <button id="tl-tab-{{ $tabKey }}" onclick="setTab('{{ $tabKey }}')"
                            class="px-4 py-2.5 text-sm font-medium border-b-2 transition whitespace-nowrap mr-1
                                   {{ $tabKey === 'conversations' ? 'border-brand-500 text-brand-700' : 'border-transparent text-gray-400 hover:text-gray-700' }}">
                        {{ $tabLabel }}
                    </button>
                @endforeach
                <div class="flex-1"></div>
                <button id="tl-tab-filtered" onclick="setTab('filtered')"
                        class="px-3 py-2.5 text-xs font-medium border-b-2 border-transparent text-gray-300 hover:text-red-500 transition whitespace-nowrap">
                    Filtered
                </button>
            </div>

            {{-- Filter bar --}}
            <div class="px-5 pt-3 pb-3 border-b border-gray-100">
            <div class="flex items-center gap-3">

                {{-- Conversations filter dropdown --}}
                <div class="relative hidden" id="tl-conv-wrapper">
                    <button onclick="tlToggleDropdown('conv', event)"
                            class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 bg-white
                                   text-xs text-gray-600 hover:border-gray-300 transition min-w-[130px]">
                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/>
                        </svg>
                        <span id="tl-conv-label" class="flex-1 text-left">All</span>
                        <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div id="tl-conv-menu" class="hidden absolute left-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1.5 w-64">
                        <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                            <input type="checkbox" id="tl-conv-all" class="rounded border-gray-300" checked onchange="tlConvAll(this)">
                            <span class="text-sm text-gray-700 font-medium">All</span>
                        </label>
                        @if($filteredConvCount > 0)
                        <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                            <input type="checkbox" class="tl-conv-item rounded border-gray-300" value="__filtered__" onchange="tlConvItem(this)">
                            <span class="w-2 h-2 rounded-full bg-red-400 shrink-0"></span>
                            <span class="text-sm text-gray-700">Filtered ({{ $filteredConvCount }})</span>
                        </label>
                        @endif
                        @if($convSystems->isNotEmpty())
                            <div class="border-t border-gray-100 my-1"></div>
                            @foreach($convSystems as $sys)
                                <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                                    <input type="checkbox" class="tl-conv-item rounded border-gray-300"
                                           value="{{ $sys->channel_type }}|{{ $sys->system_slug }}" onchange="tlConvItem(this)">
                                    <span class="inline-flex items-center gap-1">
                                        <x-channel-badge :type="$sys->channel_type" :label="false" />
                                        @if($sys->system_type && get_class(\App\Integrations\IntegrationRegistry::get($sys->system_type ?? '')) !== get_class(\App\Integrations\IntegrationRegistry::get($sys->channel_type)))
                                            {!! \App\Integrations\IntegrationRegistry::get($sys->system_type)->iconHtml('w-4 h-4', false) !!}
                                        @endif
                                    </span>
                                    <span class="text-xs text-gray-700 truncate">{{ $sys->system_slug }}</span>
                                </label>
                            @endforeach
                        @endif
                    </div>
                </div>

                {{-- Activity filter dropdown --}}
                <div class="relative hidden" id="tl-act-wrapper">
                    <button onclick="tlToggleDropdown('act', event)"
                            class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 bg-white
                                   text-xs text-gray-600 hover:border-gray-300 transition min-w-[130px]">
                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/>
                        </svg>
                        <span id="tl-act-label" class="flex-1 text-left">All</span>
                        <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div id="tl-act-menu" class="hidden absolute left-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1.5 w-52">
                        <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                            <input type="checkbox" id="tl-act-all" class="rounded border-gray-300" checked onchange="tlActAll(this)">
                            <span class="text-sm text-gray-700 font-medium">All</span>
                        </label>
                        @if($activityTypes->isNotEmpty())
                            <div class="border-t border-gray-100 my-1"></div>
                            @foreach($activityTypes as $t)
                                <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                                    <input type="checkbox" class="tl-act-item rounded border-gray-300"
                                           value="{{ $t }}" onchange="tlActItem(this)">
                                    <span class="w-2 h-2 rounded-full {{ $typeColors[$t] ?? 'bg-slate-300' }} shrink-0"></span>
                                    <span class="text-sm text-gray-700">{{ ucfirst(str_replace('_', ' ', $t)) }}</span>
                                </label>
                            @endforeach
                        @endif
                    </div>
                </div>

                {{-- Spacer --}}
                <div class="flex-1"></div>

                {{-- Clear all (visible when any filter active) --}}
                <button id="tl-clear-btn" onclick="resetTimelineFilters()"
                        class="hidden text-xs text-gray-400 hover:text-gray-600 transition whitespace-nowrap">
                    ✕ Clear
                </button>

                {{-- Date range --}}
                <div class="drp-wrap" id="tl-date-range-wrap">
                    <input id="tl-date-range" type="text" placeholder="Date range…"
                           class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-600 bg-white
                                  focus:outline-none cursor-pointer w-44">
                </div>

            </div>
            </div>

            {{-- Timeline body --}}
            <div class="relative px-4 py-2 min-h-[120px]">
                <div class="absolute inset-y-0 left-1/2 -translate-x-1/2 w-px bg-gray-200 pointer-events-none z-0"></div>
                <div id="timeline-container" class="grid grid-cols-[1fr_2rem_1fr] relative z-10">
                    @include('companies.partials.timeline-items', [
                        'activities'     => $timelinePage->items(),
                        'nextCursor'     => $timelinePage->nextCursor()?->encode(),
                        'convSubjectMap' => $convSubjectMap,
                    ])
                </div>
                <div id="timeline-loading" class="hidden py-5 text-center">
                    <div class="inline-block w-5 h-5 border-2 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
                </div>
            </div>

        </div>{{-- /timeline-box --}}

    </div>{{-- /RIGHT --}}

</div>{{-- /main grid --}}


{{-- ═══════════════════ POPUPS ═══════════════════ --}}
<div id="popup-backdrop" class="hidden fixed inset-0 bg-black/40 z-40" onclick="closeAllPopups()"></div>

{{-- Domains --}}
<div id="popup-domains"
     class="hidden fixed z-50 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
            w-[420px] bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Domains</h3>
        <button onclick="closeAllPopups()" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
    </div>
    <ul class="divide-y divide-gray-50 max-h-60 overflow-y-auto">
        @forelse($company->domains as $domain)
            <li class="px-5 py-3 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="font-mono text-sm text-gray-700 truncate">{{ $domain->domain }}</span>
                    @if($domain->is_primary)
                        <span class="px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700 shrink-0">primary</span>
                    @endif
                </div>
                @can('data_write')
                <div class="flex items-center gap-3 shrink-0">
                    @if(!$domain->is_primary)
                        <form action="{{ route('companies.domains.primary', [$company, $domain]) }}" method="POST">
                            @csrf @method('PATCH')
                            <button class="text-xs text-gray-400 hover:text-brand-600 whitespace-nowrap transition">set primary</button>
                        </form>
                    @endif
                    <form action="{{ route('companies.domains.destroy', [$company, $domain]) }}" method="POST"
                          onsubmit="return confirm('Remove {{ $domain->domain }}?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-400 hover:text-red-600 font-bold">✕</button>
                    </form>
                </div>
                @endcan
            </li>
        @empty
            <li class="px-5 py-5 text-sm text-gray-400 italic text-center">No domains yet.</li>
        @endforelse
    </ul>
    @can('data_write')
    <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
        <form action="{{ route('companies.domains.store', $company) }}" method="POST" class="flex gap-2">
            @csrf
            <input type="text" name="domain" placeholder="example.com"
                   class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
            <button class="px-4 py-2 bg-brand-600 text-white text-sm rounded-lg hover:bg-brand-700 transition">Add</button>
        </form>
    </div>
    @endcan
</div>

{{-- Aliases --}}
<div id="popup-aliases"
     class="hidden fixed z-50 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
            w-[420px] bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Aliases</h3>
        <button onclick="closeAllPopups()" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
    </div>
    <ul class="divide-y divide-gray-50 max-h-60 overflow-y-auto">
        @forelse($company->aliases as $alias)
            <li class="px-5 py-3 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="text-sm text-gray-700 truncate">{{ $alias->alias }}</span>
                    @if($alias->is_primary)
                        <span class="px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700 shrink-0">primary</span>
                    @endif
                </div>
                @can('data_write')
                <div class="flex items-center gap-3 shrink-0">
                    @if(!$alias->is_primary)
                        <form action="{{ route('companies.aliases.primary', [$company, $alias]) }}" method="POST">
                            @csrf @method('PATCH')
                            <button class="text-xs text-gray-400 hover:text-brand-600 whitespace-nowrap transition">set primary</button>
                        </form>
                    @endif
                    <form action="{{ route('companies.aliases.destroy', [$company, $alias]) }}" method="POST"
                          onsubmit="return confirm('Remove {{ $alias->alias }}?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-400 hover:text-red-600 font-bold">✕</button>
                    </form>
                </div>
                @endcan
            </li>
        @empty
            <li class="px-5 py-5 text-sm text-gray-400 italic text-center">No aliases yet.</li>
        @endforelse
    </ul>
    @can('data_write')
    <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
        <form action="{{ route('companies.aliases.store', $company) }}" method="POST" class="flex gap-2">
            @csrf
            <input type="text" name="alias" placeholder="Alias…"
                   class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
            <button class="px-4 py-2 bg-brand-600 text-white text-sm rounded-lg hover:bg-brand-700 transition font-medium">Add</button>
        </form>
    </div>
    @endcan
</div>

{{-- Add External Account --}}
@can('data_write')
<div id="popup-add-account"
     class="hidden fixed z-50 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
            w-[400px] bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Add External Account</h3>
        <button onclick="closeAllPopups()" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
    </div>
    <form action="{{ route('companies.accounts.store', $company) }}" method="POST" class="px-5 py-4 space-y-3">
        @csrf
        <div>
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">System Type</label>
            <input type="text" name="system_type" placeholder="whmcs, metricscube, …"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
        </div>
        <div>
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">
                System Slug <span class="normal-case font-normal text-gray-400">(optional, for multi-instance)</span>
            </label>
            <input type="text" name="system_slug" placeholder="default"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
        </div>
        <div>
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">External ID</label>
            <input type="text" name="external_id" placeholder="12345"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
        </div>
        @error('external_id')
            <p class="text-xs text-red-500">{{ $message }}</p>
        @enderror
        <button class="w-full py-2 bg-brand-600 text-white font-semibold text-sm rounded-lg hover:bg-brand-700 transition">
            Add Account
        </button>
    </form>
</div>
@endcan

{{-- Add Brand Status --}}
@can('data_write')
@if($availableBrands->isNotEmpty())
<div id="popup-add-brand"
     class="hidden fixed z-50 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
            w-[380px] bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Add Brand Status</h3>
        <button onclick="closeAllPopups()" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
    </div>
    <form action="{{ route('companies.brand-statuses.store', $company) }}" method="POST" class="px-5 py-4 space-y-3">
        @csrf
        <div>
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">Brand / Product</label>
            <select name="brand_product_id"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-400">
                @foreach($availableBrands as $bp)
                    <option value="{{ $bp->id }}">{{ $bp->name }}{{ $bp->variant ? ' · '.$bp->variant : '' }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">Stage</label>
            <select name="stage"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-400">
                @foreach(['lead','prospect','trial','active','churned'] as $s)
                    <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">Score (1–10)</label>
            <input type="number" name="evaluation_score" min="1" max="10" placeholder="—"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
        </div>
        <button class="w-full py-2 bg-brand-600 text-white font-semibold text-sm rounded-lg hover:bg-brand-700 transition">
            Add Brand Status
        </button>
    </form>
</div>
@endif
@endcan



{{-- ═══════════════════ SCRIPTS ═══════════════════ --}}
<script>
// ── Company filter modal ──
function showCompanyFilterModal() {
    const src = '{{ route('companies.filter-modal') }}?ids[]={{ $company->id }}';
    openActivityModal({ dataset: { modalSrc: src } });
}

// ── Popup helpers ──
function openPopup(id) {
    closeAllPopups();
    document.getElementById(id)?.classList.remove('hidden');
    document.getElementById('popup-backdrop')?.classList.remove('hidden');
}
function closeAllPopups() {
    document.querySelectorAll('[id^="popup-"]:not(#popup-backdrop)')
        .forEach(el => el.classList.add('hidden'));
    document.getElementById('popup-backdrop')?.classList.add('hidden');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAllPopups(); });

// ── Timeline ──
(function () {
    const container = document.getElementById('timeline-container');
    const loading   = document.getElementById('timeline-loading');
    const clearBtn  = document.getElementById('tl-clear-btn');
    const companyId = {{ $company->id }};

    let fetching        = false;
    let reqId           = 0;
    let activeTab       = 'conversations';
    let activeSystems   = [];   // for conversations tab: ['ticket|dev6', ...]
    let showFilteredOnly = false; // "Filtered (X)" checked in conv dropdown
    let activeActTypes  = [];   // for activity tab
    let dateFrom        = '';
    let dateTo          = '';
    let fp              = null;

    function localDateStr(d) {
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    }

    // ── Tabs ──
    window.setTab = function (tab) {
        activeTab = tab;
        activeSystems = [];
        showFilteredOnly = false;
        activeActTypes = [];

        ['conversations','activity','all','filtered'].forEach(k => {
            const btn = document.getElementById('tl-tab-' + k);
            if (!btn) return;
            if (k === tab) {
                if (k === 'filtered') {
                    btn.classList.add('border-red-400', 'text-red-600');
                    btn.classList.remove('border-transparent', 'text-gray-300', 'hover:text-red-500');
                } else {
                    btn.classList.add('border-brand-500', 'text-brand-700');
                    btn.classList.remove('border-transparent', 'text-gray-400', 'hover:text-gray-700');
                }
            } else {
                if (k === 'filtered') {
                    btn.classList.remove('border-red-400', 'text-red-600');
                    btn.classList.add('border-transparent', 'text-gray-300', 'hover:text-red-500');
                } else {
                    btn.classList.remove('border-brand-500', 'text-brand-700');
                    btn.classList.add('border-transparent', 'text-gray-400', 'hover:text-gray-700');
                }
            }
        });

        // Show/hide appropriate dropdown
        document.getElementById('tl-conv-wrapper')?.classList.toggle('hidden', tab !== 'conversations');
        document.getElementById('tl-act-wrapper')?.classList.toggle('hidden', tab !== 'activity');

        // Reset dropdown UI
        const convAll = document.getElementById('tl-conv-all');
        if (convAll) convAll.checked = true;
        document.querySelectorAll('.tl-conv-item').forEach(c => c.checked = false);
        const actAll = document.getElementById('tl-act-all');
        if (actAll) actAll.checked = true;
        document.querySelectorAll('.tl-act-item').forEach(c => c.checked = false);
        updateConvLabel();
        updateActLabel();

        resetTimeline();
    };

    // ── Conversations dropdown ──
    window.tlConvAll = function (cb) {
        if (cb.checked) {
            activeSystems = [];
            showFilteredOnly = false;
            document.querySelectorAll('.tl-conv-item').forEach(c => c.checked = false);
        } else {
            cb.checked = true; // keep checked if nothing else selected
        }
        updateConvLabel();
        resetTimeline();
    };

    window.tlConvItem = function (cb) {
        const allCb = document.getElementById('tl-conv-all');
        if (allCb) allCb.checked = false;
        const checked = Array.from(document.querySelectorAll('.tl-conv-item:checked')).map(c => c.value);
        activeSystems   = checked.filter(v => v !== '__filtered__');
        showFilteredOnly = checked.includes('__filtered__');
        if (checked.length === 0) {
            // nothing selected → revert to All
            activeSystems = [];
            showFilteredOnly = false;
            document.querySelectorAll('.tl-conv-item').forEach(c => c.checked = false);
            if (allCb) allCb.checked = true;
        }
        updateConvLabel();
        resetTimeline();
    };

    function updateConvLabel() {
        const label = document.getElementById('tl-conv-label');
        if (!label) return;
        const total = activeSystems.length + (showFilteredOnly ? 1 : 0);
        label.textContent = total === 0 ? 'All' : (total === 1
            ? (showFilteredOnly && !activeSystems.length ? 'Filtered' : activeSystems[0].split('|')[1])
            : total + ' filters');
    }

    // ── Activity dropdown ──
    window.tlActAll = function (cb) {
        if (cb.checked) {
            activeActTypes = [];
            document.querySelectorAll('.tl-act-item').forEach(c => c.checked = false);
        } else {
            cb.checked = true;
        }
        updateActLabel();
        resetTimeline();
    };

    window.tlActItem = function (cb) {
        const allCb = document.getElementById('tl-act-all');
        if (allCb) allCb.checked = false;
        activeActTypes = Array.from(document.querySelectorAll('.tl-act-item:checked')).map(c => c.value);
        if (!activeActTypes.length) {
            if (allCb) allCb.checked = true;
        }
        updateActLabel();
        resetTimeline();
    };

    function updateActLabel() {
        const label = document.getElementById('tl-act-label');
        if (!label) return;
        label.textContent = !activeActTypes.length ? 'All'
            : (activeActTypes.length === 1 ? activeActTypes[0].replace(/_/g, ' ')
            : activeActTypes.length + ' types');
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
        setTab('conversations');
        fp = drp.init('tl-date-range', function(from, to) {
            dateFrom = from; dateTo = to; resetTimeline();
        });
    });

    function sentinel() { return document.getElementById('timeline-sentinel'); }

    function buildUrl(cursor) {
        const p = new URLSearchParams();
        if (cursor) p.set('cursor', cursor);
        if (dateFrom) p.set('from', dateFrom);
        if (dateTo)   p.set('to',   dateTo);

        if (activeTab === 'conversations') {
            p.append('types[]', 'conversation');
            activeSystems.forEach(s => p.append('systems[]', s));
            if (showFilteredOnly) p.set('is_filtered', '1');
        } else if (activeTab === 'activity') {
            activeActTypes.forEach(t => p.append('types[]', t));
        } else if (activeTab === 'filtered') {
            p.set('is_filtered', '1');
        }
        // 'all' tab: no types[] = show everything

        return `/companies/${companyId}/timeline?${p}`;
    }

    function hasFilters() {
        return activeSystems.length > 0 || showFilteredOnly || activeActTypes.length > 0 || dateFrom || dateTo;
    }
    function updateClearBtn() { clearBtn.classList.toggle('hidden', !hasFilters()); }

    function loadMore(cursor) {
        if (fetching) return;
        fetching = true;
        const myId = ++reqId;
        loading.classList.remove('hidden');

        fetch(buildUrl(cursor), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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

    function resetTimeline() {
        const savedY = window.scrollY;
        const savedH = container.offsetHeight;
        container.style.minHeight = savedH + 'px';
        observer.disconnect();
        reqId++;
        container.innerHTML = '';
        fetching = false;
        updateClearBtn();
        loadMore(null);
        window.scrollTo({ top: savedY, behavior: 'instant' });
        setTimeout(() => { container.style.minHeight = ''; }, 600);
    }

    window.clearDateFilter = function () { fp?.clear(); };

    window.resetTimelineFilters = function () {
        setTab('all');
        fp?.clear();
    };

    // ── IntersectionObserver ──
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

@endsection
