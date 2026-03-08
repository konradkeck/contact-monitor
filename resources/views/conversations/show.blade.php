@extends('layouts.app')
@section('title', 'Conversation #' . $conversation->id)

@section('content')
@php
    $convTypeMap = [
        'email'   => ['label' => 'Email',   'color' => 'bg-sky-100 text-sky-800 border-sky-200',        'icon' => '✉'],
        'slack'   => ['label' => 'Slack',   'color' => 'bg-[#f4ede8] text-[#4A154B] border-[#d9b8d3]', 'icon' => '#'],
        'discord' => ['label' => 'Discord', 'color' => 'bg-indigo-50 text-indigo-800 border-indigo-200', 'icon' => '◈'],
        'ticket'  => ['label' => 'Ticket',  'color' => 'bg-amber-50 text-amber-800 border-amber-200',   'icon' => '🎫'],
    ];
    $channelCfg = $convTypeMap[$conversation->channel_type] ?? ['label' => ucfirst($conversation->channel_type), 'color' => 'bg-gray-100 text-gray-700 border-gray-200', 'icon' => '💬'];
@endphp

<div class="flex items-start justify-between mb-5">
    <div>
        <div class="flex items-center gap-2 text-sm text-gray-500">
            @if($backLink ?? null)
                <a href="{{ $backLink['url'] }}" class="hover:text-gray-700">← {{ $backLink['label'] }}</a>
                <span class="text-gray-300">/</span>
            @endif
            <a href="{{ route('conversations.index') }}" class="hover:text-gray-700">{{ ($backLink ?? null) ? 'Conversations' : '← Conversations' }}</a>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mt-1 flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 text-sm px-2.5 py-1 rounded border font-medium {{ $channelCfg['color'] }}">
                {{ $channelCfg['icon'] }} {{ $channelCfg['label'] }}
            </span>
            @if($conversation->company)
                <a href="{{ route('companies.show', $conversation->company) }}" class="text-brand-700 hover:underline">
                    {{ $conversation->company->name }}
                </a>
            @else
                <span class="text-gray-400 text-base font-normal italic">Company not yet resolved</span>
            @endif
            @if($conversation->subject)
                <span class="text-gray-600 text-base font-normal">— {{ $conversation->subject }}</span>
            @endif
        </h1>
        <div class="flex items-center gap-2 mt-1">
            @if($conversation->system_slug !== 'default')
                <x-badge color="gray">{{ $conversation->system_slug }}</x-badge>
            @endif
            @if($conversation->external_thread_id)
                <span class="text-xs text-gray-400 font-mono">{{ $conversation->external_thread_id }}</span>
            @endif
        </div>
    </div>
</div>

<div class="flex gap-5 items-start">

    {{-- ── MAIN: Messages Viewer ── --}}
    <div class="flex-1 min-w-0 space-y-5">

        {{-- Meta row --}}
        <div class="bg-white rounded-lg border border-gray-200 p-4 grid grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-xs text-gray-500 mb-0.5">Primary Contact</p>
                <p class="font-medium">
                    @if($conversation->primaryPerson)
                        <a href="{{ route('people.show', $conversation->primaryPerson) }}" class="text-brand-700 hover:underline">
                            {{ $conversation->primaryPerson->full_name }}
                        </a>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-0.5">Messages</p>
                <p class="font-medium">{{ $conversation->message_count }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-0.5">Started</p>
                <p class="font-medium">{{ $conversation->started_at?->format('Y-m-d') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-0.5">Last message</p>
                <p class="font-medium">{{ $conversation->last_message_at?->format('Y-m-d') ?? '—' }}</p>
            </div>
        </div>

        {{-- ══ Message viewer ══ --}}
        @include('conversations.partials.messages', [
            'messages'          => $conversation->messages,
            'replies'           => $replies,
            'discordMentionMap' => $discordMentionMap,
        ])

    </div>

    {{-- ── SIDEBAR ── --}}
    <div class="w-64 flex-shrink-0 space-y-4">

        {{-- Participants --}}
        <div class="bg-white rounded-lg border border-gray-200">
            <div class="px-4 py-3 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800 text-sm">Participants</h3>
            </div>
            @if($conversation->participants->isEmpty())
                <p class="px-4 py-3 text-sm text-gray-400 italic">None.</p>
            @else
                <ul class="divide-y divide-gray-50">
                    @foreach($conversation->participants as $participant)
                        <li class="px-4 py-2 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                @if($participant->person)
                                    <a href="{{ route('people.show', $participant->person) }}" class="text-xs font-medium text-brand-700 hover:underline block truncate">
                                        {{ $participant->person->full_name }}
                                    </a>
                                @else
                                    <span class="text-xs text-gray-600 truncate block">{{ $participant->display_name ?? 'Unknown' }}</span>
                                @endif
                                <span class="text-xs text-gray-400">{{ $participant->identity->type }}: {{ $participant->identity->value }}</span>
                                @if($participant->role)
                                    <x-badge color="blue">{{ $participant->role }}</x-badge>
                                @endif
                            </div>
                            <form action="{{ route('conversations.participants.destroy', [$conversation, $participant]) }}" method="POST" class="shrink-0">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600">✕</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if($allIdentities->isNotEmpty())
                <div class="px-4 py-3 border-t border-gray-100 bg-gray-50 rounded-b-lg">
                    <form action="{{ route('conversations.participants.store', $conversation) }}" method="POST" class="flex flex-col gap-1.5">
                        @csrf
                        <select name="identity_id" class="text-xs border border-gray-300 rounded px-2 py-1.5 w-full">
                            @foreach($allIdentities as $identity)
                                <option value="{{ $identity->id }}">
                                    {{ $identity->person?->full_name ?? 'Unassigned' }}
                                    — {{ $identity->type }}: {{ $identity->value }}
                                </option>
                            @endforeach
                        </select>
                        <select name="role" class="text-xs border border-gray-300 rounded px-2 py-1.5 w-full">
                            <option value="">Role…</option>
                            @foreach(['sender','recipient','cc','bcc','observer'] as $r)
                                <option value="{{ $r }}">{{ $r }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="w-full px-3 py-1.5 bg-brand-600 text-white text-xs rounded hover:bg-brand-700 transition">Add</button>
                    </form>
                </div>
            @endif
        </div>

        {{-- Notes --}}
        <x-notes-section :notes="$notes" linkable-type="conversation" :linkable-id="$conversation->id" />

    </div>

</div>
@endsection
