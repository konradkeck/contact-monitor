@extends('layouts.app')
@section('title', $person->full_name)

@section('content')

{{-- ── POPUP: Link company ── --}}
@if($allCompanies->isNotEmpty())
<div id="popup-link-company"
     class="fixed inset-0 z-50 hidden"
     onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="absolute inset-0 bg-black/20"></div>
    <div class="absolute bg-white rounded-xl shadow-xl w-80 modal-center"
         onclick="event.stopPropagation()">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <span class="font-semibold text-gray-800 text-sm">Link company</span>
            <button onclick="document.getElementById('popup-link-company').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
        </div>
        <form action="{{ route('people.companies.link', $person) }}" method="POST" class="px-5 py-4 flex flex-col gap-3">
            @csrf
            <div>
                <label class="text-xs text-gray-500 mb-1 block">Company</label>
                <select name="company_id" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
                    @foreach($allCompanies as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 mb-1 block">Role <span class="text-gray-300">(optional)</span></label>
                <input type="text" name="role" placeholder="e.g. CTO, Owner…"
                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
            </div>
            <button type="submit"
                    class="w-full py-2 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition">
                Link company
            </button>
        </form>
    </div>
</div>
@endif

{{-- ── POPUP: Add identity ── --}}
@can('data_write')
<div id="popup-add-identity"
     class="fixed inset-0 z-50 hidden"
     onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="absolute inset-0 bg-black/20"></div>
    <div class="absolute bg-white rounded-xl shadow-xl w-80 modal-center"
         onclick="event.stopPropagation()">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <span class="font-semibold text-gray-800 text-sm">Add identity</span>
            <button onclick="document.getElementById('popup-add-identity').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
        </div>
        <form action="{{ route('people.identities.store', $person) }}" method="POST" class="px-5 py-4 flex flex-col gap-3">
            @csrf
            <div>
                <label class="text-xs text-gray-500 mb-1 block">Type</label>
                <select name="type" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
                    @foreach(['email','slack_id','discord_id','phone','linkedin','twitter'] as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 mb-1 block">Value</label>
                <input type="text" name="value" placeholder="e.g. user@example.com"
                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
            </div>
            <button type="submit"
                    class="w-full py-2 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition">
                Add identity
            </button>
        </form>
    </div>
</div>
@endcan

{{-- ── PAGE HEADER ── --}}
<div class="flex items-start justify-between mb-5">
    <div>
        <div class="flex items-center gap-2 text-sm text-gray-500">
            @if($backLink ?? null)
                <a href="{{ $backLink['url'] }}" class="hover:text-gray-700">← {{ $backLink['label'] }}</a>
                <span class="text-gray-300">/</span>
            @endif
            <a href="{{ route('people.index') }}" class="hover:text-gray-700">{{ ($backLink ?? null) ? 'People' : '← People' }}</a>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mt-1">{{ $person->full_name }}</h1>
    </div>
    <div class="flex items-center gap-2">
        <button type="button"
                onclick="showPersonFilterModal()"
                class="px-3 py-1.5 border border-gray-300 text-sm rounded hover:bg-gray-50 text-gray-400 hover:text-red-500 transition"
                title="Filtered">🚫 Filter</button>
        @can('data_write')
        @if(!$person->is_our_org)
        <button type="button" id="mark-our-org-btn"
                onclick="markPersonOurOrg()"
                class="px-3 py-1.5 border border-indigo-200 bg-indigo-50 text-indigo-700 text-sm rounded hover:bg-indigo-100 transition">
            Our Org</button>
        @endif
        <button type="button"
                onclick="showPersonAssignCompany()"
                class="px-3 py-1.5 border border-gray-300 text-sm rounded hover:bg-gray-50 text-gray-400 hover:text-brand-600 transition">
            Assign company</button>
        <a href="{{ route('people.edit', $person) }}" class="px-3 py-1.5 border border-gray-300 text-sm rounded hover:bg-gray-50">Edit</a>
        @endcan
    </div>
</div>

<div class="grid grid-cols-3 gap-5">

    {{-- ── LEFT COLUMN ── --}}
    <div class="space-y-4">

        {{-- Avatar + name + companies --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

            {{-- Dark header (indigo tint if Our Organization) --}}
            <div class="{{ $person->is_our_org ? 'bg-gradient-to-br from-indigo-900 to-indigo-700' : 'bg-gradient-to-br from-gray-900 to-gray-700' }} px-5 pt-5 pb-10 flex flex-col items-center text-center">
                @if($person->is_our_org)
                    <span class="mb-2 inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-400/30 text-indigo-100 border border-indigo-400/40">
                        Our Org
                    </span>
                @endif
                <x-person-avatar :person="$person" size="14"
                     class="border-2 {{ $person->is_our_org ? 'border-indigo-300/40' : 'border-white/20' }} bg-gray-600 mb-3" />
                <p class="font-bold text-white text-base leading-snug">{{ $person->full_name }}</p>
                <p class="text-xs {{ $person->is_our_org ? 'text-indigo-300' : 'text-gray-400' }} mt-1">Since {{ $person->created_at->format('d M Y') }}</p>
            </div>

            {{-- Companies lifted card --}}
            <div class="-mt-4 mx-4 mb-4 bg-white rounded-lg border border-gray-200 shadow-sm">
                {{-- Card header --}}
                <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Assigned Companies</span>
                    <div class="flex items-center gap-1.5">
                        @if($allCompanies->isNotEmpty())
                            <button type="button"
                                    onclick="document.getElementById('popup-link-company').classList.remove('hidden')"
                                    class="text-xs font-medium text-brand-600 hover:text-brand-700 border border-brand-200 hover:border-brand-400 px-2 py-0.5 rounded transition">
                                Link
                            </button>
                        @endif
                        <button type="button"
                                onclick="showPersonAssignCompany()"
                                class="text-xs font-medium text-gray-500 hover:text-brand-700 border border-gray-200 hover:border-brand-400 px-2 py-0.5 rounded transition">
                            + New
                        </button>
                    </div>
                </div>
                @if($person->companies->isEmpty())
                    <p class="px-4 py-3 text-xs text-gray-400 italic">Not linked to any company.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach($person->companies as $company)
                            <li class="px-4 py-2.5 flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <a href="{{ route('companies.show', $company) }}"
                                       class="text-sm font-medium text-brand-700 hover:underline block truncate">
                                        {{ $company->name }}
                                    </a>
                                    @if($company->pivot->role)
                                        <span class="text-xs text-gray-400">{{ $company->pivot->role }}</span>
                                    @endif
                                </div>
                                @can('data_write')
                                <form action="{{ route('people.companies.unlink', [$person, $company]) }}" method="POST" class="shrink-0 mt-0.5">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-400 hover:text-red-600">✕</button>
                                </form>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

        </div>

        {{-- Identities --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 text-sm">Identities</h3>
                @can('data_write')
                <button onclick="document.getElementById('popup-add-identity').classList.remove('hidden')"
                        class="text-xs font-medium text-brand-600 hover:text-brand-700 border border-brand-200
                               hover:border-brand-400 px-3 py-1 rounded-full transition">
                    + Add
                </button>
                @endcan
            </div>
            @if($person->identities->isEmpty())
                <p class="px-4 py-4 text-sm text-gray-400 italic">No identities yet.</p>
            @else
                <ul class="divide-y divide-gray-50">
                    @foreach($person->identities as $identity)
                        <li class="px-4 py-2 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                @if(isset(\App\View\Components\IdentityIcon::getMap()[$identity->type]))
                                    <x-identity-icon :type="$identity->type" :value="$identity->value" />
                                @else
                                    <span class="text-xs text-gray-400 shrink-0 w-5 text-center">?</span>
                                @endif
                                @if(!empty($identity->meta_json['avatar']) && in_array($identity->type, ['discord_user', 'discord_id']))
                                    <img src="https://cdn.discordapp.com/avatars/{{ $identity->value_normalized }}/{{ $identity->meta_json['avatar'] }}.webp?size=32"
                                         class="w-5 h-5 rounded-full shrink-0 ring-1 ring-gray-200"
                                         alt="avatar">
                                @elseif(!empty($identity->meta_json['avatar']) && $identity->type === 'slack_user')
                                    <img src="{{ $identity->meta_json['avatar'] }}"
                                         class="w-5 h-5 rounded-full shrink-0 ring-1 ring-gray-200"
                                         alt="avatar">
                                @endif
                                @if(!empty($identity->meta_json['display_name']))
                                    <span class="text-xs text-gray-700 truncate font-medium">{{ $identity->meta_json['display_name'] }}</span>
                                @endif
                                @if(!in_array($identity->type, ['discord_user', 'discord_id', 'slack_user']) || empty($identity->meta_json['display_name']))
                                    @if(\App\View\Components\IdentityIcon::hrefFor($identity->type, $identity->value))
                                        <a href="{{ \App\View\Components\IdentityIcon::hrefFor($identity->type, $identity->value) }}" target="_blank" rel="noopener"
                                           class="font-mono text-xs text-brand-700 hover:underline truncate">{{ $identity->value }}</a>
                                    @else
                                        <span class="font-mono text-xs text-gray-600 truncate">{{ $identity->value }}</span>
                                    @endif
                                @endif
                                @if($identity->system_slug !== 'default')
                                    <x-badge color="gray">{{ $identity->system_slug }}</x-badge>
                                @endif
                            </div>
                            @can('data_write')
                            <form action="{{ route('people.identities.destroy', [$person, $identity]) }}" method="POST" class="shrink-0">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600">✕</button>
                            </form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Notes --}}
        <div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">Notes</p>
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl overflow-hidden shadow-sm">
                @if($notes->isEmpty())
                    <p class="px-4 py-3 text-sm text-yellow-600 italic">No notes yet.</p>
                @else
                    <ul class="divide-y divide-yellow-100 max-h-72 overflow-y-auto">
                        @foreach($notes as $note)
                            <li class="px-4 py-3">
                                <p class="text-sm text-yellow-900 leading-snug">{{ $note->content }}</p>
                                <p class="text-xs text-yellow-500 mt-1.5"
                                   title="{{ $note->created_at->format('D, j M Y \a\t H:i') }}">
                                    {{ $note->created_at->diffForHumans() }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @can('notes_write')
                <div class="px-4 py-3">
                    <form action="{{ route('notes.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="linkable_type" value="person">
                        <input type="hidden" name="linkable_id" value="{{ $person->id }}">
                        <textarea name="content" rows="2" placeholder="Add a note…"
                                  class="w-full bg-white border border-yellow-200 rounded-lg px-3 py-2 text-sm
                                         placeholder-yellow-300 text-gray-700 resize-none focus:outline-none
                                         focus:ring-2 focus:ring-yellow-300"></textarea>
                        <button class="mt-2 w-full py-1.5 bg-yellow-400 hover:bg-yellow-500 text-yellow-900
                                       font-semibold text-xs rounded-lg transition">+ Add note</button>
                    </form>
                </div>
                @endcan
            </div>
        </div>

        {{-- Conversations --}}
        @if($convGroups->isNotEmpty())
        <div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">Conversations</p>
            <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-50 overflow-hidden">
                @foreach($convGroups as $group)
                    <a href="{{ route('conversations.index', ['person_id' => $person->id, 'channel_type' => $group->channel_type, 'system_slug' => $group->system_slug]) }}"
                       class="px-4 py-2.5 flex items-center gap-2.5 hover:bg-gray-50 transition">
                        <x-channel-badge :type="$group->channel_type" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-700 truncate leading-snug"
                               title="{{ $group->last_subject }}">
                                {{ \Illuminate\Support\Str::limit($group->last_subject ?? '(no subject)', 38) }}
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                <span class="font-mono">{{ $group->system_slug }}</span>
                                · {{ $group->conv_count }}
                            </p>
                        </div>
                        <p class="text-xs text-gray-400 shrink-0 whitespace-nowrap">
                            {{ $group->last_message_at ? \Carbon\Carbon::parse($group->last_message_at)->diffForHumans() : '—' }}
                        </p>
                    </a>
                @endforeach
            </div>
        </div>
        @endif

    </div>{{-- /LEFT --}}

    {{-- ── RIGHT: TIMELINE (col-span-2) ── --}}
    <div class="col-span-2">
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
                                    <x-channel-badge :type="$sys->channel_type" :label="false" />
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

                {{-- Date range (flatpickr) --}}
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
                <div id="timeline-container"
                     class="grid grid-cols-[1fr_2rem_1fr] relative z-10"
                     data-url="{{ route('people.timeline', $person) }}">
                    @include('people.partials.timeline-items', [
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
    </div>

</div>

<script>
// ── Timeline ──
(function () {
    const container = document.getElementById('timeline-container');
    const loading   = document.getElementById('timeline-loading');
    const clearBtn  = document.getElementById('tl-clear-btn');
    const dateClear = document.getElementById('tl-date-clear');
    const baseUrl   = container.dataset.url;

    let fetching         = false;
    let reqId            = 0;
    let activeTab        = 'conversations';
    let activeSystems    = [];
    let showFilteredOnly = false;
    let activeActTypes   = [];
    let dateFrom         = '';
    let dateTo           = '';
    let fp               = null;

    function localDateStr(d) {
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    }

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

        document.getElementById('tl-conv-wrapper')?.classList.toggle('hidden', tab !== 'conversations');
        document.getElementById('tl-act-wrapper')?.classList.toggle('hidden', tab !== 'activity');

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

    window.tlConvAll = function (cb) {
        if (cb.checked) {
            activeSystems = [];
            showFilteredOnly = false;
            document.querySelectorAll('.tl-conv-item').forEach(c => c.checked = false);
        } else {
            cb.checked = true;
        }
        updateConvLabel();
        resetTimeline();
    };

    window.tlConvItem = function (cb) {
        const allCb = document.getElementById('tl-conv-all');
        if (allCb) allCb.checked = false;
        const checked = Array.from(document.querySelectorAll('.tl-conv-item:checked')).map(c => c.value);
        activeSystems    = checked.filter(v => v !== '__filtered__');
        showFilteredOnly = checked.includes('__filtered__');
        if (!checked.length) {
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
                    resetTimeline();
                } else if (selectedDates.length === 0) {
                    dateFrom = dateTo = '';
                    dateClear.classList.add('hidden');
                    resetTimeline();
                }
            }
        });
    });

    function sentinel() { return document.getElementById('timeline-sentinel'); }

    function buildUrl(cursor) {
        const p = new URLSearchParams();
        if (cursor)   p.set('cursor', cursor);
        if (dateFrom) p.set('from',   dateFrom);
        if (dateTo)   p.set('to',     dateTo);

        if (activeTab === 'conversations') {
            p.append('types[]', 'conversation');
            activeSystems.forEach(s => p.append('systems[]', s));
            if (showFilteredOnly) p.set('is_filtered', '1');
        } else if (activeTab === 'activity') {
            activeActTypes.forEach(t => p.append('types[]', t));
        } else if (activeTab === 'filtered') {
            p.set('is_filtered', '1');
        }

        return baseUrl + '?' + p;
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

    window.clearDateFilter  = function () { fp?.clear(); };
    window.resetTimelineFilters = function () { setTab('all'); fp?.clear(); };

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
<script>
const _personId  = {{ $person->id }};
const _csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

function showPersonFilterModal() {
    openActivityModal({ dataset: { modalSrc: '{{ route('people.filter-modal') }}?ids[]=' + _personId } });
}
function showPersonAssignCompany() {
    openActivityModal({ dataset: { modalSrc: '{{ route('people.assign-company-modal') }}?ids[]=' + _personId } });
}
function markPersonOurOrg() {
    const btn = document.getElementById('mark-our-org-btn');
    if (btn) btn.disabled = true;
    fetch(`/people/${_personId}/mark-our-org`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({}),
    }).then(r => r.json()).then(d => { if (d.ok) window.location.reload(); else if (btn) btn.disabled = false; });
}
</script>
@endsection
