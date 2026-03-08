{{--
  Shared conversation message rendering — used by both show.blade.php and modal.blade.php.
  Parameters:
    $conversation       — Conversation model (channel_type, system_slug, etc.)
    $messages           — Collection of ConversationMessage models to render
    $replies            — Collection/array keyed by message id → reply messages (optional; for Slack/Discord threads)
    $discordMentionMap  — array of discord user_id → display_name (optional)
--}}
@php
    $replies           = $replies ?? collect();
    $discordMentionMap = $discordMentionMap ?? [];

    $convTypeMap = [
        'email'   => ['label' => 'Email',   'color' => 'bg-sky-100 text-sky-800 border-sky-200',        'icon' => '✉'],
        'slack'   => ['label' => 'Slack',   'color' => 'bg-[#f4ede8] text-[#4A154B] border-[#d9b8d3]', 'icon' => '#'],
        'discord' => ['label' => 'Discord', 'color' => 'bg-indigo-50 text-indigo-800 border-indigo-200', 'icon' => '◈'],
        'ticket'  => ['label' => 'Ticket',  'color' => 'bg-amber-50 text-amber-800 border-amber-200',   'icon' => '🎫'],
    ];
    $channelCfg = $convTypeMap[$conversation->channel_type] ?? [
        'label' => ucfirst($conversation->channel_type),
        'color' => 'bg-gray-100 text-gray-700 border-gray-200',
        'icon'  => '💬',
    ];
    $isSlack  = in_array($conversation->channel_type, ['slack', 'discord']);
    $isEmail  = $conversation->channel_type === 'email';
    $isTicket = $conversation->channel_type === 'ticket';

    $resolveMentions = fn(?string $text) => $conversation->channel_type === 'discord'
        ? preg_replace_callback('/<@!?(\d+)>/', fn($m) => '@' . ($discordMentionMap[$m[1]] ?? $m[1]), $text ?? '')
        : ($text ?? '');
@endphp

@if($messages->isEmpty())
    <div class="bg-white rounded-lg border border-gray-200 px-4 py-10 text-center text-gray-400 italic text-sm">
        No messages imported yet.
    </div>

@elseif(!$isEmail && !$isTicket)
    {{-- ── Slack / Discord / other: chat layout ── --}}
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
            {{ $channelCfg['icon'] }} {{ $channelCfg['label'] }} thread
        </div>
        <div class="divide-y divide-gray-50">
            @foreach($messages->whereNull('thread_key') as $msg)
                @php
                    $isSystem   = $msg->is_system_message;
                    $isTeamMsg  = $msg->direction === 'internal' || $msg->identity?->is_team_member;
                    $msgReplies = $replies[$msg->id] ?? collect();
                @endphp

                @if($isSystem)
                    <div class="flex justify-center py-2">
                        <span class="text-xs bg-gray-100 text-gray-500 rounded-full px-3 py-1">
                            {{ $msg->body_text }}
                        </span>
                    </div>
                @else
                    <div class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition group">
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
                                <x-isolated-html :content="$msg->body_html" class="text-sm text-gray-700 leading-relaxed" />
                            @elseif($msg->body_text)
                                <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ $resolveMentions($msg->body_text) }}</p>
                            @endif

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

@else
    {{-- ── Email / Ticket: bubble layout ── --}}
    @if($isTicket)
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="flex items-center gap-3 flex-wrap">
                @php $subject = $messages->first()?->meta_json['subject'] ?? null; @endphp
                @if($subject)
                    <span class="font-semibold text-gray-800 text-sm">{{ $subject }}</span>
                @endif
                @php $ticketStatus = $messages->first()?->meta_json['ticket_status'] ?? null; @endphp
                @if($ticketStatus)
                    <x-badge color="{{ match($ticketStatus) {
                        'open'    => 'green',
                        'pending' => 'yellow',
                        'closed'  => 'gray',
                        default   => 'blue',
                    } }}">{{ $ticketStatus }}</x-badge>
                @endif
                @php $priority = $messages->first()?->meta_json['priority'] ?? null; @endphp
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
        @foreach($messages as $msg)
            @php
                $isInternal = $msg->direction === 'internal' || $msg->identity?->is_team_member;
                $isSystem   = $msg->is_system_message;
            @endphp

            @if($isSystem)
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
                                <x-isolated-html :content="$msg->body_html" />
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
