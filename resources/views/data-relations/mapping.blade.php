@extends('layouts.app')
@section('title', "{$systemSlug} — Mapping")

@section('content')

{{-- ─── PAGE HEADER ─── --}}
<div class="page-header">
    <div>
        <nav aria-label="Breadcrumb" class="page-breadcrumb">
            <a href="{{ route('data-relations.index') }}">Data Relations</a>
            <span class="sep">/</span>
            <span class="cur" aria-current="page">{{ $systemSlug }}</span>
        </nav>
        <div class="flex items-center gap-2.5 mt-1">
            <x-channel-badge :type="$systemType" />
            <h1 class="page-title">{{ $systemSlug }}</h1>
        </div>
    </div>
    <form action="{{ route('data-relations.resolve-auto') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-secondary btn-sm">
            ⚡ Auto-Resolve
        </button>
    </form>
</div>

{{-- ─── TOP-LEVEL TABS ─── --}}
@if($hasTabs || $hasWhmcsTabs)
<div class="flex gap-0 border-b border-gray-200 mb-6">
    @if($hasWhmcsTabs)
        @foreach(['clients' => ['label' => 'Clients & Contacts', 'count' => $stats['unlinked']], 'unregistered' => ['label' => 'Unregistered Users', 'count' => $unregisteredStats['unlinked']]] as $tabKey => $tab)
            <a href="{{ request()->fullUrlWithQuery(['tab' => $tabKey, 'view' => 'unlinked', 'q' => '']) }}"
               class="px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition
                      {{ $activeTab === $tabKey ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                {{ $tab['label'] }}
                @if($tab['count'] > 0)
                    <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-xs {{ $activeTab === $tabKey ? 'bg-brand-100 text-brand-700' : 'bg-amber-100 text-amber-700' }}">{{ $tab['count'] }}</span>
                @endif
            </a>
        @endforeach
    @endif
    @if($hasTabs)
        @foreach(['people' => ['label' => 'People', 'count' => $stats['unlinked']], 'channels' => ['label' => 'Channels', 'count' => $conversationStats['unlinked']]] as $tabKey => $tab)
            <a href="{{ request()->fullUrlWithQuery(['tab' => $tabKey, 'view' => 'unlinked', 'q' => '']) }}"
               class="px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition
                      {{ $activeTab === $tabKey ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                {{ $tab['label'] }}
                @if($tab['count'] > 0)
                    <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-xs {{ $activeTab === $tabKey ? 'bg-brand-100 text-brand-700' : 'bg-amber-100 text-amber-700' }}">{{ $tab['count'] }}</span>
                @endif
            </a>
        @endforeach
    @endif
</div>
@endif

{{-- ─── ACCOUNT-BASED (WHMCS, MetricsCube) — Companies with inline contacts ─── --}}
@if($isAccountSystem && $activeTab !== 'unregistered')

@include('data-relations._people-toolbar', ['linkedCount' => $stats['linked'], 'unlinkedCount' => $stats['unlinked']])

@if($activeView === 'unlinked')
    @if($unlinked->isNotEmpty())
    <div class="bg-white rounded-lg border border-gray-200 mobile-overflow-visible overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="tbl-header">
                <tr>
                    @if($systemType === 'whmcs')
                        <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Client ID</th>
                        <th class="px-4 py-2 text-left font-medium">Company name</th>
                        <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Email</th>
                        <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Phone</th>
                        <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Country</th>
                    @else
                        <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Client ID</th>
                        <th class="px-4 py-2 text-left font-medium">Company name</th>
                        <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Email</th>
                    @endif
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($unlinked as $account)
                    <tr class="hover:bg-gray-50" x-data="{ linkOpen: false, dropOpen: false }">
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-500 truncate max-w-[80px] col-mobile-hidden">{{ $account->external_id }}</td>
                        <td class="px-4 py-2.5 text-gray-800 truncate max-w-[180px]">{{ ($account->meta_json ?? [])['company_name'] ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-gray-500 text-xs truncate max-w-[160px] col-mobile-hidden">{{ ($account->meta_json ?? [])['email'] ?? '—' }}</td>
                        @if($systemType === 'whmcs')
                            <td class="px-4 py-2.5 text-gray-500 text-xs truncate col-mobile-hidden">{{ ($account->meta_json ?? [])['phone'] ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs truncate col-mobile-hidden">{{ ($account->meta_json ?? [])['country'] ?? '—' }}</td>
                        @endif
                        <td class="px-4 py-2.5 text-right">
                            {{-- Desktop actions --}}
                            <div class="row-actions-desktop items-center justify-end gap-1.5">
                                <button type="button" @click="linkOpen = true" class="btn btn-sm btn-primary">Link</button>
                                <button type="button"
                                        onclick="openActivityModal({ dataset: { modalSrc: '{{ $account->filter_url }}' } })"
                                        class="btn btn-sm btn-danger">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
                                    Filter
                                </button>
                            </div>
                            {{-- Mobile "..." dropdown --}}
                            <div class="row-actions-mobile relative" @close-row-dropdowns.window="dropOpen = false">
                                <button @click="let o=dropOpen; $dispatch('close-row-dropdowns'); dropOpen=!o"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition font-bold text-base">
                                    ···
                                </button>
                                <div x-show="dropOpen" x-cloak
                                     class="absolute right-0 top-full mt-1 w-44 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50 text-sm">
                                    <button type="button" @click="linkOpen = true; dropOpen = false"
                                            class="flex w-full px-3 py-2 text-brand-700 hover:bg-brand-50 transition text-xs text-left">Link company</button>
                                    <button type="button"
                                            onclick="openActivityModal({ dataset: { modalSrc: '{{ $account->filter_url }}' } })"
                                            class="flex w-full px-3 py-2 text-red-600 hover:bg-red-50 transition text-xs text-left">Filter</button>
                                </div>
                            </div>
                            {{-- Link popup --}}
                            <div x-show="linkOpen" x-cloak @click.self="linkOpen = false" @keydown.escape.window="linkOpen = false" @close-link-popup="linkOpen = false"
                                 class="fixed inset-0 z-[60] flex items-center justify-center bg-black/30 backdrop-blur-sm">
                                <div class="bg-white rounded-xl shadow-2xl p-4 w-80 max-w-[90vw]" @click.stop>
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="text-sm font-semibold text-gray-700">Link to company</span>
                                        <button type="button" @click="linkOpen = false" class="text-gray-400 hover:text-gray-600 -mr-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    @include('data-relations._ac-company', ['action' => route('data-relations.accounts.link', $account)])
                                </div>
                            </div>
                        </td>
                    </tr>
                    @foreach($identitiesByExtId->get((string) $account->external_id, collect()) as $contact)
                    <tr class="border-t-0 contact-subrow" x-data="{ linkOpen: false }">
                        <td class="pl-5 pr-2 py-1.5 col-mobile-hidden">
                            <span class="text-gray-300 text-xs select-none">↳</span>
                        </td>
                        <td class="px-4 py-1.5">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-mono text-gray-600 text-xs truncate">{{ $contact->value }}</span>
                                @if(!empty($contact->meta_json['display_name']))
                                <span class="text-gray-400 text-xs truncate">{{ $contact->meta_json['display_name'] }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="col-mobile-hidden"></td>
                        @if($systemType === 'whmcs')
                        <td class="col-mobile-hidden"></td>
                        <td class="col-mobile-hidden"></td>
                        @endif
                        <td class="px-4 py-1.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if($contact->person)
                                    <a href="{{ route('people.show', $contact->person) }}" class="link font-medium text-xs">{{ $contact->person->full_name }}</a>
                                    <form action="{{ route('data-relations.identities.unlink', $contact) }}" method="POST" class="inline shrink-0">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="row-action-danger">Unlink</button>
                                    </form>
                                @else
                                    <span class="text-gray-400 text-xs">—</span>
                                    <button type="button" @click="linkOpen = true" class="btn btn-sm btn-primary shrink-0">Link</button>
                                @endif
                            </div>
                            <div x-show="linkOpen" x-cloak @click.self="linkOpen = false" @keydown.escape.window="linkOpen = false" @close-link-popup="linkOpen = false"
                                 class="fixed inset-0 z-[60] flex items-center justify-center bg-black/30 backdrop-blur-sm">
                                <div class="bg-white rounded-xl shadow-2xl p-4 w-80 max-w-[90vw]" @click.stop>
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="text-sm font-semibold text-gray-700">Link to person</span>
                                        <button type="button" @click="linkOpen = false" class="text-gray-400 hover:text-gray-600 -mr-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    @include('data-relations._link-person-panel', ['linkUrl' => route('data-relations.identities.link-create', $contact)])
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
        @if($unlinked->hasPages())<div class="px-4 py-3 border-t border-gray-100">{{ $unlinked->links() }}</div>@endif
    </div>
    @else
        <div class="bg-green-50 border border-green-200 rounded-lg px-5 py-4 text-sm text-green-700">All accounts are linked to companies.</div>
    @endif

@else {{-- linked --}}
    @if($linked->isNotEmpty())
    <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm table-fixed min-w-[400px]">
            <colgroup>
                <col class="w-[110px]">
                @if($systemType === 'whmcs')<col class="w-[220px]">@endif
                <col>
                <col class="w-20">
            </colgroup>
            <thead class="tbl-header">
                <tr>
                    <th class="px-4 py-2 text-left font-medium whitespace-nowrap col-mobile-hidden">External ID</th>
                    @if($systemType === 'whmcs')<th class="px-4 py-2 text-left font-medium col-mobile-hidden">Company name (WHMCS)</th>@endif
                    <th class="px-4 py-2 text-left font-medium">Company in Contact Monitor</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($linked as $account)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-500 truncate col-mobile-hidden">{{ $account->external_id }}</td>
                        @if($systemType === 'whmcs')<td class="px-4 py-2.5 text-gray-600 text-xs truncate col-mobile-hidden">{{ ($account->meta_json ?? [])['company_name'] ?? '—' }}</td>@endif
                        <td class="px-4 py-2.5">
                            <a href="{{ route('companies.show', $account->company) }}" class="link font-medium">{{ $account->company->name }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <form action="{{ route('data-relations.accounts.unlink', $account) }}" method="POST" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="row-action-danger">Unlink</button>
                            </form>
                        </td>
                    </tr>
                    @foreach($identitiesByExtId->get((string) $account->external_id, collect()) as $contact)
                    <tr class="border-t-0 contact-subrow" x-data="{ linkOpen: false }">
                        <td class="pl-5 pr-2 py-1.5 col-mobile-hidden">
                            <span class="text-gray-300 text-xs select-none">↳</span>
                        </td>
                        @if($systemType === 'whmcs')
                        <td class="px-4 py-1.5 col-mobile-hidden">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-mono text-gray-600 text-xs truncate">{{ $contact->value }}</span>
                                @if(!empty($contact->meta_json['display_name']))
                                <span class="text-gray-400 text-xs truncate">{{ $contact->meta_json['display_name'] }}</span>
                                @endif
                            </div>
                        </td>
                        @endif
                        <td class="px-4 py-1.5">
                            @if($contact->person)
                                <a href="{{ route('people.show', $contact->person) }}" class="link font-medium text-xs truncate">{{ $contact->person->full_name }}</a>
                            @else
                                <span class="text-gray-400 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-1.5 text-right">
                            @if($contact->person)
                                <form action="{{ route('data-relations.identities.unlink', $contact) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="row-action-danger">Unlink</button>
                                </form>
                            @else
                                <button type="button" @click="linkOpen = true" class="btn btn-sm btn-primary">Link</button>
                            @endif
                            <div x-show="linkOpen" x-cloak @click.self="linkOpen = false" @keydown.escape.window="linkOpen = false" @close-link-popup="linkOpen = false"
                                 class="fixed inset-0 z-[60] flex items-center justify-center bg-black/30 backdrop-blur-sm">
                                <div class="bg-white rounded-xl shadow-2xl p-4 w-80 max-w-[90vw]" @click.stop>
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="text-sm font-semibold text-gray-700">Link to person</span>
                                        <button type="button" @click="linkOpen = false" class="text-gray-400 hover:text-gray-600 -mr-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    @include('data-relations._link-person-panel', ['linkUrl' => route('data-relations.identities.link-create', $contact)])
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
        @if($linked->hasPages())<div class="px-4 py-3 border-t border-gray-100">{{ $linked->links() }}</div>@endif
    </div>
    @else
        <div class="bg-gray-50 border border-gray-200 rounded-lg px-5 py-4 text-sm text-gray-500">No linked accounts.</div>
    @endif

@endif {{-- end activeView --}}

{{-- ─── UNREGISTERED USERS (WHMCS/MetricsCube: ticket senders without a client account) ─── --}}
@if($isAccountSystem && $activeTab === 'unregistered')
<div class="mb-4 text-sm text-gray-500">
    Email addresses that appeared in tickets or other activity but are <strong>not registered WHMCS clients</strong>.
    Auto-Resolve will try to match them to existing people by email.
</div>
@if($unregisteredUsers->isNotEmpty())
<div class="bg-white rounded-lg border border-gray-200 mobile-overflow-visible">
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th class="px-4 py-2 text-left font-medium">Email address</th>
                <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Display name</th>
                <th class="px-4 py-2 text-left font-medium">Person</th>
                <th class="px-4 py-2 text-right font-medium">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($unregisteredUsers as $identity)
            <tr class="hover:bg-gray-50" x-data="{ linkOpen: false, dropOpen: false }">
                <td class="px-4 py-2.5 font-mono text-xs text-gray-700 truncate max-w-[180px]">{{ $identity->value }}</td>
                <td class="px-4 py-2.5 col-mobile-hidden">
                    <div class="flex items-center gap-2">
                        <img src="https://www.gravatar.com/avatar/{{ $identity->gravatar_hash }}?d=identicon&s=40"
                             class="w-6 h-6 rounded-full object-cover shrink-0">
                        <span class="text-gray-600 text-xs">{{ $identity->meta_json['display_name'] ?? '—' }}</span>
                    </div>
                </td>
                <td class="px-4 py-2.5">
                    @if($identity->person)
                        <a href="{{ route('people.show', $identity->person) }}" class="link font-medium text-xs">{{ $identity->person->full_name }}</a>
                        <form action="{{ route('data-relations.identities.unlink', $identity) }}" method="POST" class="inline ml-2">
                            @csrf @method('DELETE')
                            <button type="submit" class="row-action-danger">Unlink</button>
                        </form>
                    @else
                        <span class="text-gray-400 text-xs">—</span>
                    @endif
                </td>
                <td class="px-4 py-2.5 text-right">
                    {{-- Desktop actions --}}
                    <div class="row-actions-desktop items-center justify-end gap-1.5">
                        @if(!$identity->person)
                            <button type="button" @click="linkOpen = true" class="btn btn-sm btn-primary">Link</button>
                        @endif
                        @if(!$identity->person)
                            <button type="button"
                                    onclick="openActivityModal({ dataset: { modalSrc: '{{ $identity->filter_url }}' } })"
                                    class="btn btn-sm btn-danger">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
                                Filter
                            </button>
                        @endif
                    </div>
                    {{-- Mobile "..." dropdown --}}
                    <div class="row-actions-mobile relative" @close-row-dropdowns.window="dropOpen = false">
                        <button @click="let o=dropOpen; $dispatch('close-row-dropdowns'); dropOpen=!o"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition font-bold text-base">
                            ···
                        </button>
                        <div x-show="dropOpen" x-cloak
                             class="absolute right-0 top-full mt-1 w-44 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50 text-sm">
                            @if(!$identity->person)
                                <button type="button" @click="linkOpen = true; dropOpen = false"
                                        class="flex w-full px-3 py-2 text-brand-700 hover:bg-brand-50 transition text-xs text-left">Link to person</button>
                            @endif
                            @if(!$identity->person)
                                <button type="button"
                                        onclick="openActivityModal({ dataset: { modalSrc: '{{ $identity->filter_url }}' } })"
                                        class="flex w-full px-3 py-2 text-red-600 hover:bg-red-50 transition text-xs text-left">
                                    Filter
                                </button>
                            @endif
                        </div>
                    </div>
                    {{-- Link popup --}}
                    <div x-show="linkOpen" x-cloak @click.self="linkOpen = false" @keydown.escape.window="linkOpen = false" @close-link-popup="linkOpen = false"
                         class="fixed inset-0 z-[60] flex items-center justify-center bg-black/30 backdrop-blur-sm">
                        <div class="bg-white rounded-xl shadow-2xl p-4 w-80 max-w-[90vw]" @click.stop>
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-semibold text-gray-700">Link to person</span>
                                <button type="button" @click="linkOpen = false" class="text-gray-400 hover:text-gray-600 -mr-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                </button>
                            </div>
                            @include('data-relations._link-person-panel', ['linkUrl' => route('data-relations.identities.link-create', $identity)])
                        </div>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@else
    <div class="bg-green-50 border border-green-200 rounded-lg px-5 py-4 text-sm text-green-700">No unregistered users found.</div>
@endif
@endif

{{-- ─── IDENTITY-BASED (IMAP, Slack, Discord) ─── --}}
@elseif($activeTab === 'people' || !$hasTabs)

@include('data-relations._people-toolbar', ['linkedCount' => $stats['linked'], 'unlinkedCount' => $stats['unlinked']])

@if($activeView === 'unlinked')
    @if($unlinked->isNotEmpty())
    <div class="bg-white rounded-lg border border-gray-200 mobile-overflow-visible">
        <table class="w-full text-sm">
            <thead class="tbl-header">
                <tr>
                    @if($systemType === 'imap')
                        <th class="px-4 py-2 text-left font-medium">Email address</th>
                        <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Display name</th>
                    @elseif($systemType === 'slack')
                        <th class="px-4 py-2 text-left font-medium"><span class="hidden md:inline">Slack user ID</span><span class="md:hidden">User</span></th>
                        <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Display name</th>
                        <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Email</th>
                    @elseif($systemType === 'discord')
                        <th class="px-4 py-2 text-left font-medium"><span class="hidden md:inline">Discord user ID</span><span class="md:hidden">User</span></th>
                        <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Username</th>
                    @else
                        <th class="px-4 py-2 text-left font-medium">Identity value</th>
                        <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Display name</th>
                    @endif
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($unlinked as $identity)
                    <tr class="hover:bg-gray-50" x-data="{ linkOpen: false, dropOpen: false }">
                        <td class="px-4 py-2.5">
                            @if($systemType === 'slack' || $systemType === 'discord')
                                {{-- Mobile: avatar + display name --}}
                                <div class="flex items-center gap-2 md:hidden">
                                    <span class="relative w-6 h-6 shrink-0 inline-flex rounded-full">
                                        <span class="w-6 h-6 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-[10px] font-bold absolute inset-0">{{ strtoupper(substr($identity->meta_json['display_name'] ?? $identity->value, 0, 1)) }}</span>
                                        @if($identity->sys_avatar)
                                            <img src="{{ $identity->sys_avatar }}" class="w-6 h-6 rounded-full object-cover absolute inset-0" onerror="this.style.display='none'">
                                        @elseif($identity->gravatar_hash)
                                            <img src="https://www.gravatar.com/avatar/{{ $identity->gravatar_hash }}?d=identicon&s=40" class="w-6 h-6 rounded-full object-cover absolute inset-0" onerror="this.style.display='none'">
                                        @endif
                                    </span>
                                    <span class="text-gray-700 text-xs truncate max-w-[140px]">{{ $identity->meta_json['display_name'] ?? $identity->value }}</span>
                                </div>
                                {{-- Desktop: raw user ID --}}
                                <span class="hidden md:inline font-mono text-xs text-gray-700">{{ $identity->value }}</span>
                            @else
                                <span class="font-mono text-xs text-gray-700 truncate max-w-[180px]">{{ $identity->value }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 col-mobile-hidden">
                            <div class="flex items-center gap-2">
                                <span class="relative w-6 h-6 shrink-0 inline-flex rounded-full">
                                    <span class="w-6 h-6 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-[10px] font-bold absolute inset-0">{{ strtoupper(substr($identity->meta_json['display_name'] ?? $identity->value, 0, 1)) }}</span>
                                    @if($identity->sys_avatar)
                                        <img src="{{ $identity->sys_avatar }}" class="w-6 h-6 rounded-full object-cover absolute inset-0" onerror="this.style.display='none'">
                                    @elseif($identity->gravatar_hash)
                                        <img src="https://www.gravatar.com/avatar/{{ $identity->gravatar_hash }}?d=identicon&s=40" class="w-6 h-6 rounded-full object-cover absolute inset-0" onerror="this.style.display='none'">
                                    @endif
                                </span>
                                <span class="text-gray-600 text-xs">{{ $identity->meta_json['display_name'] ?? '—' }}</span>
                            </div>
                        </td>
                        @if($systemType === 'slack')
                            <td class="px-4 py-2.5 text-gray-500 text-xs col-mobile-hidden">{{ $identity->meta_json['email_hint'] ?? '—' }}</td>
                        @endif
                        <td class="px-4 py-2.5 text-right">
                            {{-- Desktop actions --}}
                            <div class="row-actions-desktop items-center justify-end gap-1.5">
                                <button type="button" @click="linkOpen = true" class="btn btn-sm btn-primary">Link</button>
                                @if($identity->has_filter_data)
                                    <button type="button"
                                            onclick="openActivityModal({ dataset: { modalSrc: '{{ $identity->filter_url }}' } })"
                                            class="btn btn-sm btn-danger">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
                                        Filter
                                    </button>
                                @endif
                            </div>
                            {{-- Mobile "..." dropdown --}}
                            <div class="row-actions-mobile relative" @close-row-dropdowns.window="dropOpen = false">
                                <button @click="let o=dropOpen; $dispatch('close-row-dropdowns'); dropOpen=!o"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition font-bold text-base">
                                    ···
                                </button>
                                <div x-show="dropOpen" x-cloak
                                     class="absolute right-0 top-full mt-1 w-44 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50 text-sm">
                                    <button type="button" @click="linkOpen = true; dropOpen = false"
                                            class="flex w-full px-3 py-2 text-brand-700 hover:bg-brand-50 transition text-xs text-left">Link to person</button>
                                    @if($identity->has_filter_data)
                                        <button type="button"
                                                onclick="openActivityModal({ dataset: { modalSrc: '{{ $identity->filter_url }}' } })"
                                                class="flex w-full px-3 py-2 text-red-600 hover:bg-red-50 transition text-xs text-left">
                                            Filter
                                        </button>
                                    @endif
                                </div>
                            </div>
                            {{-- Link popup --}}
                            <div x-show="linkOpen" x-cloak @click.self="linkOpen = false" @keydown.escape.window="linkOpen = false" @close-link-popup="linkOpen = false"
                                 class="fixed inset-0 z-[60] flex items-center justify-center bg-black/30 backdrop-blur-sm">
                                <div class="bg-white rounded-xl shadow-2xl p-4 w-80 max-w-[90vw]" @click.stop>
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="text-sm font-semibold text-gray-700">Link to person</span>
                                        <button type="button" @click="linkOpen = false" class="text-gray-400 hover:text-gray-600 -mr-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    @include('data-relations._link-person-panel', ['linkUrl' => route('data-relations.identities.link-create', $identity)])
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($unlinked->hasPages())<div class="px-4 py-3 border-t border-gray-100">{{ $unlinked->links() }}</div>@endif
    </div>
    @else
        <div class="bg-green-50 border border-green-200 rounded-lg px-5 py-4 text-sm text-green-700">All identities are linked to people.</div>
    @endif

@else {{-- linked --}}
    @if($linked->isNotEmpty())
    <div class="bg-white rounded-lg border border-gray-200 mobile-overflow-visible">
        <table class="w-full text-sm">
            <thead class="tbl-header">
                <tr>
                    <th class="px-4 py-2 text-left font-medium">
                        @if($systemType === 'slack')<span class="hidden md:inline">Slack user ID</span><span class="md:hidden">User</span>
                        @elseif($systemType === 'discord')<span class="hidden md:inline">Discord user ID</span><span class="md:hidden">User</span>
                        @else Identity value
                        @endif
                    </th>
                    <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Display name</th>
                    @if($systemType === 'slack')<th class="px-4 py-2 text-left font-medium col-mobile-hidden">Email</th>@endif
                    <th class="px-4 py-2 text-left font-medium">Person in Contact Monitor</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($linked as $identity)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5">
                            @if($systemType === 'slack' || $systemType === 'discord')
                                {{-- Mobile: avatar + display name --}}
                                <div class="flex items-center gap-2 md:hidden">
                                    @if($identity->sys_avatar)
                                        <img src="{{ $identity->sys_avatar }}" class="w-6 h-6 rounded-full object-cover shrink-0">
                                    @elseif($identity->gravatar_hash)
                                        <img src="https://www.gravatar.com/avatar/{{ $identity->gravatar_hash }}?d=identicon&s=40" class="w-6 h-6 rounded-full object-cover shrink-0">
                                    @endif
                                    <span class="text-gray-600 text-xs truncate max-w-[140px]">{{ $identity->meta_json['display_name'] ?? $identity->value }}</span>
                                </div>
                                {{-- Desktop: raw user ID --}}
                                <span class="hidden md:inline font-mono text-xs text-gray-600">{{ $identity->value }}</span>
                            @else
                                <span class="font-mono text-xs text-gray-600 truncate max-w-[180px]">{{ $identity->value }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 col-mobile-hidden">
                            <div class="flex items-center gap-2">
                                @if($identity->sys_avatar)
                                    <img src="{{ $identity->sys_avatar }}"
                                         class="w-6 h-6 rounded-full object-cover shrink-0">
                                @elseif($identity->gravatar_hash)
                                    <img src="https://www.gravatar.com/avatar/{{ $identity->gravatar_hash }}?d=identicon&s=40"
                                         class="w-6 h-6 rounded-full object-cover shrink-0">
                                @endif
                                <span class="text-gray-500 text-xs">{{ $identity->meta_json['display_name'] ?? '—' }}</span>
                            </div>
                        </td>
                        @if($systemType === 'slack')<td class="px-4 py-2.5 text-gray-500 text-xs col-mobile-hidden">{{ $identity->meta_json['email_hint'] ?? '—' }}</td>@endif
                        <td class="px-4 py-2.5"><a href="{{ route('people.show', $identity->person) }}" class="link font-medium">{{ $identity->person->first_name }} {{ $identity->person->last_name }}</a></td>
                        <td class="px-4 py-2.5 text-right">
                            {{-- Desktop actions --}}
                            <div class="row-actions-desktop items-center justify-end gap-1.5">
                                <form action="{{ route('data-relations.identities.unlink', $identity) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                        Unlink
                                    </button>
                                </form>
                            </div>
                            {{-- Mobile "..." dropdown --}}
                            <div class="row-actions-mobile relative" x-data="{ open: false }" @click.outside="open = false" @close-row-dropdowns.window="open = false">
                                <button @click="let o=open; $dispatch('close-row-dropdowns'); open=!o"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition font-bold text-base">
                                    ···
                                </button>
                                <div x-show="open" x-cloak
                                     class="absolute right-0 top-full mt-1 w-44 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50 text-sm">
                                    <form action="{{ route('data-relations.identities.unlink', $identity) }}" method="POST" class="contents">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="flex w-full px-3 py-2 text-red-600 hover:bg-red-50 transition text-xs text-left">Unlink</button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($linked->hasPages())<div class="px-4 py-3 border-t border-gray-100">{{ $linked->links() }}</div>@endif
    </div>
    @else
        <div class="bg-gray-50 border border-gray-200 rounded-lg px-5 py-4 text-sm text-gray-500">No linked identities.</div>
    @endif
@endif

@endif {{-- end identity-based --}}

{{-- ─── TAB: CHANNELS (Discord / Slack) ─── --}}
@if($activeTab === 'channels' && $conversationStats !== null)
<div class="flex flex-wrap items-center gap-3 mb-4">
    <div class="flex-1 min-w-0 flex gap-2 items-center">
        <input type="text" id="ch-search" placeholder="Search…"
               class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-full max-w-[280px] focus:outline-none focus:border-brand-400">
        <button type="button" onclick="filterChannels()"
                class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded-lg transition shrink-0">Search</button>
    </div>
    <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm shrink-0">
        <button type="button" id="ch-btn-unlinked" onclick="setChannelView('unlinked')"
                class="px-4 py-1.5 font-medium transition border-r border-gray-200 bg-amber-50 text-amber-700">
            Unlinked <span class="font-bold">{{ $conversationStats['unlinked'] }}</span>
        </button>
        <button type="button" id="ch-btn-linked" onclick="setChannelView('linked')"
                class="px-4 py-1.5 font-medium transition bg-white text-gray-500 hover:bg-gray-50">
            Linked <span class="font-bold">{{ $conversationStats['linked'] }}</span>
        </button>
    </div>
</div>
<div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
    <table id="ch-table" class="w-full text-sm min-w-[480px]">
        <thead class="tbl-header">
            <tr>
                <th class="px-4 py-2 text-left font-medium">Channel</th>
                <th class="px-4 py-2 text-left font-medium">Company in Contact Monitor</th>
                <th class="px-4 py-2 text-left font-medium w-72">Link / Change</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($conversations as $conv)
                <tr class="hover:bg-gray-50" data-linked="{{ $conv->company_id ? '1' : '0' }}">
                    <td class="px-4 py-2.5 font-medium text-gray-800">{{ $conv->subject ?: $conv->external_thread_id }}</td>
                    <td class="px-4 py-2.5">
                        @if($conv->company)
                            <a href="{{ route('companies.show', $conv->company) }}" class="link font-medium">{{ $conv->company->name }}</a>
                        @else
                            <span class="text-gray-400 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5">@include('data-relations._ac-company', ['action' => route('data-relations.conversations.link', $conv), 'placeholder' => $conv->company ? 'Change…' : 'Search company…'])</td>
                    <td class="px-4 py-2.5 text-right">
                        @if($conv->company_id)
                            <form action="{{ route('data-relations.conversations.unlink', $conv) }}" method="POST" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="row-action-danger">Unlink</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@endsection

@push('scripts')
<script>
// ── Channels tab: search + linked/unlinked filter ──────────────────────────
(function () {
    let chView = 'unlinked';

    function filterChannels() {
        const q = (document.getElementById('ch-search')?.value ?? '').toLowerCase();
        document.querySelectorAll('#ch-table tbody tr').forEach(tr => {
            const text = (tr.querySelector('td')?.textContent ?? '').toLowerCase();
            const linked = tr.dataset.linked === '1';
            const matchView = chView === 'unlinked' ? !linked : linked;
            tr.style.display = (!q || text.includes(q)) && matchView ? '' : 'none';
        });
    }

    function setChannelView(view) {
        chView = view;
        const btnU = document.getElementById('ch-btn-unlinked');
        const btnL = document.getElementById('ch-btn-linked');
        if (!btnU || !btnL) return;
        btnU.className = view === 'unlinked'
            ? 'px-4 py-1.5 font-medium transition border-r border-gray-200 bg-amber-50 text-amber-700'
            : 'px-4 py-1.5 font-medium transition border-r border-gray-200 bg-white text-gray-500 hover:bg-gray-50';
        btnL.className = view === 'linked'
            ? 'px-4 py-1.5 font-medium transition bg-green-50 text-green-700'
            : 'px-4 py-1.5 font-medium transition bg-white text-gray-500 hover:bg-gray-50';
        filterChannels();
    }

    window.setChannelView = setChannelView;
    window.filterChannels = filterChannels;

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('ch-search')?.addEventListener('input', filterChannels);
        setChannelView('unlinked');
    });
})();

// ── Autocomplete ───────────────────────────────────────────────────────────
(function () {
    let debounceTimer = null;

    function initAutocomplete(container) {
        const input   = container.querySelector('.ac-input');
        const hidden  = container.querySelector('.ac-value');
        const results = container.querySelector('.ac-results');
        const submit  = container.closest('form').querySelector('.ac-submit');
        const url     = container.dataset.searchUrl;

        function clearSelection() {
            hidden.value = '';
            if (submit) submit.disabled = true;
        }

        function showResults(items) {
            results.innerHTML = '';
            if (!items.length) { results.classList.add('hidden'); return; }
            items.forEach(item => {
                const li = document.createElement('li');
                li.textContent = item.name;
                li.className = 'px-3 py-1.5 cursor-pointer hover:bg-brand-50 hover:text-brand-700';
                li.addEventListener('mousedown', e => {
                    e.preventDefault();
                    input.value  = item.name;
                    hidden.value = item.id;
                    results.classList.add('hidden');
                    if (submit) submit.disabled = false;
                });
                results.appendChild(li);
            });
            results.classList.remove('hidden');
        }

        input.addEventListener('input', () => {
            clearSelection();
            clearTimeout(debounceTimer);
            const q = input.value.trim();
            if (q.length < 2) { results.classList.add('hidden'); return; }
            debounceTimer = setTimeout(() => {
                fetch(`${url}?q=${encodeURIComponent(q)}`)
                    .then(r => r.json())
                    .then(showResults)
                    .catch(() => results.classList.add('hidden'));
            }, 250);
        });

        document.addEventListener('click', e => {
            if (!container.contains(e.target)) results.classList.add('hidden');
        });
    }

    document.querySelectorAll('[data-autocomplete]').forEach(initAutocomplete);
})();
</script>
@endpush
