{{--
    Shared timeline items partial.
    Parameters:
      $activities      — collection of Activity models
      $nextCursor      — encoded cursor string or null
      $showPersonLink  — (bool) show person name link on company timeline
      $showCompanyLink — (bool) show company name link on person timeline
      $convSubjectMap  — (array) ticket extId → subject string (optional)
--}}
@foreach($activities as $activity)
    @php
        $url        = $activity->targetUrl();
        $meta       = $activity->meta_json ?? [];
        $isCustomer = $activity->direction() === 'customer';
        $showPersonLink  = $showPersonLink ?? false;
        $showCompanyLink = $showCompanyLink ?? false;
        $chType  = $activity->conversationChannelType(); // email|ticket|discord|slack|null
        $sysType = $meta['system_type'] ?? '';
        $sysSlug = $meta['system_slug'] ?? '';
        $convSubjectMap = $convSubjectMap ?? [];

        // Tooltip for the channel badge: "{Type}: {system_slug}"
        $badgeTitle = $chType ? (ucfirst($chType) . ': ' . $sysSlug) : null;

        $mcType = $meta['mc_type'] ?? '';
        $isMcTicket = in_array($mcType, ['Opened Ticket', 'Closed Ticket', 'Ticket Replied'], true);

        // --- WHMCS-native ticket lookup ---
        $convExtId   = $meta['conversation_external_id'] ?? '';
        $convMapEntry = $convSubjectMap[$convExtId] ?? null; // ['id'=>…,'subject'=>…] or null
        if (!$url && $convMapEntry) {
            $url = '/conversations/' . $convMapEntry['id'];
        }

        // --- MetricsCube ticket lookup ---
        $mcRelId    = $meta['relation_id'] ?? null;
        $mcMapEntry = ($isMcTicket && $mcRelId) ? ($convSubjectMap['ticket_' . $mcRelId] ?? null) : null;
        if ($isMcTicket && !$url && $mcMapEntry) {
            $url = '/conversations/' . $mcMapEntry['id'];
        }

        // Build source label (shown left of the title, specific to integration)
        $sourceLabel = null;
        if ($chType === 'email') {
            $sourceLabel = $meta['contact_email'] ?? null;
        } elseif ($chType === 'discord' || $chType === 'slack') {
            $sourceLabel = $meta['description'] ?? null; // already "#channelname"
        } elseif ($chType === 'ticket') {
            preg_match('/ticket_(\d+)/', $convExtId, $_tm);
            $ticketNum = $_tm[1] ?? null;
            $sourceLabel = null;
        }

        // Title text (clickable)
        $titleText = null;
        if ($chType === 'email') {
            $titleText = $meta['subject'] ?? $meta['description'] ?? null;
        } elseif ($chType === 'ticket') {
            if ($isMcTicket) {
                // MetricsCube: show subject only — no #id prefix, no customer name
                if ($mcMapEntry) {
                    $titleText = $mcMapEntry['subject'];
                } else {
                    // Fallback to description but strip leading customer name
                    $desc     = $meta['description'] ?? null;
                    $customer = trim($meta['customer'] ?? '');
                    if ($desc && $customer && mb_stripos($desc, $customer) === 0) {
                        $desc = trim(mb_substr($desc, mb_strlen($customer)));
                        $desc = preg_replace('/^[\s\-–—]+/u', '', $desc);
                    }
                    $titleText = $desc;
                }
            } else {
                // WHMCS-native: conversation_external_id = "ticket_NNN"
                $subject   = $meta['subject'] ?? ($convMapEntry ? $convMapEntry['subject'] : null);
                $titleText = $ticketNum
                    ? ('#' . $ticketNum . ($subject ? ' — ' . $subject : ''))
                    : ($subject ?? $meta['description'] ?? null);
            }
        } elseif ($chType === 'discord' || $chType === 'slack') {
            $titleText = null; // channel name shown as sourceLabel; whole row is clickable
        } else {
            $titleText = $meta['description'] ?? $meta['text'] ?? $meta['subject'] ?? $meta['title'] ?? null;
        }

        // Modal URL for conversation preview
        $modalDate = null;
        if (in_array($chType, ['discord', 'slack'], true)) {
            $modalDate = $activity->occurred_at->format('Y-m-d');
        }
        $modalUrl = ($url && preg_match('#^/conversations/(\d+)$#', $url))
            ? $url . '/modal' . ($modalDate ? '?date=' . $modalDate : '')
            : null;

        // Discord/Slack: whole row is clickable
        $rowClickable = in_array($chType, ['discord', 'slack'], true) && $modalUrl;

        // "not found" indicator — only when we couldn't resolve the conversation
        $ticketNotFound = null;
        if ($isMcTicket && !$url) {
            $ticketNotFound = $mcRelId;
        } elseif ($chType === 'ticket' && !$url) {
            preg_match('/ticket_(\d+)/', $convExtId, $_tm3);
            $ticketNotFound = $_tm3[1] ?? null;
        }

        $useBadge = ($chType === null && $sysType !== 'metricscube')
                 || ($chType === null && $sysType === 'metricscube' && !$isMcTicket);

        // Hover tooltip: full title + "not found" notice (computed after $ticketNotFound)
        $hoverText = $titleText ?? '';
        if ($ticketNotFound !== null) {
            $notFoundMsg = 'Cannot find corresponding ticket in WHMCS' . ($sysSlug ? ' ' . $sysSlug : '');
            $hoverText   = ($hoverText !== '' ? $hoverText . "\n" : '') . $notFoundMsg;
        }
    @endphp

    {{-- LEFT cell (customer) --}}
    <div class="flex items-center justify-end py-1.5 pr-2">
        @if($isCustomer)
            <div class="flex items-center gap-1.5 max-w-full group rounded-lg px-2 py-1 transition-colors min-w-0
                        {{ $rowClickable ? 'hover:bg-gray-100 cursor-pointer' : 'hover:bg-gray-50' }}"
                 @if($rowClickable) onclick="openActivityModal(this)" data-modal-src="{{ $modalUrl }}" @endif>

                {{-- Icon with tooltip --}}
                @if($chType)
                    <span @if($badgeTitle) title="{{ $badgeTitle }}" @endif class="shrink-0">
                        <x-channel-badge :type="$chType" :label="false" />
                    </span>
                @else
                    <span class="shrink-0 text-xs font-medium px-2 py-0.5 rounded-full border {{ $activity->timelineColor() }} whitespace-nowrap">
                        {{ $activity->timelineLabel() }}
                    </span>
                @endif

                {{-- Source label --}}
                @if($sourceLabel)
                    <span class="text-xs text-gray-400 shrink-0 font-mono truncate max-w-[120px]" title="{{ $sourceLabel }}">{{ $sourceLabel }}</span>
                @endif

                {{-- Title / main text --}}
                @if($titleText)
                    @if($modalUrl && !$rowClickable)
                        <button type="button" data-modal-src="{{ $modalUrl }}"
                                onclick="openActivityModal(this)"
                                class="text-xs text-blue-600 hover:text-blue-800 truncate max-w-[180px] text-right cursor-pointer"
                                title="{{ $hoverText }}">{{ $titleText }}</button>
                    @elseif($ticketNotFound)
                        <span class="text-xs text-red-500 truncate max-w-[180px]"
                              title="{{ $hoverText }}">{{ $titleText }}</span>
                    @elseif($url && !$modalUrl)
                        <a href="{{ $url }}" class="text-xs text-blue-600 hover:text-blue-800 truncate max-w-[180px] text-right" title="{{ $hoverText }}">{{ $titleText }}</a>
                    @else
                        <span class="text-xs text-gray-600 truncate max-w-[180px]" title="{{ $hoverText }}">{{ $titleText }}</span>
                    @endif
                @elseif($ticketNotFound)
                    <span class="text-xs text-red-400 italic truncate max-w-[180px]" title="{{ $hoverText }}">?</span>
                @endif

                <div class="flex-1 min-w-0"></div>
                @if($showPersonLink && $activity->person)
                    <a href="{{ route('people.show', $activity->person) }}"
                       class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] truncate">
                        {{ $activity->person->full_name }}
                    </a>
                @endif
                @if($showCompanyLink && $activity->company)
                    <a href="{{ route('companies.show', $activity->company) }}"
                       class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] truncate">
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
            <div class="flex items-center gap-1.5 max-w-full group rounded-lg px-2 py-1 transition-colors min-w-0
                        {{ $rowClickable ? 'hover:bg-gray-100 cursor-pointer' : 'hover:bg-gray-50' }}"
                 @if($rowClickable) onclick="openActivityModal(this)" data-modal-src="{{ $modalUrl }}" @endif>

                {{-- Icon with tooltip --}}
                @if($chType)
                    <span @if($badgeTitle) title="{{ $badgeTitle }}" @endif class="shrink-0">
                        <x-channel-badge :type="$chType" :label="false" />
                    </span>
                @else
                    <span class="shrink-0 text-xs font-medium px-2 py-0.5 rounded-full border {{ $activity->timelineColor() }} whitespace-nowrap">
                        {{ $activity->timelineLabel() }}
                    </span>
                @endif

                {{-- Source label --}}
                @if($sourceLabel)
                    <span class="text-xs text-gray-400 shrink-0 font-mono truncate max-w-[120px]" title="{{ $sourceLabel }}">{{ $sourceLabel }}</span>
                @endif

                {{-- Title / main text --}}
                @if($titleText)
                    @if($modalUrl && !$rowClickable)
                        <button type="button" data-modal-src="{{ $modalUrl }}"
                                onclick="openActivityModal(this)"
                                class="text-xs text-blue-600 hover:text-blue-800 truncate max-w-[180px] text-left cursor-pointer"
                                title="{{ $hoverText }}">{{ $titleText }}</button>
                    @elseif($ticketNotFound)
                        <span class="text-xs text-red-500 truncate max-w-[180px]"
                              title="{{ $hoverText }}">{{ $titleText }}</span>
                    @elseif($url && !$modalUrl)
                        <a href="{{ $url }}" class="text-xs text-blue-600 hover:text-blue-800 truncate max-w-[180px]" title="{{ $hoverText }}">{{ $titleText }}</a>
                    @else
                        <span class="text-xs text-gray-600 truncate max-w-[180px]" title="{{ $hoverText }}">{{ $titleText }}</span>
                    @endif
                @elseif($ticketNotFound)
                    <span class="text-xs text-red-400 italic truncate max-w-[180px]" title="{{ $hoverText }}">?</span>
                @endif

                <div class="flex-1 min-w-0"></div>
                @if($showPersonLink && $activity->person)
                    <a href="{{ route('people.show', $activity->person) }}"
                       class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] truncate">
                        {{ $activity->person->full_name }}
                    </a>
                @endif
                @if($showCompanyLink && $activity->company)
                    <a href="{{ route('companies.show', $activity->company) }}"
                       class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] truncate">
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
