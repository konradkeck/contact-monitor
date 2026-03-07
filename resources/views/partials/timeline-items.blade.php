{{--
    Shared timeline items partial.
    Parameters:
      $activities      — collection of Activity models
      $nextCursor      — encoded cursor string or null
      $showPersonLink  — (bool) show person name link on company timeline
      $showCompanyLink — (bool) show company name link on person timeline
--}}
@foreach($activities as $activity)
    @php
        $url        = $activity->targetUrl();
        $meta       = $activity->meta_json ?? [];
        $mainText   = $meta['subject'] ?? $meta['text'] ?? $meta['title'] ?? $meta['description'] ?? null;
        $hoverText  = $meta['description'] ?? $mainText;
        $isCustomer = $activity->direction() === 'customer';
        $showPersonLink  = $showPersonLink ?? false;
        $showCompanyLink = $showCompanyLink ?? false;
        $chType     = $activity->conversationChannelType();
        // Detect conversation link → use modal instead of hard navigation
        // For Discord/Slack daily aggregates, pass the date to filter modal messages
        $modalDate  = null;
        $chTypeForDate = $meta['channel_type'] ?? null;
        if (in_array($chTypeForDate, ['discord', 'slack'], true)) {
            $modalDate = $activity->occurred_at->format('Y-m-d');
        }
        $modalUrl   = ($url && preg_match('#^/conversations/(\d+)$#', $url))
            ? $url . '/modal' . ($modalDate ? '?date=' . $modalDate : '')
            : null;
        // Detect ticket activity with no linked conversation
        $ticketNotFound = null;
        $mcType = $meta['mc_type'] ?? '';
        if (!$url && in_array($mcType, ['Opened Ticket', 'Closed Ticket', 'Ticket Replied'], true)) {
            if ($mcType === 'Ticket Replied') {
                preg_match('/ticket to #(\d+)/i', $meta['description'] ?? '', $_tm);
                $ticketNotFound = $_tm[1] ?? null;
            } else {
                $ticketNotFound = $meta['relation_id'] ?? null;
            }
        }
    @endphp

    {{-- LEFT cell (customer) --}}
    <div class="flex items-center justify-end py-1.5 pr-2">
        @if($isCustomer)
            <div class="flex items-center gap-2 max-w-full group hover:bg-gray-50 rounded-lg px-2 py-1 transition-colors min-w-0">
                @if($chType)
                    <x-channel-badge :type="$chType" class="shrink-0" />
                    @if($meta['system_slug'] ?? null)
                        <span class="text-xs text-gray-400 shrink-0 font-mono">{{ $meta['system_slug'] }}</span>
                    @endif
                @else
                    <span class="shrink-0 text-xs font-medium px-2 py-0.5 rounded-full border {{ $activity->timelineColor() }} whitespace-nowrap">
                        {{ $activity->timelineLabel() }}
                    </span>
                @endif
                @if($mainText)
                    @if($modalUrl)
                        <button type="button" data-modal-src="{{ $modalUrl }}"
                                onclick="openActivityModal(this)"
                                class="text-xs text-blue-600 hover:text-blue-800 truncate max-w-[200px] text-right cursor-pointer"
                                title="{{ $hoverText }}">{{ $mainText }}</button>
                    @elseif($ticketNotFound)
                        <span class="text-xs text-red-500 truncate max-w-[200px]"
                              title="Conversation not found (ticket #{{ $ticketNotFound }})">{{ $mainText }}</span>
                    @else
                        <span class="text-xs text-gray-600 truncate max-w-[200px]"
                              title="{{ $hoverText }}">{{ $mainText }}</span>
                    @endif
                @endif
                <div class="flex-1 min-w-0"></div>
                @if($showPersonLink && $activity->person)
                    <a href="{{ route('people.show', $activity->person) }}"
                       class="text-xs text-brand-600 hover:underline shrink-0 max-w-[90px] truncate">
                        {{ $activity->person->full_name }}
                    </a>
                @endif
                @if($showCompanyLink && $activity->company)
                    <a href="{{ route('companies.show', $activity->company) }}"
                       class="text-xs text-brand-600 hover:underline shrink-0 max-w-[90px] truncate">
                        {{ $activity->company->name }}
                    </a>
                @endif
                <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                      title="{{ $activity->occurred_at->format('d M Y H:i') }}">
                    {{ $activity->occurred_at->diffForHumans(null, true, true) }}
                </span>
                @if(!$modalUrl && $url)
                    <a href="{{ $url }}"
                       class="opacity-0 group-hover:opacity-60 hover:!opacity-100 shrink-0 transition-opacity">
                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                @endif
            </div>
        @endif
    </div>

    {{-- CENTER dot --}}
    <div class="flex items-center justify-center py-1.5 relative z-10">
        <div class="w-2.5 h-2.5 rounded-full ring-2 border-2 border-white shrink-0 {{ $activity->dotColor() }}"></div>
    </div>

    {{-- RIGHT cell (internal / our side) --}}
    <div class="flex items-center justify-start py-1.5 pl-2">
        @if(!$isCustomer)
            <div class="flex items-center gap-2 max-w-full group hover:bg-gray-50 rounded-lg px-2 py-1 transition-colors min-w-0">
                @if($chType)
                    <x-channel-badge :type="$chType" class="shrink-0" />
                    @if($meta['system_slug'] ?? null)
                        <span class="text-xs text-gray-400 shrink-0 font-mono">{{ $meta['system_slug'] }}</span>
                    @endif
                @else
                    <span class="shrink-0 text-xs font-medium px-2 py-0.5 rounded-full border {{ $activity->timelineColor() }} whitespace-nowrap">
                        {{ $activity->timelineLabel() }}
                    </span>
                @endif
                @if($mainText)
                    @if($modalUrl)
                        <button type="button" data-modal-src="{{ $modalUrl }}"
                                onclick="openActivityModal(this)"
                                class="text-xs text-blue-600 hover:text-blue-800 truncate max-w-[200px] text-left cursor-pointer"
                                title="{{ $hoverText }}">{{ $mainText }}</button>
                    @elseif($ticketNotFound)
                        <span class="text-xs text-red-500 truncate max-w-[200px]"
                              title="Conversation not found (ticket #{{ $ticketNotFound }})">{{ $mainText }}</span>
                    @else
                        <span class="text-xs text-gray-600 truncate max-w-[200px]"
                              title="{{ $hoverText }}">{{ $mainText }}</span>
                    @endif
                @endif
                <div class="flex-1 min-w-0"></div>
                @if($showPersonLink && $activity->person)
                    <a href="{{ route('people.show', $activity->person) }}"
                       class="text-xs text-brand-600 hover:underline shrink-0 max-w-[90px] truncate">
                        {{ $activity->person->full_name }}
                    </a>
                @endif
                @if($showCompanyLink && $activity->company)
                    <a href="{{ route('companies.show', $activity->company) }}"
                       class="text-xs text-brand-600 hover:underline shrink-0 max-w-[90px] truncate">
                        {{ $activity->company->name }}
                    </a>
                @endif
                <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                      title="{{ $activity->occurred_at->format('d M Y H:i') }}">
                    {{ $activity->occurred_at->diffForHumans(null, true, true) }}
                </span>
                @if(!$modalUrl && $url)
                    <a href="{{ $url }}"
                       class="opacity-0 group-hover:opacity-60 hover:!opacity-100 shrink-0 transition-opacity">
                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                @endif
            </div>
        @endif
    </div>

@endforeach

{{-- Sentinel / end --}}
@if(isset($nextCursor) && $nextCursor)
    <div id="timeline-sentinel" data-next-cursor="{{ $nextCursor }}" class="col-span-3 h-2"></div>
@else
    <div class="col-span-3 py-6 text-center text-xs text-gray-300">— end —</div>
@endif

