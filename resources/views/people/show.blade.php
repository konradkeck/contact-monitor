@extends('layouts.app')
@section('title', $person->full_name)

@section('content')
@php
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
        'discord_user' => [
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

    $initials = strtoupper(mb_substr($person->first_name, 0, 1) . mb_substr($person->last_name ?? '', 0, 1));
@endphp

{{-- ── POPUP: Link company ── --}}
@if($allCompanies->isNotEmpty())
<div id="popup-link-company"
     class="fixed inset-0 z-50 hidden"
     onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="absolute inset-0 bg-black/20"></div>
    <div class="absolute bg-white rounded-xl shadow-xl w-80"
         style="top:50%;left:50%;transform:translate(-50%,-50%)"
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
<div id="popup-add-identity"
     class="fixed inset-0 z-50 hidden"
     onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="absolute inset-0 bg-black/20"></div>
    <div class="absolute bg-white rounded-xl shadow-xl w-80"
         style="top:50%;left:50%;transform:translate(-50%,-50%)"
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
        <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $person->full_name }}</h1>
    </div>
    <div class="flex items-center gap-2">
        <form action="{{ route('filtering.contacts.add') }}" method="POST">
            @csrf
            <input type="hidden" name="person_id" value="{{ $person->id }}">
            <button type="submit"
                    class="px-3 py-1.5 border border-gray-300 text-sm rounded hover:bg-gray-50 text-gray-400 hover:text-red-500 transition"
                    title="Add to filter contacts">🚫 Filter</button>
        </form>
        <a href="{{ route('people.edit', $person) }}" class="px-3 py-1.5 border border-gray-300 text-sm rounded hover:bg-gray-50">Edit</a>
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
                        🏢 Our Organization
                    </span>
                @endif
                <x-person-avatar :person="$person" size="20"
                     class="border-2 {{ $person->is_our_org ? 'border-indigo-300/40' : 'border-white/20' }} bg-gray-600 mb-3" />
                <p class="font-bold text-white text-base leading-snug">{{ $person->full_name }}</p>
                <p class="text-xs {{ $person->is_our_org ? 'text-indigo-300' : 'text-gray-400' }} mt-1">Since {{ $person->created_at->format('d M Y') }}</p>
            </div>

            {{-- Companies lifted card --}}
            <div class="-mt-4 mx-4 bg-white rounded-lg border border-gray-200 shadow-sm">
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
                                <form action="{{ route('people.companies.unlink', [$person, $company]) }}" method="POST" class="shrink-0 mt-0.5">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-400 hover:text-red-600">✕</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Add company button --}}
            @if($allCompanies->isNotEmpty())
                <div class="px-4 py-3 text-center">
                    <button onclick="document.getElementById('popup-link-company').classList.remove('hidden')"
                            class="text-xs font-medium text-brand-600 hover:text-brand-700 border border-brand-200
                                   hover:border-brand-400 px-4 py-1.5 rounded-full transition">
                        + Add company
                    </button>
                </div>
            @else
                <div class="pb-3"></div>
            @endif

        </div>

        {{-- Identities --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 text-sm">Identities</h3>
                <button onclick="document.getElementById('popup-add-identity').classList.remove('hidden')"
                        class="text-xs font-medium text-brand-600 hover:text-brand-700 border border-brand-200
                               hover:border-brand-400 px-3 py-1 rounded-full transition">
                    + Add
                </button>
            </div>
            @if($person->identities->isEmpty())
                <p class="px-4 py-4 text-sm text-gray-400 italic">No identities yet.</p>
            @else
                <ul class="divide-y divide-gray-50">
                    @foreach($person->identities as $identity)
                        @php
                            $cfg  = $identityIcons[$identity->type] ?? null;
                            $href = ($cfg && is_callable($cfg['href'] ?? null)) ? ($cfg['href'])($identity->value) : null;
                        @endphp
                        <li class="px-4 py-2 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                @if($cfg)
                                    @if($href)
                                        <a href="{{ $href }}" target="_blank" rel="noopener"
                                           title="{{ $cfg['title'] }}"
                                           class="inline-flex items-center justify-center w-5 h-5 rounded shrink-0 {{ $cfg['cls'] }}"
                                           @if($cfg['style'] ?? null) style="{{ $cfg['style'] }}" @endif>
                                            <svg class="w-3 h-3" @if($cfg['stroke']) fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" @else fill="currentColor" @endif viewBox="0 0 24 24">
                                                <path d="{{ $cfg['d'] }}"/>
                                            </svg>
                                        </a>
                                    @else
                                        <span title="{{ $cfg['title'] }}"
                                              class="inline-flex items-center justify-center w-5 h-5 rounded shrink-0 {{ $cfg['cls'] }}"
                                              @if($cfg['style'] ?? null) style="{{ $cfg['style'] }}" @endif>
                                            <svg class="w-3 h-3" @if($cfg['stroke']) fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" @else fill="currentColor" @endif viewBox="0 0 24 24">
                                                <path d="{{ $cfg['d'] }}"/>
                                            </svg>
                                        </span>
                                    @endif
                                @else
                                    <span class="text-xs text-gray-400 shrink-0 w-5 text-center">?</span>
                                @endif
                                @php
                                    $showRawValue = !in_array($identity->type, ['discord_user', 'discord_id', 'slack_user'])
                                        || empty($identity->meta_json['display_name']);
                                    $avatarSrcId = null;
                                    if (!empty($identity->meta_json['avatar'])) {
                                        if (in_array($identity->type, ['discord_user', 'discord_id'])) {
                                            $avatarSrcId = 'https://cdn.discordapp.com/avatars/' . $identity->value_normalized . '/' . $identity->meta_json['avatar'] . '.webp?size=32';
                                        } elseif ($identity->type === 'slack_user') {
                                            $avatarSrcId = $identity->meta_json['avatar'];
                                        }
                                    }
                                @endphp
                                @if($avatarSrcId)
                                    <img src="{{ $avatarSrcId }}"
                                         class="w-5 h-5 rounded-full shrink-0 ring-1 ring-gray-200"
                                         alt="avatar">
                                @endif
                                @if(!empty($identity->meta_json['display_name']))
                                    <span class="text-xs text-gray-700 truncate font-medium">{{ $identity->meta_json['display_name'] }}</span>
                                @endif
                                @if($showRawValue)
                                    @if($href)
                                        <a href="{{ $href }}" target="_blank" rel="noopener"
                                           class="font-mono text-xs text-brand-700 hover:underline truncate">{{ $identity->value }}</a>
                                    @else
                                        <span class="font-mono text-xs text-gray-600 truncate">{{ $identity->value }}</span>
                                    @endif
                                @endif
                                @if($identity->system_slug !== 'default')
                                    <x-badge color="gray">{{ $identity->system_slug }}</x-badge>
                                @endif
                            </div>
                            <form action="{{ route('people.identities.destroy', [$person, $identity]) }}" method="POST" class="shrink-0">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600">✕</button>
                            </form>
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
    @php
        $allTypes   = ['payment','renewal','cancellation','ticket','conversation','note','status_change','campaign_run','followup'];
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
    <div class="col-span-2">
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
                <div id="timeline-container"
                     class="grid grid-cols-[1fr_2rem_1fr] relative z-10"
                     data-url="{{ route('people.timeline', $person) }}">
                    @include('people.partials.timeline-items', [
                        'activities' => $timelinePage->items(),
                        'nextCursor' => $timelinePage->nextCursor()?->encode(),
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

    let fetching    = false;
    let reqId       = 0;
    let activeTypes = [];
    let dateFrom    = '';
    let dateTo      = '';
    let fp          = null;

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

    function localDateStr(d) {
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    }

    function sentinel() { return document.getElementById('timeline-sentinel'); }

    function buildUrl(cursor) {
        const p = new URLSearchParams();
        if (cursor)   p.set('cursor', cursor);
        activeTypes.forEach(t => p.append('types[]', t));
        if (dateFrom) p.set('from', dateFrom);
        if (dateTo)   p.set('to',   dateTo);
        return baseUrl + '?' + p;
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
