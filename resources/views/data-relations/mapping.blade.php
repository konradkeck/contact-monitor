@extends('layouts.app')
@section('title', "{$systemSlug} — Mapping")

@php
    $hasTabs    = $conversationStats !== null; // Discord / Slack
    $hasWhmcsTabs = $isAccountSystem && $unregisteredStats !== null;
    $activeTab  = request('tab', $hasWhmcsTabs ? 'clients' : 'people');
    $activeView = request('view', 'unlinked');
    $q          = request('q', '');
@endphp

@section('content')

{{-- ─── PAGE HEADER ─── --}}
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
        <x-channel-badge :type="$systemType" />
        <h1 class="text-2xl font-bold text-gray-900">{{ $systemSlug }}</h1>
    </div>

    <form action="{{ route('data-relations.resolve-auto') }}" method="POST">
        @csrf
        <button type="submit"
                class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium
                       text-gray-700 hover:border-brand-400 hover:text-brand-700 transition">
            ⚡ Auto-Resolve
        </button>
    </form>
</div>

{{-- ─── TOP-LEVEL TABS ─── --}}
@if($hasTabs || $hasWhmcsTabs)
<div class="flex gap-0 border-b border-gray-200 mb-6">
    @if($hasWhmcsTabs)
        @php
            $whmcsTabs = [
                'clients'      => ['label' => 'Clients & Contacts', 'count' => $stats['unlinked']],
                'unregistered' => ['label' => 'Unregistered Users',  'count' => $unregisteredStats['unlinked']],
            ];
        @endphp
        @foreach($whmcsTabs as $tabKey => $tab)
            @php $isActive = $activeTab === $tabKey; @endphp
            <a href="{{ request()->fullUrlWithQuery(['tab' => $tabKey, 'view' => 'unlinked', 'q' => '']) }}"
               class="px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition
                      {{ $isActive ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                {{ $tab['label'] }}
                @if($tab['count'] > 0)
                    <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-xs {{ $isActive ? 'bg-brand-100 text-brand-700' : 'bg-amber-100 text-amber-700' }}">{{ $tab['count'] }}</span>
                @endif
            </a>
        @endforeach
    @endif
    @if($hasTabs)
        @foreach(['people' => ['label' => 'People', 'count' => $stats['unlinked']], 'channels' => ['label' => 'Channels', 'count' => $conversationStats['unlinked']]] as $tabKey => $tab)
            @php $isActive = $activeTab === $tabKey; @endphp
            <a href="{{ request()->fullUrlWithQuery(['tab' => $tabKey, 'view' => 'unlinked', 'q' => '']) }}"
               class="px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition
                      {{ $isActive ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                {{ $tab['label'] }}
                @if($tab['count'] > 0)
                    <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-xs {{ $isActive ? 'bg-brand-100 text-brand-700' : 'bg-amber-100 text-amber-700' }}">{{ $tab['count'] }}</span>
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
    <div class="bg-white rounded-lg border border-gray-200">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
                <tr>
                    @if($systemType === 'whmcs')
                        <th class="px-4 py-2 text-left font-medium">Client ID</th>
                        <th class="px-4 py-2 text-left font-medium">Company name</th>
                        <th class="px-4 py-2 text-left font-medium">Email</th>
                        <th class="px-4 py-2 text-left font-medium">Phone</th>
                        <th class="px-4 py-2 text-left font-medium">Country</th>
                    @else
                        <th class="px-4 py-2 text-left font-medium">Client ID</th>
                        <th class="px-4 py-2 text-left font-medium">Company name</th>
                        <th class="px-4 py-2 text-left font-medium">Email</th>
                    @endif
                    <th class="px-4 py-2 text-left font-medium w-72">Link to company</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($unlinked as $account)
                    @php
                        $meta       = $account->meta_json ?? [];
                        $acEmail    = strtolower(trim($meta['email'] ?? ''));
                        $acName     = $meta['company_name'] ?? $account->external_id;
                        $acFmUrl    = route('filtering.identity-filter-modal') . '?' . http_build_query(array_filter(['email' => $acEmail, 'domain' => $acEmail ? substr(strrchr($acEmail, '@'), 1) : '', 'name' => $acName]));
                        $acContacts = $identitiesByExtId->get((string) $account->external_id, collect());
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-500">{{ $account->external_id }}</td>
                        <td class="px-4 py-2.5 text-gray-800">{{ $meta['company_name'] ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $meta['email'] ?? '—' }}</td>
                        @if($systemType === 'whmcs')
                            <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $meta['phone'] ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $meta['country'] ?? '—' }}</td>
                        @endif
                        <td class="px-4 py-2.5 flex items-center gap-2">
                            @include('data-relations._ac-company', ['action' => route('data-relations.accounts.link', $account)])
                            <button type="button"
                                    onclick="openActivityModal({ dataset: { modalSrc: '{{ $acFmUrl }}' } })"
                                    class="btn btn-sm btn-danger shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
                                Filter
                            </button>
                        </td>
                    </tr>
                    @if($acContacts->isNotEmpty())
                    <tr class="bg-gray-50 border-t-0">
                        <td colspan="{{ $systemType === 'whmcs' ? 6 : 4 }}" class="px-6 pb-2 pt-0">
                            <table class="w-full text-xs">
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($acContacts as $contact)
                                    <tr>
                                        <td class="py-1.5 pr-3 font-mono text-gray-500 w-48">{{ $contact->value }}</td>
                                        <td class="py-1.5 pr-3 text-gray-500">{{ $contact->meta_json['display_name'] ?? '—' }}</td>
                                        <td class="py-1.5 pr-3 w-64">
                                            @if($contact->person)
                                                <a href="{{ route('people.show', $contact->person) }}" class="text-brand-700 hover:underline font-medium">{{ $contact->person->full_name }}</a>
                                                <form action="{{ route('data-relations.identities.unlink', $contact) }}" method="POST" class="inline ml-2">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-red-400 hover:text-red-600">unlink</button>
                                                </form>
                                            @else
                                                @include('data-relations._ac-person', ['action' => route('data-relations.identities.link', $contact)])
                                            @endif
                                        </td>
                                        <td class="py-1.5">
                                            <form action="{{ route('data-relations.identities.toggle-team-member', $contact) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" title="{{ $contact->is_team_member ? 'Our Org — click to remove' : 'Mark as Our Organization' }}"
                                                        class="btn btn-sm {{ $contact->is_team_member ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'btn-muted' }}">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M4 21V7l8-4 8 4v14M9 21v-6h6v6"/></svg>
                                                    Our Org
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @endif
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
    <div class="bg-white rounded-lg border border-gray-200">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
                <tr>
                    <th class="px-4 py-2 text-left font-medium">External ID</th>
                    @if($systemType === 'whmcs')<th class="px-4 py-2 text-left font-medium">Company name (WHMCS)</th>@endif
                    <th class="px-4 py-2 text-left font-medium">Company in Contact Monitor</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($linked as $account)
                    @php
                        $meta       = $account->meta_json ?? [];
                        $acContacts = $identitiesByExtId->get((string) $account->external_id, collect());
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-500">{{ $account->external_id }}</td>
                        @if($systemType === 'whmcs')<td class="px-4 py-2.5 text-gray-600 text-xs">{{ $meta['company_name'] ?? '—' }}</td>@endif
                        <td class="px-4 py-2.5"><a href="{{ route('companies.show', $account->company) }}" class="text-brand-700 hover:underline font-medium">{{ $account->company->name }}</a></td>
                        <td class="px-4 py-2.5 text-right">
                            <form action="{{ route('data-relations.accounts.unlink', $account) }}" method="POST" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600">unlink</button>
                            </form>
                        </td>
                    </tr>
                    @if($acContacts->isNotEmpty())
                    <tr class="bg-gray-50 border-t-0">
                        <td colspan="{{ $systemType === 'whmcs' ? 4 : 3 }}" class="px-6 pb-2 pt-0">
                            <table class="w-full text-xs">
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($acContacts as $contact)
                                    <tr>
                                        <td class="py-1.5 pr-3 font-mono text-gray-500 w-48">{{ $contact->value }}</td>
                                        <td class="py-1.5 pr-3 text-gray-500">{{ $contact->meta_json['display_name'] ?? '—' }}</td>
                                        <td class="py-1.5 pr-3 w-64">
                                            @if($contact->person)
                                                <a href="{{ route('people.show', $contact->person) }}" class="text-brand-700 hover:underline font-medium">{{ $contact->person->full_name }}</a>
                                                <form action="{{ route('data-relations.identities.unlink', $contact) }}" method="POST" class="inline ml-2">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-red-400 hover:text-red-600">unlink</button>
                                                </form>
                                            @else
                                                @include('data-relations._ac-person', ['action' => route('data-relations.identities.link', $contact)])
                                            @endif
                                        </td>
                                        <td class="py-1.5">
                                            <form action="{{ route('data-relations.identities.toggle-team-member', $contact) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" title="{{ $contact->is_team_member ? 'Our Org — click to remove' : 'Mark as Our Organization' }}"
                                                        class="btn btn-sm {{ $contact->is_team_member ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'btn-muted' }}">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M4 21V7l8-4 8 4v14M9 21v-6h6v6"/></svg>
                                                    Our Org
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @endif
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
<div class="bg-white rounded-lg border border-gray-200">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
            <tr>
                <th class="px-4 py-2 text-left font-medium">Email address</th>
                <th class="px-4 py-2 text-left font-medium">Display name</th>
                <th class="px-4 py-2 text-left font-medium w-72">Person in Contact Monitor</th>
                <th class="px-4 py-2 text-left font-medium">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($unregisteredUsers as $identity)
            @php
                $gHash  = md5(strtolower(trim($identity->value)));
                $idFmUrl = route('filtering.identity-filter-modal') . '?' . http_build_query(array_filter([
                    'email'  => $identity->value,
                    'domain' => substr(strrchr($identity->value, '@'), 1),
                    'name'   => $identity->meta_json['display_name'] ?? '',
                ]));
            @endphp
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2.5 font-mono text-xs text-gray-700">{{ $identity->value }}</td>
                <td class="px-4 py-2.5">
                    <div class="flex items-center gap-2">
                        <img src="https://www.gravatar.com/avatar/{{ $gHash }}?d=identicon&s=40"
                             class="w-6 h-6 rounded-full object-cover shrink-0">
                        <span class="text-gray-600 text-xs">{{ $identity->meta_json['display_name'] ?? '—' }}</span>
                    </div>
                </td>
                <td class="px-4 py-2.5 flex items-center gap-2">
                    @if($identity->person)
                        <a href="{{ route('people.show', $identity->person) }}" class="text-brand-700 hover:underline font-medium text-xs">{{ $identity->person->full_name }}</a>
                        <form action="{{ route('data-relations.identities.unlink', $identity) }}" method="POST" class="inline ml-2">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-400 hover:text-red-600">unlink</button>
                        </form>
                    @else
                        @include('data-relations._ac-person', ['action' => route('data-relations.identities.link', $identity)])
                    @endif
                </td>
                <td class="px-4 py-2.5">
                    <div class="flex items-center gap-1.5">
                        <form action="{{ route('data-relations.identities.toggle-team-member', $identity) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" title="{{ $identity->is_team_member ? 'Our Org — click to remove' : 'Mark as Our Organization' }}"
                                    class="btn btn-sm {{ $identity->is_team_member ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'btn-muted' }}">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M4 21V7l8-4 8 4v14M9 21v-6h6v6"/></svg>
                                Our Org
                            </button>
                        </form>
                        @if(!$identity->person)
                            <button type="button"
                                    onclick="openActivityModal({ dataset: { modalSrc: '{{ $idFmUrl }}' } })"
                                    class="btn btn-sm btn-danger">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
                                Filter
                            </button>
                        @endif
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
    <div class="bg-white rounded-lg border border-gray-200">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
                <tr>
                    @if($systemType === 'imap')
                        <th class="px-4 py-2 text-left font-medium">Email address</th>
                        <th class="px-4 py-2 text-left font-medium">Display name</th>
                    @elseif($systemType === 'slack')
                        <th class="px-4 py-2 text-left font-medium">Slack user ID</th>
                        <th class="px-4 py-2 text-left font-medium">Display name</th>
                        <th class="px-4 py-2 text-left font-medium">Email</th>
                    @elseif($systemType === 'discord')
                        <th class="px-4 py-2 text-left font-medium">Discord user ID</th>
                        <th class="px-4 py-2 text-left font-medium">Username</th>
                    @else
                        <th class="px-4 py-2 text-left font-medium">Identity value</th>
                        <th class="px-4 py-2 text-left font-medium">Display name</th>
                    @endif
                    <th class="px-4 py-2 text-left font-medium w-72">Link to person</th>
                    <th class="px-4 py-2 text-left font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($unlinked as $identity)
                    @php
                        $gEmail = $identity->type === 'email' ? $identity->value : ($identity->meta_json['email_hint'] ?? null);
                        $gHash  = $gEmail ? md5(strtolower(trim($gEmail))) : null;
                        $sysAvatar = null;
                        if (!empty($identity->meta_json['avatar'])) {
                            if (in_array($identity->type, ['discord_user', 'discord_id'])) {
                                $sysAvatar = 'https://cdn.discordapp.com/avatars/' . $identity->value_normalized . '/' . $identity->meta_json['avatar'] . '.webp?size=40';
                            } elseif ($identity->type === 'slack_user') {
                                $sysAvatar = $identity->meta_json['avatar'];
                            }
                        }
                        $idFmEmail  = $gEmail ?? '';
                        $idFmDomain = $idFmEmail ? substr(strrchr($idFmEmail, '@'), 1) : '';
                        $idFmName   = $identity->meta_json['display_name'] ?? $identity->value;
                        $idFmUrl    = route('filtering.identity-filter-modal') . '?' . http_build_query(array_filter(['email' => $idFmEmail, 'domain' => $idFmDomain, 'name' => $idFmName]));
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-700">{{ $identity->value }}</td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                @if($sysAvatar)
                                    <img src="{{ $sysAvatar }}"
                                         class="w-6 h-6 rounded-full object-cover shrink-0">
                                @elseif($gHash)
                                    <img src="https://www.gravatar.com/avatar/{{ $gHash }}?d=identicon&s=40"
                                         class="w-6 h-6 rounded-full object-cover shrink-0">
                                @endif
                                <span class="text-gray-600 text-xs">{{ $identity->meta_json['display_name'] ?? '—' }}</span>
                            </div>
                        </td>
                        @if($systemType === 'slack')
                            <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $identity->meta_json['email_hint'] ?? '—' }}</td>
                        @endif
                        <td class="px-4 py-2.5">
                            @include('data-relations._ac-person', ['action' => route('data-relations.identities.link', $identity)])
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-1.5">
                                <form action="{{ route('data-relations.identities.toggle-team-member', $identity) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" title="{{ $identity->is_team_member ? 'Our Org — click to remove' : 'Mark as Our Organization' }}"
                                            class="btn btn-sm {{ $identity->is_team_member ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'btn-muted' }}">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M4 21V7l8-4 8 4v14M9 21v-6h6v6"/></svg>
                                        Our Org
                                    </button>
                                </form>
                                @if($idFmEmail || $idFmDomain)
                                    <button type="button"
                                            onclick="openActivityModal({ dataset: { modalSrc: '{{ $idFmUrl }}' } })"
                                            class="btn btn-sm btn-danger">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
                                        Filter
                                    </button>
                                @endif
                                @if(in_array($systemType, ['discord', 'slack']))
                                    <form action="{{ route('data-relations.identities.toggle-bot', $identity) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" title="Mark as bot — hides from this list"
                                                class="btn btn-sm btn-muted">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path stroke-linecap="round" d="M12 7v4M8 15h.01M16 15h.01"/></svg>
                                            Bot
                                        </button>
                                    </form>
                                @endif
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
    <div class="bg-white rounded-lg border border-gray-200">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
                <tr>
                    <th class="px-4 py-2 text-left font-medium">Identity value</th>
                    <th class="px-4 py-2 text-left font-medium">Display name</th>
                    @if($systemType === 'slack')<th class="px-4 py-2 text-left font-medium">Email</th>@endif
                    <th class="px-4 py-2 text-left font-medium">Person in Contact Monitor</th>
                    <th class="px-4 py-2 text-left font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($linked as $identity)
                    @php
                        $gEmail = $identity->type === 'email' ? $identity->value : ($identity->meta_json['email_hint'] ?? null);
                        $gHash  = $gEmail ? md5(strtolower(trim($gEmail))) : null;
                        $sysAvatar = null;
                        if (!empty($identity->meta_json['avatar'])) {
                            if (in_array($identity->type, ['discord_user', 'discord_id'])) {
                                $sysAvatar = 'https://cdn.discordapp.com/avatars/' . $identity->value_normalized . '/' . $identity->meta_json['avatar'] . '.webp?size=40';
                            } elseif ($identity->type === 'slack_user') {
                                $sysAvatar = $identity->meta_json['avatar'];
                            }
                        }
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-600">{{ $identity->value }}</td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                @if($sysAvatar)
                                    <img src="{{ $sysAvatar }}"
                                         class="w-6 h-6 rounded-full object-cover shrink-0">
                                @elseif($gHash)
                                    <img src="https://www.gravatar.com/avatar/{{ $gHash }}?d=identicon&s=40"
                                         class="w-6 h-6 rounded-full object-cover shrink-0">
                                @endif
                                <span class="text-gray-500 text-xs">{{ $identity->meta_json['display_name'] ?? '—' }}</span>
                            </div>
                        </td>
                        @if($systemType === 'slack')<td class="px-4 py-2.5 text-gray-500 text-xs">{{ $identity->meta_json['email_hint'] ?? '—' }}</td>@endif
                        <td class="px-4 py-2.5"><a href="{{ route('people.show', $identity->person) }}" class="text-brand-700 hover:underline font-medium">{{ $identity->person->first_name }} {{ $identity->person->last_name }}</a></td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-1.5">
                                <form action="{{ route('data-relations.identities.toggle-team-member', $identity) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" title="{{ $identity->is_team_member ? 'Our Org — click to remove' : 'Mark as Our Organization' }}"
                                            class="btn btn-sm {{ $identity->is_team_member ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'btn-muted' }}">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M4 21V7l8-4 8 4v14M9 21v-6h6v6"/></svg>
                                        Our Org
                                    </button>
                                </form>
                                <form action="{{ route('data-relations.identities.unlink', $identity) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                        Unlink
                                    </button>
                                </form>
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
<div class="flex items-center gap-3 mb-4">
    <div class="flex-1 flex gap-2 items-center">
        <input type="text" id="ch-search" placeholder="Search…"
               class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-72 focus:outline-none focus:border-brand-400">
        <button type="button" onclick="filterChannels()"
                class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded-lg transition">Search</button>
    </div>
    <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
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
<div class="bg-white rounded-lg border border-gray-200">
    <table id="ch-table" class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
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
                            <a href="{{ route('companies.show', $conv->company) }}" class="text-brand-700 hover:underline font-medium">{{ $conv->company->name }}</a>
                        @else
                            <span class="text-gray-400 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5">@include('data-relations._ac-company', ['action' => route('data-relations.conversations.link', $conv), 'placeholder' => $conv->company ? 'Change…' : 'Search company…'])</td>
                    <td class="px-4 py-2.5 text-right">
                        @if($conv->company_id)
                            <form action="{{ route('data-relations.conversations.unlink', $conv) }}" method="POST" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600">unlink</button>
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
