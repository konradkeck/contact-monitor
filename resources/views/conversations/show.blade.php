@extends('layouts.app')
@section('title', 'Conversation #' . $conversation->id)

@section('content')
@php
    $sysIntCls   = get_class(\App\Integrations\IntegrationRegistry::get($conversation->system_type ?? ''));
    $chnIntCls   = get_class(\App\Integrations\IntegrationRegistry::get($conversation->channel_type));
    $showSysLogo = $conversation->system_type && $sysIntCls !== $chnIntCls;

    $debugInfo = array_filter([
        'conversation_id'    => $conversation->id,
        'channel_type'       => $conversation->channel_type,
        'system_type'        => $conversation->system_type,
        'system_slug'        => $conversation->system_slug,
        'external_thread_id' => $conversation->external_thread_id,
        'company_id'         => $conversation->company_id,
        'message_count'      => $conversation->message_count,
        'started_at'         => $conversation->started_at?->toIso8601String(),
        'last_message_at'    => $conversation->last_message_at?->toIso8601String(),
    ], fn($v) => $v !== null && $v !== '');
@endphp

<div style="max-width:80%">

{{-- Breadcrumb --}}
<div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    @if($backLink ?? null)
        <a href="{{ $backLink['url'] }}" class="hover:text-gray-700">← {{ $backLink['label'] }}</a>
        <span class="text-gray-300">/</span>
    @endif
    <a href="{{ route('conversations.index') }}" class="hover:text-gray-700">{{ ($backLink ?? null) ? 'Conversations' : '← Conversations' }}</a>
</div>

{{-- Header --}}
<div class="flex items-start justify-between mb-5">
    <div class="flex items-center gap-3">
        {{-- Large icon(s) --}}
        <span class="inline-flex items-center gap-1">
            {!! \App\Integrations\IntegrationRegistry::get($conversation->channel_type)->iconHtml('w-9 h-9', false) !!}
            @if($showSysLogo)
                {!! \App\Integrations\IntegrationRegistry::get($conversation->system_type)->iconHtml('w-7 h-7', false) !!}
            @endif
        </span>
        <div>
            <div class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ $conversation->system_slug }}</div>
            <h1 class="text-xl font-bold text-gray-900 leading-tight mt-0.5">
                @if($conversation->company)
                    <a href="{{ route('companies.show', $conversation->company) }}"
                       class="text-brand-700 hover:underline">{{ $conversation->company->name }}</a>
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
<div class="bg-white rounded-lg border border-gray-200 p-4 grid grid-cols-4 gap-4 text-sm mb-5">
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

{{-- Messages --}}
@include('conversations.partials.messages', [
    'messages'          => $conversation->messages,
    'replies'           => $replies,
    'discordMentionMap' => $discordMentionMap,
])

</div>
@endsection
