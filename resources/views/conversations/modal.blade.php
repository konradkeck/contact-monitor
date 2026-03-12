{{--
  Conversation quick-view modal partial.
  Returned by GET /conversations/{id}/modal[?date=YYYY-MM-DD] — loaded via fetch() in timeline-items.
  Variables: $conversation, $messages (collection), $replies, $date (optional), $discordMentionMap
--}}
@php
$isEmail  = $conversation->channel_type === 'email';
$isTicket = $conversation->channel_type === 'ticket';

// Email header fields from first message meta
$emailFrom        = null;
$emailTo          = null;
$emailCc          = null;
$fromGravatarHash = null;
$firstMsg         = $messages->first();
if ($isEmail && $firstMsg) {
    $msgMeta   = $firstMsg->meta_json ?? [];
    $fromEmail = $firstMsg->identity?->value ?? null;
    $fromName  = $firstMsg->author_name;
    $emailFrom = $fromName ? "{$fromName} <{$fromEmail}>" : $fromEmail;
    $emailTo   = $msgMeta['to'] ?? null;
    $emailCc   = $msgMeta['cc'] ?? null;
    if ($fromEmail) {
        $fromGravatarHash = md5(strtolower(trim($fromEmail)));
    }
}
@endphp
<div class="p-5">

    {{-- Header --}}
    <div class="mb-4 pr-6">
        <div class="flex items-center gap-2 mb-2">
            <x-channel-badge :type="$conversation->channel_type" />
            @if(!$isTicket && !$isEmail && $conversation->company)
                <span class="text-sm font-semibold text-gray-800">{{ $conversation->company->name }}</span>
            @endif
            @if($date)
                <span class="text-xs text-gray-400 shrink-0">{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</span>
            @endif
        </div>
        @if(!$isTicket && $conversation->subject)
            <h3 class="text-sm font-semibold text-gray-900 leading-snug break-words">{{ $conversation->subject }}</h3>
        @endif
    </div>

    {{-- Email headers --}}
    @if($isEmail && ($emailFrom || $emailTo || $emailCc))
        <div class="mb-4 bg-gray-50 rounded-lg px-3 py-2.5 text-xs space-y-1 border border-gray-100">
            @if($emailFrom)
                <div class="flex gap-2 items-start">
                    <span class="text-gray-400 w-7 shrink-0 mt-0.5">From</span>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        @if($fromGravatarHash)
                            <img src="https://www.gravatar.com/avatar/{{ $fromGravatarHash }}?d=identicon&s=32"
                                 class="w-5 h-5 rounded-full shrink-0">
                        @endif
                        <span class="text-gray-700 break-all">{{ $emailFrom }}</span>
                    </div>
                </div>
            @endif
            @if($emailTo)
                <div class="flex gap-2">
                    <span class="text-gray-400 w-7 shrink-0">To</span>
                    <span class="text-gray-700 break-all">{{ is_array($emailTo) ? implode(', ', $emailTo) : $emailTo }}</span>
                </div>
            @endif
            @if($emailCc)
                <div class="flex gap-2">
                    <span class="text-gray-400 w-7 shrink-0">CC</span>
                    <span class="text-gray-700 break-all">{{ is_array($emailCc) ? implode(', ', $emailCc) : $emailCc }}</span>
                </div>
            @endif
        </div>
    @endif

    {{-- Messages --}}
    <div class="mb-4">
        @include('conversations.partials.messages', [
            'messages'          => $messages,
            'replies'           => $replies,
            'discordMentionMap' => $discordMentionMap,
        ])
    </div>

    {{-- CTA --}}
    <a href="{{ route('conversations.show', $conversation) }}"
       class="block w-full text-center bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold py-2.5 rounded-lg transition">
        Show full discussion →
    </a>
</div>
