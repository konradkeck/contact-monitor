@extends('layouts.app')
@section('title', "{$systemSlug} — Mapping")

@php
    $hasTabs   = $conversationStats !== null; // Discord / Slack
    $activeTab = request('tab', 'people');
    $activeView = request('view', 'unlinked'); // people sub-view
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

{{-- ─── TOP-LEVEL TABS (Discord / Slack only) ─── --}}
@if($hasTabs)
<div class="flex gap-0 border-b border-gray-200 mb-6">
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
</div>
@endif

{{-- ─── ACCOUNT-BASED (WHMCS, MetricsCube) ─── --}}
@if($isAccountSystem)

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
                    @php $meta = $account->meta_json ?? []; @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-500">{{ $account->external_id }}</td>
                        <td class="px-4 py-2.5 text-gray-800">{{ $meta['company_name'] ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $meta['email'] ?? '—' }}</td>
                        @if($systemType === 'whmcs')
                            <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $meta['phone'] ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $meta['country'] ?? '—' }}</td>
                        @endif
                        <td class="px-4 py-2.5">@include('data-relations._ac-company', ['action' => route('data-relations.accounts.link', $account)])</td>
                    </tr>
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
                    <th class="px-4 py-2 text-left font-medium">Company in SalesOS</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($linked as $account)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-500">{{ $account->external_id }}</td>
                        @if($systemType === 'whmcs')<td class="px-4 py-2.5 text-gray-600 text-xs">{{ $account->meta_json['company_name'] ?? '—' }}</td>@endif
                        <td class="px-4 py-2.5"><a href="{{ route('companies.show', $account->company) }}" class="text-brand-700 hover:underline font-medium">{{ $account->company->name }}</a></td>
                        <td class="px-4 py-2.5 text-right">
                            <form action="{{ route('data-relations.accounts.unlink', $account) }}" method="POST" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600">unlink</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($linked->hasPages())<div class="px-4 py-3 border-t border-gray-100">{{ $linked->links() }}</div>@endif
    </div>
    @else
        <div class="bg-gray-50 border border-gray-200 rounded-lg px-5 py-4 text-sm text-gray-500">No linked accounts.</div>
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
                    <th class="px-4 py-2 text-center font-medium">Team</th>
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
                        <td class="px-4 py-2.5">@include('data-relations._ac-person', ['action' => route('data-relations.identities.link', $identity)])</td>
                        <td class="px-4 py-2.5 text-center">
                            <form action="{{ route('data-relations.identities.toggle-team-member', $identity) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" title="{{ $identity->is_team_member ? 'Our team member — click to remove' : 'Click to mark as team member' }}"
                                        class="text-lg leading-none transition {{ $identity->is_team_member ? 'opacity-100' : 'opacity-20 hover:opacity-60' }}">🏢</button>
                            </form>
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
                    <th class="px-4 py-2 text-left font-medium">Person in SalesOS</th>
                    <th class="px-4 py-2 text-center font-medium">Team</th>
                    <th class="px-4 py-2"></th>
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
                        <td class="px-4 py-2.5 text-center">
                            <form action="{{ route('data-relations.identities.toggle-team-member', $identity) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" title="{{ $identity->is_team_member ? 'Our team member — click to remove' : 'Click to mark as team member' }}"
                                        class="text-lg leading-none transition {{ $identity->is_team_member ? 'opacity-100' : 'opacity-20 hover:opacity-60' }}">🏢</button>
                            </form>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <form action="{{ route('data-relations.identities.unlink', $identity) }}" method="POST" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600">unlink</button>
                            </form>
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
<div class="bg-white rounded-lg border border-gray-200">
    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
        <span class="text-sm font-semibold text-gray-700">
            Unlinked <span class="text-amber-600 font-bold">{{ $conversationStats['unlinked'] }}</span>
            <span class="text-gray-400 font-normal ml-2">/ {{ $conversationStats['total'] }} total</span>
        </span>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
            <tr>
                <th class="px-4 py-2 text-left font-medium">Channel</th>
                <th class="px-4 py-2 text-left font-medium">Company in SalesOS</th>
                <th class="px-4 py-2 text-left font-medium w-72">Link / Change</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($conversations as $conv)
                <tr class="hover:bg-gray-50">
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
