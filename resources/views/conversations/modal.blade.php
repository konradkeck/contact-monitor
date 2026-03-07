{{--
  Conversation quick-view modal partial.
  Returned by GET /conversations/{id}/modal[?date=YYYY-MM-DD] — loaded via fetch() in timeline-items.
  Variables: $conversation, $messages (collection), $firstMsg, $date (optional), $discordMentionMap
--}}
@php
$resolveMentions = fn(?string $text) => $conversation->channel_type === 'discord'
    ? preg_replace_callback('/<@!?(\d+)>/', fn($m) => '@' . ($discordMentionMap[$m[1]] ?? $m[1]), $text ?? '')
    : ($text ?? '');
@endphp
<div class="p-4">

    {{-- Header --}}
    <div class="flex items-center gap-2 mb-3">
        <x-channel-badge :type="$conversation->channel_type" />
        @if($conversation->company)
            <span class="text-sm font-semibold text-gray-800">{{ $conversation->company->name }}</span>
        @endif
        @if($conversation->subject)
            <span class="text-sm text-gray-500 truncate">— {{ $conversation->subject }}</span>
        @endif
        @if($date)
            <span class="text-xs text-gray-400 shrink-0">{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</span>
        @endif
    </div>

    {{-- Messages --}}
    @if($messages->isNotEmpty())
        <div class="space-y-2 mb-4 max-h-64 overflow-y-auto">
            @foreach($messages as $msg)
                <div class="bg-gray-50 rounded-lg p-3 text-sm text-gray-700 leading-relaxed">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-semibold text-gray-800 text-xs">{{ $msg->author_name }}</span>
                        <span class="text-xs text-gray-400">{{ $msg->occurred_at->format('H:i') }}</span>
                    </div>
                    @if($msg->body_html)
                        <div class="prose prose-sm max-w-none text-xs">{!! $msg->body_html !!}</div>
                    @elseif($msg->body_text)
                        <p class="whitespace-pre-wrap text-xs">{{ Str::limit($resolveMentions($msg->body_text), 400) }}</p>
                    @else
                        <span class="text-gray-400 italic text-xs">No content.</span>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-400 italic mb-4">No messages{{ $date ? ' for this date' : '' }}.</p>
    @endif

    {{-- CTA --}}
    <a href="{{ route('conversations.show', $conversation) }}"
       class="block w-full text-center bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold py-2.5 rounded-lg transition">
        Show full discussion →
    </a>
</div>
