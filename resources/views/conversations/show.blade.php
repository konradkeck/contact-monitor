@extends('layouts.app')
@section('title', 'Conversation #' . $conversation->id)

@section('content')

<div class="w-full md:max-w-[80%]">

{{-- Page header --}}
<div class="page-header">
    <div class="flex items-center gap-3">
        {{-- Large icon(s) --}}
        <span class="inline-flex items-center gap-1 shrink-0">
            {!! \App\Integrations\IntegrationRegistry::get($conversation->channel_type)->iconHtml('w-9 h-9', false) !!}
            @if($showSysLogo)
                {!! \App\Integrations\IntegrationRegistry::get($conversation->system_type)->iconHtml('w-7 h-7', false) !!}
            @endif
        </span>
        <div>
            <nav aria-label="Breadcrumb" class="page-breadcrumb">
                @if($backLink ?? null)
                    <a href="{{ $backLink['url'] }}">{{ $backLink['label'] }}</a>
                    <span class="sep">/</span>
                @endif
                <a href="{{ route('conversations.index') }}">Conversations</a>
                <span class="sep">/</span>
                <span class="cur" aria-current="page">{{ $conversation->system_slug }}</span>
            </nav>
            <h1 class="page-title mt-1 leading-tight">
                @if($conversation->company)
                    <a href="{{ route('companies.show', $conversation->company) }}"
                       class="link">{{ $conversation->company->name }}</a>
                    @if($conversation->subject)
                        <span class="text-gray-300 font-normal mx-1">—</span>
                    @endif
                @endif
                @if($conversation->subject)
                    <span class="text-gray-800 font-semibold">{{ $conversation->subject }}</span>
                @elseif(!$conversation->company)
                    <span class="text-gray-400 font-normal italic">No subject</span>
                @endif
            </h1>
        </div>
    </div>

    {{-- Debug button --}}
    <div class="relative shrink-0">
        <button onclick="this.nextElementSibling.classList.toggle('hidden')"
                class="text-xs text-gray-300 hover:text-gray-500 px-2 py-1 rounded transition font-mono"
                title="Debug info">···</button>
        <div class="hidden absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-30 p-4 w-80">
            @foreach($debugInfo as $k => $v)
                <div class="flex gap-2 py-0.5">
                    <span class="text-xs text-gray-400 font-mono shrink-0 w-36">{{ $k }}</span>
                    <span class="text-xs text-gray-700 font-mono break-all">{{ $v }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Meta row --}}
<div class="bg-white rounded-lg border border-gray-200 p-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-5">
    <div>
        <p class="text-xs text-gray-500 mb-0.5">Primary Contact</p>
        <p class="font-medium">
            @if($conversation->primaryPerson)
                <a href="{{ route('people.show', $conversation->primaryPerson) }}" class="link">
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

{{-- Messages --}}
@include('conversations.partials.messages', [
    'messages'          => $conversation->messages,
    'replies'           => $replies,
    'discordMentionMap' => $discordMentionMap,
    'slackMentionMap'   => $slackMentionMap,
])

</div>
@endsection
