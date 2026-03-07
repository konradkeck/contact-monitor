@extends('layouts.app')
@section('title', $company->name)

@section('content')

@php
    $primaryDomain = $company->domains->firstWhere('is_primary', true) ?? $company->domains->first();
    $otherDomains  = $company->domains->filter(fn($d) => $d->id !== $primaryDomain?->id);
    $primaryAlias  = $company->aliases->firstWhere('is_primary', true);

    $allTypes   = ['payment','renewal','cancellation','ticket','conversation','note','status_change','campaign_run','followup'];

    // Score ring colors: 1 (red) → 10 (dark green)
    $scoreColorMap = [
        1  => '#ef4444',
        2  => '#f97316',
        3  => '#f59e0b',
        4  => '#eab308',
        5  => '#84cc16',
        6  => '#4ade80',
        7  => '#22c55e',
        8  => '#16a34a',
        9  => '#15803d',
        10 => '#166534',
    ];

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

{{-- Breadcrumb --}}
<div class="flex items-center gap-3 mb-6">
    @if($backLink ?? null)
        <a href="{{ $backLink['url'] }}" class="text-gray-400 hover:text-gray-600 text-sm">← {{ $backLink['label'] }}</a>
        <span class="text-gray-300">/</span>
    @endif
    <a href="{{ route('companies.index') }}" class="text-gray-400 hover:text-gray-600 text-sm">{{ ($backLink ?? null) ? 'Companies' : '← Companies' }}</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">{{ $company->name }}</h1>
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
                    @php $nonPrimaryAliasCount = $company->aliases->filter(fn($a) => !$a->is_primary)->count(); @endphp
                    @if($nonPrimaryAliasCount > 0)
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

            {{-- Meta pill --}}
            <div class="-mt-4 mx-4 bg-white rounded-lg border border-gray-200 shadow-sm px-4 py-2.5 flex items-center gap-2 text-sm">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Added</span>
                <span class="text-gray-700">{{ $company->created_at->format('d M Y') }}</span>
            </div>

            {{-- Last Conversations — grouped by channel_type + system_slug --}}
            @if($convGroups->isNotEmpty())
                <div class="mt-3 border-t border-gray-100">
                    <div class="flex items-center justify-between px-5 pt-3 pb-1">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Conversations</p>
                        <a href="{{ route('conversations.index', ['company_id' => $company->id]) }}"
                           class="text-xs text-brand-600 hover:underline">all ({{ $conversationCount }})</a>
                    </div>
                    <div class="divide-y divide-gray-50 pb-1">
                        @foreach($convGroups as $group)
                            <a href="{{ route('conversations.index', ['company_id' => $company->id, 'channel_type' => $group->channel_type, 'system_slug' => $group->system_slug]) }}"
                               class="px-5 py-2.5 flex items-center gap-2.5 hover:bg-gray-50 transition">
                                <x-channel-badge :type="$group->channel_type" />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-700 truncate leading-snug"
                                       title="{{ $group->last_subject }}">
                                        {{ \Illuminate\Support\Str::limit($group->last_subject ?? '(no subject)', 42) }}
                                    </p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        <span class="font-mono">{{ $group->system_slug }}</span>
                                        · {{ $group->conv_count }} conv
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

        </div>{{-- /Company Card --}}

        {{-- Contacts (exclude team members) --}}
        @php $contacts = $company->people->filter(fn($p) => !$p->identities->contains('is_team_member', true)); @endphp
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
                            <x-person-avatar :person="$person" size="10" class="border border-gray-100 bg-gray-100 shrink-0" />
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
                <div class="px-4 py-3">
                    <form action="{{ route('notes.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="linkable_type" value="company">
                        <input type="hidden" name="linkable_id" value="{{ $company->id }}">
                        <textarea name="content" rows="2" placeholder="Add a note…"
                                  class="w-full bg-white border border-yellow-200 rounded-lg px-3 py-2 text-sm
                                         placeholder-yellow-300 text-gray-700 resize-none focus:outline-none
                                         focus:ring-2 focus:ring-yellow-300"></textarea>
                        <button class="mt-2 w-full py-1.5 bg-yellow-400 hover:bg-yellow-500 text-yellow-900
                                       font-semibold text-xs rounded-lg transition">+ Add note</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- External Accounts --}}
        <div>
            <div class="flex items-center justify-between mb-2 px-1">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">External Accounts</p>
                <button onclick="openPopup('popup-add-account')"
                        class="text-xs font-medium text-brand-600 hover:text-brand-700 border border-brand-200
                               hover:border-brand-400 px-3 py-1 rounded-full transition">
                    + Add
                </button>
            </div>
            @if($company->accounts->isEmpty())
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-4 text-sm text-gray-400 italic">
                    No external accounts linked.
                </div>
            @else
                <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                    @foreach($company->accounts as $account)
                        <div class="flex items-center gap-2 px-4 py-2.5">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 shrink-0">
                                {{ $account->system_type }}
                            </span>
                            @if($account->system_slug !== 'default')
                                <span class="text-xs text-gray-400 shrink-0">{{ $account->system_slug }}</span>
                            @endif
                            <span class="font-mono text-sm text-gray-700 truncate flex-1">{{ $account->external_id }}</span>
                            <form action="{{ route('companies.accounts.destroy', [$company, $account]) }}" method="POST"
                                  onsubmit="return confirm('Remove this account?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-400 hover:text-red-600 font-bold shrink-0">✕</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>{{-- /LEFT --}}

    {{-- ── RIGHT COLUMN (2/3) ── --}}
    <div class="col-span-2 space-y-5">

        {{-- Segmentation --}}
        <div>
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Segmentation</p>
                @if($availableBrands->isNotEmpty())
                    <button onclick="openPopup('popup-add-brand')"
                            class="text-xs font-medium text-brand-600 hover:text-brand-700 border border-brand-200
                                   hover:border-brand-400 px-3 py-1 rounded-full transition">
                        + Add brand
                    </button>
                @endif
            </div>
            @if($company->brandStatuses->isEmpty())
                <p class="text-sm text-gray-400 italic">No brand statuses yet.</p>
            @else
                <div class="grid grid-cols-2 xl:grid-cols-3 gap-3">
                    @foreach($company->brandStatuses as $status)
                        @php
                            [$stageColor, $cardBg, $cardBorder] = match(strtolower($status->stage)) {
                                'lead'     => ['bg-blue-100 text-blue-700',   'bg-blue-50',   'border-blue-200'],
                                'prospect' => ['bg-purple-100 text-purple-700','bg-purple-50','border-purple-200'],
                                'trial'    => ['bg-yellow-100 text-yellow-800','bg-yellow-50','border-yellow-200'],
                                'active'   => ['bg-green-100 text-green-700', 'bg-green-50',  'border-green-200'],
                                'churned'  => ['bg-red-100 text-red-700',     'bg-red-50',    'border-red-200'],
                                default    => ['bg-gray-100 text-gray-700',   'bg-white',     'border-gray-200'],
                            };
                        @endphp
                        <div class="{{ $cardBg }} {{ $cardBorder }} rounded-xl border p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <p class="font-semibold text-gray-900 text-sm">{{ $status->brandProduct->name }}</p>
                                    @if($status->brandProduct->variant)
                                        <p class="text-xs text-gray-400">{{ $status->brandProduct->variant }}</p>
                                    @endif
                                </div>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $stageColor }}">
                                    {{ $status->stage }}
                                </span>
                            </div>
                            <div class="flex items-end justify-between">
                                <div>
                                    @if($status->evaluation_score !== null)
                                        @php
                                            $sc     = $status->evaluation_score;
                                            $scClr  = $scoreColorMap[$sc] ?? '#e5e7eb';
                                            $r      = 26;
                                            $circ   = 2 * M_PI * $r;
                                            $offset = $circ * (1 - $sc / 10);
                                        @endphp
                                        <div class="relative w-16 h-16">
                                            <svg width="64" height="64" viewBox="0 0 64 64"
                                                 style="transform:rotate(-90deg)">
                                                <circle cx="32" cy="32" r="{{ $r }}" fill="none"
                                                        stroke="#e5e7eb" stroke-width="5"/>
                                                <circle cx="32" cy="32" r="{{ $r }}" fill="none"
                                                        stroke="{{ $scClr }}" stroke-width="5"
                                                        stroke-linecap="round"
                                                        style="stroke-dasharray:{{ number_format($circ,3) }};stroke-dashoffset:{{ number_format($offset,3) }}"/>
                                            </svg>
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <span class="text-xl font-bold text-gray-900">{{ $sc }}</span>
                                            </div>
                                        </div>
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
                                    <button onclick="document.getElementById('edit-bs-{{ $status->id }}').classList.toggle('hidden')"
                                            class="text-brand-600 hover:underline mt-1">Edit</button>
                                </div>
                            </div>
                            @if($status->evaluation_notes)
                                <p class="text-xs text-gray-500 mt-2 line-clamp-2">{{ $status->evaluation_notes }}</p>
                            @endif
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
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── Services ── --}}
        @php
            // Group services by system_slug (each WHMCS instance = separate tab)
            $serviceSystems = [];
            foreach ($company->accounts as $acc) {
                if (empty($acc->meta_json['services'])) continue;
                $slug = $acc->system_slug;
                if (!isset($serviceSystems[$slug])) {
                    $serviceSystems[$slug] = ['system_type' => $acc->system_type, 'services' => []];
                }
                foreach ($acc->meta_json['services'] as $svc) {
                    $serviceSystems[$slug]['services'][] = $svc;
                }
            }
            // Compute KPIs per slug
            foreach ($serviceSystems as $slug => &$sys) {
                $svcs = $sys['services'];
                $sys['revenue'] = array_sum(array_column($svcs, 'total_revenue'));
                $sys['active']  = count(array_filter($svcs, fn($s) => strtolower($s['status'] ?? '') === 'active'));
                $sys['total']   = count($svcs);
                // Sort: active first, then by product name
                usort($sys['services'], fn($a, $b) =>
                    (strtolower($b['status'] ?? '') === 'active') <=> (strtolower($a['status'] ?? '') === 'active')
                    ?: strcmp($a['product_name'] ?? '', $b['product_name'] ?? '')
                );
            }
            unset($sys);
        @endphp

        @if(!empty($serviceSystems))
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            {{-- Header + tabs --}}
            <div class="flex items-center justify-between border-b border-gray-100 px-5 pt-3">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider pb-3">Services</p>
                @if(count($serviceSystems) > 1)
                <div class="flex gap-1" id="svc-tabs">
                    @foreach($serviceSystems as $slug => $sys)
                        <button onclick="showSvcTab('{{ $slug }}')"
                                id="svc-tab-{{ $slug }}"
                                class="px-3 py-1.5 text-xs font-semibold rounded-t-lg border-b-2 transition mb-[-1px]
                                       {{ $loop->first ? 'border-brand-500 text-brand-700' : 'border-transparent text-gray-400 hover:text-gray-600' }}">
                            {{ $slug }}
                        </button>
                    @endforeach
                </div>
                @else
                    @php $onlySlug = array_key_first($serviceSystems); @endphp
                    <span class="text-xs text-gray-400 font-mono pb-3">{{ $onlySlug }}</span>
                @endif
            </div>

            @foreach($serviceSystems as $slug => $sys)
            <div id="svc-panel-{{ $slug }}" class="{{ $loop->first ? '' : 'hidden' }}">

                {{-- KPI row --}}
                <div class="grid grid-cols-3 divide-x divide-gray-100 border-b border-gray-100">
                    <div class="px-5 py-4">
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold mb-1">Revenue</p>
                        <p class="text-2xl font-bold text-gray-900 tabular-nums">
                            ${{ number_format($sys['revenue'], 0) }}
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5">lifetime</p>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold mb-1">Active</p>
                        <p class="text-2xl font-bold text-green-600 tabular-nums">{{ $sys['active'] }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">services</p>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold mb-1">Total</p>
                        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ $sys['total'] }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">all services</p>
                    </div>
                </div>

                {{-- Services table --}}
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-5 py-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Product</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Since</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Renewals</th>
                            <th class="px-5 py-2 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($sys['services'] as $svc)
                            @php
                                $st = strtolower($svc['status'] ?? '');
                                [$stBadge, $stDot] = match($st) {
                                    'active'    => ['bg-green-100 text-green-700',  'bg-green-400'],
                                    'pending'   => ['bg-yellow-100 text-yellow-700','bg-yellow-400'],
                                    'suspended' => ['bg-red-100 text-red-600',      'bg-red-400'],
                                    default     => ['bg-gray-100 text-gray-500',    'bg-gray-300'],
                                };
                                $startDate = $svc['start_date'] ? \Carbon\Carbon::parse($svc['start_date'])->format('M Y') : '—';
                            @endphp
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-5 py-2.5 font-medium text-gray-800">{{ $svc['product_name'] ?? '—' }}</td>
                                <td class="px-3 py-2.5">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium {{ $stBadge }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $stDot }}"></span>
                                        {{ ucfirst($svc['status'] ?? '—') }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-xs text-gray-500">{{ $startDate }}</td>
                                <td class="px-3 py-2.5 text-xs text-gray-500 text-right tabular-nums">{{ $svc['renewal_count'] ?? 0 }}×</td>
                                <td class="px-5 py-2.5 text-right font-semibold text-gray-800 tabular-nums">
                                    ${{ number_format((float)($svc['total_revenue'] ?? 0), 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            </div>
            @endforeach
        </div>
        <script>
        function showSvcTab(key) {
            document.querySelectorAll('[id^="svc-panel-"]').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('[id^="svc-tab-"]').forEach(b => {
                b.classList.remove('border-brand-500', 'text-brand-700');
                b.classList.add('border-transparent', 'text-gray-400');
            });
            document.getElementById('svc-panel-' + key)?.classList.remove('hidden');
            const btn = document.getElementById('svc-tab-' + key);
            if (btn) { btn.classList.add('border-brand-500', 'text-brand-700'); btn.classList.remove('border-transparent', 'text-gray-400'); }
        }
        </script>
        @endif

        {{-- Timeline (boxed) --}}
        <div id="timeline-box" class="bg-white rounded-xl border border-gray-200 overflow-hidden">

            {{-- Filter bar --}}
            <div class="px-5 pt-4 pb-3 border-b border-gray-100">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Activity</p>
            <div class="flex items-center gap-3">

                {{-- Type multiselect dropdown --}}
                <div class="relative" id="tl-type-wrapper">
                    <button id="tl-type-btn" onclick="toggleTypeDropdown(event)"
                            class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 bg-white
                                   text-xs text-gray-600 hover:border-gray-300 transition min-w-[130px]">
                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/>
                        </svg>
                        <span id="tl-type-label" class="flex-1 text-left">All types</span>
                        <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div id="tl-type-menu"
                         class="hidden absolute left-0 top-full mt-1 bg-white border border-gray-200
                                rounded-xl shadow-lg z-20 py-1.5 w-52">
                        @foreach($allTypes as $t)
                            @php
                                $dotCls = $typeColors[$t] ?? 'bg-slate-300';
                                $lbl    = ucfirst(str_replace('_', ' ', $t));
                            @endphp
                            <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                                <input type="checkbox" class="tl-type-check rounded border-gray-300"
                                       value="{{ $t }}" onchange="handleTypeChecks()">
                                <span class="w-2 h-2 rounded-full {{ $dotCls }} shrink-0"></span>
                                <span class="text-sm text-gray-700">{{ $lbl }}</span>
                            </label>
                        @endforeach
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
                <div id="timeline-container" class="grid grid-cols-[1fr_2rem_1fr] relative z-10">
                    @include('companies.partials.timeline-items', [
                        'activities' => $timelinePage->items(),
                        'nextCursor' => $timelinePage->nextCursor()?->encode(),
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
            </li>
        @empty
            <li class="px-5 py-5 text-sm text-gray-400 italic text-center">No domains yet.</li>
        @endforelse
    </ul>
    <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
        <form action="{{ route('companies.domains.store', $company) }}" method="POST" class="flex gap-2">
            @csrf
            <input type="text" name="domain" placeholder="example.com"
                   class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
            <button class="px-4 py-2 bg-brand-600 text-white text-sm rounded-lg hover:bg-brand-700 transition">Add</button>
        </form>
    </div>
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
            </li>
        @empty
            <li class="px-5 py-5 text-sm text-gray-400 italic text-center">No aliases yet.</li>
        @endforelse
    </ul>
    <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
        <form action="{{ route('companies.aliases.store', $company) }}" method="POST" class="flex gap-2">
            @csrf
            <input type="text" name="alias" placeholder="Alias…"
                   class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
            <button class="px-4 py-2 bg-brand-600 text-white text-sm rounded-lg hover:bg-brand-700 transition font-medium">Add</button>
        </form>
    </div>
</div>

{{-- Add External Account --}}
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

{{-- Add Brand Status --}}
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



{{-- ═══════════════════ SCRIPTS ═══════════════════ --}}
<script>
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
    const dateClear = document.getElementById('tl-date-clear');
    const companyId = {{ $company->id }};

    let fetching    = false;
    let reqId       = 0;
    let activeTypes = [];
    let dateFrom    = '';
    let dateTo      = '';
    let fp          = null;

    function localDateStr(d) {
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    }

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
        activeTypes.forEach(t => p.append('types[]', t));
        if (dateFrom) p.set('from', dateFrom);
        if (dateTo)   p.set('to',   dateTo);
        return `/companies/${companyId}/timeline?${p}`;
    }

    function hasFilters() { return activeTypes.length > 0 || dateFrom || dateTo; }
    function updateClearBtn() { clearBtn.classList.toggle('hidden', !hasFilters()); }

    function updateTypeLabel() {
        const label = document.getElementById('tl-type-label');
        if (!activeTypes.length)           label.textContent = 'All types';
        else if (activeTypes.length === 1) label.textContent = activeTypes[0].replace(/_/g, ' ');
        else                               label.textContent = `${activeTypes.length} types`;
    }

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
        observer.disconnect();
        reqId++;
        container.innerHTML = '';
        fetching = false;
        updateClearBtn();
        loadMore(null);
    }

    // ── Type dropdown ──
    window.toggleTypeDropdown = function (e) {
        e.stopPropagation();
        document.getElementById('tl-type-menu')?.classList.toggle('hidden');
    };
    document.addEventListener('click', e => {
        if (!document.getElementById('tl-type-wrapper')?.contains(e.target)) {
            document.getElementById('tl-type-menu')?.classList.add('hidden');
        }
    });

    window.handleTypeChecks = function () {
        activeTypes = Array.from(document.querySelectorAll('.tl-type-check:checked')).map(c => c.value);
        updateTypeLabel();
        resetTimeline();
    };

    // Called from conversation row click
    window.setTypeFilter = function (type) {
        activeTypes = [type];
        document.querySelectorAll('.tl-type-check').forEach(cb => { cb.checked = cb.value === type; });
        updateTypeLabel();
        resetTimeline();
        document.getElementById('timeline-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    window.clearDateFilter = function () {
        fp?.clear();
    };

    window.resetTimelineFilters = function () {
        activeTypes = [];
        document.querySelectorAll('.tl-type-check').forEach(cb => { cb.checked = false; });
        updateTypeLabel();
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
