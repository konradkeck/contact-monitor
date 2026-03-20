{{--
  Shared conversation message rendering — used by both show.blade.php and modal.blade.php.
  Parameters:
    $conversation       — Conversation model (channel_type, system_slug, etc.)
    $messages           — Collection of ConversationMessage models to render
    $replies            — Collection/array keyed by message id → reply messages (optional; for Slack/Discord threads)
    $discordMentionMap  — array of discord user_id → display_name (optional)
--}}
@once
<script>
function markPersonOurOrg(personId) {
    fetch('/people/' + personId + '/mark-our-org', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    }).then(() => window.location.reload());
}
function unmarkPersonOurOrg(personId) {
    fetch('/people/' + personId + '/unmark-our-org', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    }).then(() => window.location.reload());
}
</script>
@endonce

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
                @if($msg->is_system_message)
                    <div class="flex justify-center py-2">
                        <span class="text-xs bg-gray-100 text-gray-500 rounded-full px-3 py-1">
                            {{ html_entity_decode($msg->body_text, ENT_QUOTES | ENT_HTML5, 'UTF-8') }}
                        </span>
                    </div>
                @else
                    <div class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition group">
                        @if($msg->chatAvatarUrl())
                            <img src="{{ $msg->chatAvatarUrl() }}" alt="{{ $msg->author_name }}"
                                 class="w-8 h-8 rounded-full object-cover shrink-0 mt-0.5">
                        @else
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-0.5
                                        {{ $msg->isTeamMessage() ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ strtoupper(mb_substr($msg->author_name, 0, 2)) }}
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="flex items-baseline gap-2 mb-1">
                                <span class="text-sm font-semibold {{ $msg->isTeamMessage() ? 'text-brand-700' : 'text-gray-800' }}">
                                    {{ $msg->author_name }}
                                </span>
                                @if($msg->identity?->person)
                                    @if(!$msg->identity->person->is_our_org)
                                        <span class="relative group/ourorg opacity-0 group-hover:opacity-100 transition shrink-0">
                                            <button type="button"
                                                    onclick="markPersonOurOrg({{ $msg->identity->person->id }})"
                                                    class="text-xs text-brand-500 hover:text-brand-700 underline decoration-dotted underline-offset-2 cursor-pointer">
                                                Set as Our Org
                                            </button>
                                            <span class="pointer-events-none absolute bottom-full left-0 mb-1.5 hidden group-hover/ourorg:block bg-gray-800 text-white text-xs rounded-lg px-2.5 py-1.5 w-52 z-20 leading-snug shadow-lg">
                                                Mark as part of your organization. Their messages will appear on the internal side.
                                            </span>
                                        </span>
                                    @else
                                        <span class="relative group/ourorg shrink-0">
                                            <button type="button"
                                                    onclick="unmarkPersonOurOrg({{ $msg->identity->person->id }})"
                                                    class="text-xs bg-brand-50 text-brand-600 border border-brand-200 rounded px-1.5 py-0.5 leading-none hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition">
                                                Our Org
                                            </button>
                                            <span class="pointer-events-none absolute bottom-full left-0 mb-1.5 hidden group-hover/ourorg:block bg-gray-800 text-white text-xs rounded-lg px-2.5 py-1.5 w-44 z-20 leading-snug shadow-lg">
                                                Click to unmark as Our Organization.
                                            </span>
                                        </span>
                                    @endif
                                @endif
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

                            <x-message-body
                                :bodyHtml="$msg->body_html"
                                :bodyText="$conversation->resolveMentions($msg->body_text ?? '', $discordMentionMap, $slackMentionMap ?? [])"
                                :usesMarkdown="$usesMarkdown ?? false"
                                class="text-sm text-gray-700 leading-relaxed" />

                            @if($msg->allAttachments()->isNotEmpty())
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    @foreach($msg->allAttachments() as $att)
                                        <a href="{{ $att->source_url ?? $att['url'] ?? '#' }}" target="_blank"
                                           class="flex items-center gap-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded px-2 py-1 transition">
                                            📎 {{ $att->filename ?? $att['name'] ?? 'Attachment' }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            @if($msg->thread_count > 0 || ($replies[$msg->id] ?? collect())->isNotEmpty())
                                <div class="mt-2 border-l-2 border-gray-200 pl-3 space-y-2">
                                    @if(($replies[$msg->id] ?? collect())->isEmpty())
                                        <p class="text-xs text-gray-400 italic">{{ $msg->thread_count }} thread replies (not loaded)</p>
                                    @else
                                        @foreach($replies[$msg->id] as $reply)
                                            <div class="flex items-start gap-2">
                                                @if($reply->chatAvatarUrl())
                                                    <img src="{{ $reply->chatAvatarUrl() }}" alt="{{ $reply->author_name }}"
                                                         class="w-6 h-6 rounded-full object-cover shrink-0">
                                                @else
                                                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                                                                {{ $reply->isTeamMessage() ? 'bg-brand-50 text-brand-600' : 'bg-gray-100 text-gray-500' }}">
                                                        {{ strtoupper(mb_substr($reply->author_name, 0, 2)) }}
                                                    </div>
                                                @endif
                                                <div>
                                                    <span class="text-xs font-semibold text-gray-700">{{ $reply->author_name }}</span>
                                                    <span class="text-xs text-gray-400 ml-1">{{ $reply->occurred_at->format('H:i') }}</span>
                                                    <x-message-body
                                                        :bodyHtml="$reply->body_html"
                                                        :bodyText="$conversation->resolveMentions($reply->body_text ?? '', $discordMentionMap, $slackMentionMap ?? [])"
                                                        :usesMarkdown="$usesMarkdown ?? false"
                                                        class="text-xs text-gray-600 leading-relaxed" />
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
        @foreach([$conversation->ticketDisplayData($messages)] as $td)
        @if($td->hasTicketInfo)
        <div class="bg-white rounded-lg border border-gray-200 px-4 py-2.5 mb-3 flex items-center gap-2 flex-wrap">
            @if($td->ticketHeading !== '')
                <span class="text-sm font-semibold text-gray-800">{{ $td->ticketHeading }}</span>
            @endif
            @if($td->ticketStatus)
                <x-badge color="{{ $td->statusColor }}">{{ $td->ticketStatus }}</x-badge>
            @endif
            @if($td->ticketDept)
                <span class="text-xs text-gray-500">{{ $td->ticketDept }}</span>
            @endif
            @if($td->priority)
                <x-badge color="{{ $td->priorityColor }}">{{ $td->priority }}</x-badge>
            @endif
        </div>
        @endif
        @endforeach
    @endif

    <div class="space-y-3">
        @foreach($messages as $msg)
            @if($msg->is_system_message)
                <div class="flex justify-center">
                    <span class="text-xs bg-amber-50 border border-amber-200 text-amber-700 rounded-full px-4 py-1">
                        {{ html_entity_decode($msg->body_text, ENT_QUOTES | ENT_HTML5, 'UTF-8') }}
                    </span>
                </div>
            @else
                <div class="flex {{ $msg->isTeamMessage() ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[70%] {{ $msg->isTeamMessage() ? 'items-end' : 'items-start' }} flex flex-col gap-1">

                        {{-- Author + time --}}
                        <div class="flex items-center gap-2 px-1 {{ $msg->isTeamMessage() ? 'flex-row-reverse' : '' }}">
                            @if($isEmail && $msg->gravatarHash())
                                <img src="https://www.gravatar.com/avatar/{{ $msg->gravatarHash() }}?d=identicon&s=48"
                                     class="w-6 h-6 rounded-full object-cover shrink-0"
                                     alt="{{ $msg->author_name }}">
                            @else
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                                            {{ $msg->isTeamMessage() ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ strtoupper(mb_substr($msg->author_name, 0, 2)) }}
                                </div>
                            @endif
                            <div class="{{ $msg->isTeamMessage() ? 'text-right' : '' }}">
                                <div class="flex items-center gap-1.5 {{ $msg->isTeamMessage() ? 'flex-row-reverse' : '' }}">
                                    <span class="text-xs text-gray-500 font-medium">{{ $msg->author_name }}</span>
                                    @if($isEmail && $msg->identity?->value)
                                        <span class="text-xs text-gray-400">&lt;{{ $msg->identity->value }}&gt;</span>
                                    @endif
                                    <span class="text-xs text-gray-300">{{ $msg->occurred_at->format('d M · H:i') }}</span>
                                    @if($msg->identity?->person)
                                        @if(!$msg->identity->person->is_our_org)
                                            <span class="relative group/ourorg shrink-0">
                                                <button type="button"
                                                        onclick="markPersonOurOrg({{ $msg->identity->person->id }})"
                                                        class="text-xs text-brand-500 hover:text-brand-700 underline decoration-dotted underline-offset-2 cursor-pointer">
                                                    Set as Our Org
                                                </button>
                                                <span class="pointer-events-none absolute bottom-full left-0 mb-1.5 hidden group-hover/ourorg:block bg-gray-800 text-white text-xs rounded-lg px-2.5 py-1.5 w-52 z-20 leading-snug shadow-lg">
                                                    Mark as part of your organization. Their messages will appear on the internal side.
                                                </span>
                                            </span>
                                        @else
                                            <span class="relative group/ourorg shrink-0">
                                                <button type="button"
                                                        onclick="unmarkPersonOurOrg({{ $msg->identity->person->id }})"
                                                        class="text-xs bg-brand-50 text-brand-600 border border-brand-200 rounded px-1.5 py-0.5 leading-none hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition">
                                                    Our Org
                                                </button>
                                                <span class="pointer-events-none absolute bottom-full left-0 mb-1.5 hidden group-hover/ourorg:block bg-gray-800 text-white text-xs rounded-lg px-2.5 py-1.5 w-44 z-20 leading-snug shadow-lg">
                                                    Click to unmark as Our Organization.
                                                </span>
                                            </span>
                                        @endif
                                    @endif
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
                                    {{ $msg->isTeamMessage()
                                        ? 'bg-gray-100 text-gray-800 rounded-tr-none'
                                        : 'bg-white border border-gray-200 text-gray-800 rounded-tl-none' }}">
                            <x-message-body
                                :bodyHtml="$msg->body_html"
                                :bodyText="$msg->body_text"
                                :usesMarkdown="$usesMarkdown ?? false" />
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
                        @if($msg->allAttachments()->isNotEmpty())
                            <div class="flex flex-wrap gap-1 {{ $msg->isTeamMessage() ? 'justify-end' : '' }}">
                                @foreach($msg->allAttachments() as $att)
                                    <a href="{{ $att->source_url ?? $att['url'] ?? '#' }}" target="_blank"
                                       class="flex items-center gap-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded px-2 py-1 transition">
                                        📎 {{ $att->filename ?? $att['name'] ?? 'Attachment' }}
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
