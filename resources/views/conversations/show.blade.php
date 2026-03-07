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
    $isSlack    = in_array($conversation->channel_type, ['slack', 'discord']);
    $isEmail    = $conversation->channel_type === 'email';
    $isTicket   = $conversation->channel_type === 'ticket';
    $hasMessages = $conversation->messages->isNotEmpty();
    // Resolve Discord <@userId> mentions
    $resolveMentions = fn(?string $text) => $conversation->channel_type === 'discord'
        ? preg_replace_callback('/<@!?(\d+)>/', fn($m) => '@' . ($discordMentionMap[$m[1]] ?? $m[1]), $text ?? '')
        : ($text ?? '');
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
        @if(!$hasMessages)
            <div class="bg-white rounded-lg border border-gray-200 px-4 py-10 text-center text-gray-400 italic text-sm">
                No messages imported yet.
            </div>
        @elseif($isSlack || $isSlack === false && !$isEmail && !$isTicket)
            {{-- ── Slack / Discord: chat layout ── --}}
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    {{ $channelCfg['icon'] }} {{ $channelCfg['label'] }} thread
                </div>
                <div class="divide-y divide-gray-50">
                    @foreach($conversation->messages->whereNull('thread_key') as $msg)
                        @php
                            $isSystem   = $msg->is_system_message;
                            $isTeamMsg  = $msg->direction === 'internal' || $msg->identity?->is_team_member;
                            $msgReplies = $replies[$msg->id] ?? collect();
                        @endphp

                        @if($isSystem)
                            {{-- System message --}}
                            <div class="flex justify-center py-2">
                                <span class="text-xs bg-gray-100 text-gray-500 rounded-full px-3 py-1">
                                    {{ $msg->body_text }}
                                </span>
                            </div>
                        @else
                            <div class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition group">
                                {{-- Avatar circle --}}
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-0.5
                                            {{ $isTeamMsg ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ strtoupper(mb_substr($msg->author_name, 0, 2)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-baseline gap-2 mb-1">
                                        <span class="text-sm font-semibold {{ $isTeamMsg ? 'text-brand-700' : 'text-gray-800' }}">
                                            {{ $msg->author_name }}
                                        </span>
                                        <span class="text-xs text-gray-400">{{ $msg->occurred_at->format('d M Y · H:i') }}</span>
                                        @if($msg->edited_at)
                                            <span class="text-xs text-gray-300 italic">(edited)</span>
                                        @endif
                                        @if($msg->source_url)
                                            <a href="{{ $msg->source_url }}" target="_blank"
                                               class="opacity-0 group-hover:opacity-100 transition ml-auto text-gray-300 hover:text-gray-500">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                    @if($msg->body_html)
                                        <div class="text-sm text-gray-700 leading-relaxed prose prose-sm max-w-none">
                                            {!! $msg->body_html !!}
                                        </div>
                                    @elseif($msg->body_text)
                                        <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ $resolveMentions($msg->body_text) }}</p>
                                    @endif

                                    {{-- Attachments (from message_attachments table or legacy attachments_json) --}}
                                    @php $allAtts = $msg->attachments->isNotEmpty() ? $msg->attachments : collect($msg->attachments_json ?? []); @endphp
                                    @if($allAtts->isNotEmpty())
                                        <div class="flex flex-wrap gap-1.5 mt-2">
                                            @foreach($allAtts as $att)
                                                @php
                                                    $attUrl  = $att->source_url ?? $att['url'] ?? '#';
                                                    $attName = $att->filename ?? $att['name'] ?? 'Attachment';
                                                @endphp
                                                <a href="{{ $attUrl }}" target="_blank"
                                                   class="flex items-center gap-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded px-2 py-1 transition">
                                                    📎 {{ $attName }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Thread replies --}}
                                    @if($msg->thread_count > 0 || $msgReplies->isNotEmpty())
                                        <div class="mt-2 border-l-2 border-gray-200 pl-3 space-y-2">
                                            @if($msgReplies->isEmpty())
                                                <p class="text-xs text-gray-400 italic">{{ $msg->thread_count }} thread replies (not loaded)</p>
                                            @else
                                                @foreach($msgReplies as $reply)
                                                    <div class="flex items-start gap-2">
                                                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                                                                    {{ ($reply->direction === 'internal' || $reply->identity?->is_team_member) ? 'bg-brand-50 text-brand-600' : 'bg-gray-100 text-gray-500' }}">
                                                            {{ strtoupper(mb_substr($reply->author_name, 0, 2)) }}
                                                        </div>
                                                        <div>
                                                            <span class="text-xs font-semibold text-gray-700">{{ $reply->author_name }}</span>
                                                            <span class="text-xs text-gray-400 ml-1">{{ $reply->occurred_at->format('H:i') }}</span>
                                                            <p class="text-xs text-gray-600 leading-relaxed">{{ $resolveMentions($reply->body_text) }}</p>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

        @elseif($isEmail || $isTicket)
            {{-- ── Email / Ticket: bubble layout ── --}}
            @if($isTicket)
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center gap-3 flex-wrap">
                        @php $subject = $conversation->messages->first()?->meta_json['subject'] ?? null; @endphp
                        @if($subject)
                            <span class="font-semibold text-gray-800 text-sm">{{ $subject }}</span>
                        @endif
                        @php $ticketStatus = $conversation->messages->first()?->meta_json['ticket_status'] ?? null; @endphp
                        @if($ticketStatus)
                            <x-badge color="{{ match($ticketStatus) {
                                'open'     => 'green',
                                'pending'  => 'yellow',
                                'closed'   => 'gray',
                                default    => 'blue',
                            } }}">{{ $ticketStatus }}</x-badge>
                        @endif
                        @php $priority = $conversation->messages->first()?->meta_json['priority'] ?? null; @endphp
                        @if($priority)
                            <x-badge color="{{ match($priority) {
                                'high'   => 'red',
                                'medium' => 'yellow',
                                'low'    => 'gray',
                                default  => 'blue',
                            } }}">{{ $priority }}</x-badge>
                        @endif
                    </div>
                </div>
            @endif

            <div class="space-y-3">
                @foreach($conversation->messages as $msg)
                    @php
                        $isInternal = $msg->direction === 'internal' || $msg->identity?->is_team_member;
                        $isSystem   = $msg->is_system_message;
                    @endphp

                    @if($isSystem)
                        {{-- System event (ticket status change, etc.) --}}
                        <div class="flex justify-center">
                            <span class="text-xs bg-amber-50 border border-amber-200 text-amber-700 rounded-full px-4 py-1">
                                {{ $msg->body_text }}
                            </span>
                        </div>
                    @else
                        <div class="flex {{ $isInternal ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[70%] {{ $isInternal ? 'items-end' : 'items-start' }} flex flex-col gap-1">
                                {{-- Author + time --}}
                                <div class="flex items-center gap-2 px-1 {{ $isInternal ? 'flex-row-reverse' : '' }}">
                                    @php
                                        $bubbleEmail = $isEmail ? ($msg->identity?->value ?? null) : null;
                                        $bubbleHash  = $bubbleEmail ? md5(strtolower(trim($bubbleEmail))) : null;
                                    @endphp
                                    @if($bubbleHash)
                                        <img src="https://www.gravatar.com/avatar/{{ $bubbleHash }}?d=identicon&s=48"
                                             class="w-6 h-6 rounded-full object-cover shrink-0"
                                             alt="{{ $msg->author_name }}">
                                    @else
                                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                                                    {{ $isInternal ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-600' }}">
                                            {{ strtoupper(mb_substr($msg->author_name, 0, 2)) }}
                                        </div>
                                    @endif
                                    <div class="{{ $isInternal ? 'text-right' : '' }}">
                                        <div>
                                            <span class="text-xs text-gray-500 font-medium">{{ $msg->author_name }}</span>
                                            @if($isEmail && $msg->identity?->value)
                                                <span class="text-xs text-gray-400 ml-1">&lt;{{ $msg->identity->value }}&gt;</span>
                                            @endif
                                            <span class="text-xs text-gray-300 ml-1">{{ $msg->occurred_at->format('d M · H:i') }}</span>
                                        </div>
                                        @if($isEmail && !empty($msg->meta_json['to']))
                                            <div class="text-xs text-gray-400 mt-0.5">To: {{ $msg->meta_json['to'] }}</div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Bubble --}}
                                @if($msg->source_url)
                                    <a href="{{ $msg->source_url }}" target="_blank" class="group">
                                @endif
                                <div class="rounded-2xl px-4 py-2.5 text-sm leading-relaxed shadow-sm
                                            {{ $isInternal
                                                ? 'bg-brand-600 text-white rounded-tr-none'
                                                : 'bg-white border border-gray-200 text-gray-800 rounded-tl-none' }}">
                                    @if($msg->body_html)
                                        <div class="{{ $isInternal ? 'prose-invert' : '' }} prose prose-sm max-w-none">
                                            {!! $msg->body_html !!}
                                        </div>
                                    @elseif($msg->body_text)
                                        <p class="whitespace-pre-wrap">{{ $msg->body_text }}</p>
                                    @endif
                                    @if($msg->source_url)
                                        <svg class="inline w-3 h-3 opacity-40 ml-1 group-hover:opacity-70 transition"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    @endif
                                </div>
                                @if($msg->source_url)
                                    </a>
                                @endif

                                {{-- Attachments --}}
                                @php $allAtts = $msg->attachments->isNotEmpty() ? $msg->attachments : collect($msg->attachments_json ?? []); @endphp
                                @if($allAtts->isNotEmpty())
                                    <div class="flex flex-wrap gap-1 {{ $isInternal ? 'justify-end' : '' }}">
                                        @foreach($allAtts as $att)
                                            @php
                                                $attUrl  = $att->source_url ?? $att['url'] ?? '#';
                                                $attName = $att->filename ?? $att['name'] ?? 'Attachment';
                                            @endphp
                                            <a href="{{ $attUrl }}" target="_blank"
                                               class="flex items-center gap-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded px-2 py-1 transition">
                                                📎 {{ $attName }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

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
