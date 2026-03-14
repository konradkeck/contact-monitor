@extends('layouts.app')
@section('title', 'Our Organization — Data Relations')

@section('content')

@php $activeTab = request('tab', 'members'); @endphp

<div class="page-header">
    <div>
        <span class="page-title">Our Organization</span>
        <p class="text-xs text-gray-400 mt-0.5">Define which people and domains belong to your own team. This separates internal activity from customer interactions so timelines and statistics only reflect external communications.</p>
    </div>
</div>

@if(session('success'))
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
        {{ session('success') }}
    </div>
@endif

{{-- Tabs --}}
<div class="flex gap-0 border-b border-gray-200 mb-6">
    @foreach([
        'members'    => ['label' => 'Members',         'count' => $teamPeople->count()],
        'identities' => ['label' => 'Team Identities', 'count' => $unlinkedTeamIdentities->count()],
        'domains'    => ['label' => 'Email Domains',   'count' => count($teamDomains)],
    ] as $tab => $cfg)
        <a href="{{ request()->fullUrlWithQuery(['tab' => $tab]) }}"
           class="px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition
                  {{ $activeTab === $tab
                     ? 'border-brand-600 text-brand-700'
                     : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            {{ $cfg['label'] }}
            @if($cfg['count'] > 0)
                <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-xs
                             {{ $activeTab === $tab ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $cfg['count'] }}
                </span>
            @endif
        </a>
    @endforeach
</div>

{{-- ── TAB: MEMBERS ── --}}
@if($activeTab === 'members')
    @if($teamPeople->isNotEmpty())
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            @foreach($teamPeople as $person)
                @php
                    $emailId = $person->identities->firstWhere('type', 'email');
                    $hash    = $emailId ? md5(strtolower(trim($emailId->value))) : md5('');
                @endphp
                <div class="flex items-center gap-3 px-4 py-3">
                    <img src="https://www.gravatar.com/avatar/{{ $hash }}?d=identicon&s=80"
                         class="w-9 h-9 rounded-full object-cover border border-gray-100 shrink-0">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm text-gray-800">{{ $person->full_name }}</p>
                        <div class="flex flex-wrap gap-1.5 mt-0.5">
                            @foreach($person->identities->where('is_team_member', true) as $id)
                                <span class="text-xs text-gray-500 font-mono bg-gray-100 px-1.5 py-0.5 rounded">
                                    {{ $id->type }}: {{ $id->value }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <a href="{{ route('people.show', $person) }}"
                       class="text-xs text-brand-600 hover:underline shrink-0">View →</a>
                    <form action="{{ route('our-company.remove-member', $person) }}" method="POST"
                          onsubmit="return confirm('Remove {{ $person->full_name }} from Our Organization?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-400 hover:text-red-600 font-medium">✕ Remove</button>
                    </form>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 px-6 py-10 text-center">
            <p class="text-gray-400 text-sm italic">No members in Our Organization yet.</p>
            <p class="text-gray-300 text-xs mt-1">Mark people from the People list or Person Details page.</p>
        </div>
    @endif
@endif

{{-- ── TAB: IDENTITIES ── --}}
@if($activeTab === 'identities')
    @if($unlinkedTeamIdentities->isNotEmpty())
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            @foreach($unlinkedTeamIdentities as $identity)
                <div class="flex items-center gap-3 px-4 py-2.5 text-sm">
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded shrink-0">{{ $identity->type }}</span>
                    <span class="font-mono text-gray-700 flex-1 truncate">{{ $identity->value }}</span>
                    <span class="text-xs text-gray-400 shrink-0 font-mono">{{ $identity->system_slug }}</span>
                    <form action="{{ route('data-relations.identities.toggle-team-member', $identity) }}" method="POST">
                        @csrf
                        <button class="text-xs text-red-400 hover:text-red-600 font-medium">✕ unmark</button>
                    </form>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 px-6 py-10 text-center">
            <p class="text-gray-400 text-sm italic">No unlinked team identities.</p>
        </div>
    @endif
@endif

{{-- ── TAB: DOMAINS ── --}}
@if($activeTab === 'domains')
<div class="max-w-xl">
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Team Email Domains</h2>
            <p class="text-xs text-gray-400 mt-0.5">
                Identities with these email domains are auto-marked as team members on Auto-Resolve.
            </p>
        </div>

        @if(!empty($teamDomains))
            <ul class="divide-y divide-gray-50">
                @foreach($teamDomains as $domain)
                    <li class="flex items-center justify-between px-5 py-2.5">
                        <span class="font-mono text-sm text-gray-700">{{ $domain }}</span>
                        <form action="{{ route('our-company.remove-domain') }}" method="POST">
                            @csrf
                            <input type="hidden" name="domain" value="{{ $domain }}">
                            <button class="text-xs text-red-400 hover:text-red-600 font-bold">✕ remove</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="px-5 py-4 text-sm text-gray-400 italic">No team domains configured.</p>
        @endif

        <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
            <form action="{{ route('our-company.save-domains') }}" method="POST" class="space-y-2">
                @csrf
                <textarea name="domains" rows="3" placeholder="modulesgarden.com&#10;mg.com"
                          class="w-full text-sm font-mono border border-gray-200 rounded-lg px-3 py-2
                                 placeholder-gray-300 text-gray-700 resize-none focus:outline-none
                                 focus:ring-2 focus:ring-brand-300">{{ implode("\n", $teamDomains) }}</textarea>
                <button class="w-full py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg transition">
                    Save & Auto-mark
                </button>
            </form>
        </div>
    </div>
</div>
@endif

@endsection
