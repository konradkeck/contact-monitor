{{--
    Shared timeline items partial.
    Parameters:
      $activities      — collection of Activity models (each has _display pre-computed)
      $nextCursor      — encoded cursor string or null
      $showPersonLink  — (bool) show person name link on company timeline
      $showCompanyLink — (bool) show company name link on person timeline
      $convSubjectMap  — (array) ticket extId → subject string (optional)
--}}
@foreach($activities as $activity)

    {{-- LEFT cell (customer) --}}
    <div class="flex items-center justify-end py-1.5 pr-2 min-w-0 overflow-hidden">
        @if($activity->display->isCustomer)
            <div class="flex items-center gap-1.5 max-w-full group rounded-lg px-2 py-1 transition-colors min-w-0
                        {{ $activity->display->rowClickable ? 'hover:bg-gray-100 cursor-pointer' : 'hover:bg-gray-50' }}"
                 @if($activity->display->rowClickable) onclick="openActivityModal(this)" data-modal-src="{{ $activity->display->modalUrl }}" @endif>

                {{-- Icon --}}
                @if($activity->display->chType)
                    <span @if($activity->display->badgeTitle) title="{{ $activity->display->badgeTitle }}" @endif class="shrink-0">
                        <x-channel-badge :type="$activity->display->chType" :label="false" />
                    </span>
                @else
                    <span class="hidden md:inline shrink-0 text-xs font-medium px-2 py-0.5 rounded-full border {{ $activity->timelineColor() }} whitespace-nowrap">
                        {{ $activity->timelineLabel() }}
                    </span>
                @endif

                {{-- Email: 2-line layout --}}
                @if($activity->display->isEmail)
                    <div class="min-w-0 flex-1 flex flex-col gap-px text-right">
                        @if($activity->display->sourceLabel)
                        <div class="flex items-center justify-end gap-1 min-w-0">
                            <span class="text-[10px] text-gray-400 shrink-0">{{ $activity->display->isOutbound ? 'to:' : 'from:' }}</span>
                            @if($activity->display->sourcePerson)
                                <a href="{{ route('people.show', $activity->display->sourcePerson) }}" class="text-[10px] text-brand-600 hover:underline truncate max-w-[120px] md:max-w-[260px]">{{ $activity->display->sourceLabel }}</a>
                            @else
                                <span class="text-[10px] text-gray-500 truncate max-w-[120px] md:max-w-[260px]">{{ $activity->display->sourceLabel }}</span>
                            @endif
                        </div>
                        @endif
                        @if($activity->display->counterpartLabel)
                        <div class="flex items-center justify-end gap-1 min-w-0">
                            <span class="text-[10px] text-gray-400 shrink-0">{{ $activity->display->isOutbound ? 'from:' : 'to:' }}</span>
                            @if(($showPersonLink ?? false) && $activity->person)
                                <a href="{{ route('people.show', $activity->person) }}" class="text-[10px] text-brand-600 hover:underline truncate max-w-[120px] md:max-w-[260px]">{{ $activity->display->counterpartLabel }}</a>
                            @elseif(($showCompanyLink ?? false) && $activity->company)
                                <a href="{{ route('companies.show', $activity->company) }}" class="text-[10px] text-brand-600 hover:underline truncate max-w-[120px] md:max-w-[260px]">{{ $activity->display->counterpartLabel }}</a>
                            @else
                                <span class="text-[10px] text-gray-500 truncate max-w-[120px] md:max-w-[260px]">{{ $activity->display->counterpartLabel }}</span>
                            @endif
                        </div>
                        @endif
                        <div class="flex items-center gap-1.5 min-w-0">
                            <div class="flex-1 min-w-0"></div>
                            @if($activity->display->titleText)
                                @if($activity->display->modalUrl)
                                    <button type="button" data-modal-src="{{ $activity->display->modalUrl }}" onclick="openActivityModal(this)"
                                            class="text-xs link truncate max-w-[120px] md:max-w-[260px] text-right cursor-pointer" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</button>
                                @elseif($activity->display->url)
                                    <a href="{{ $activity->display->url }}" class="text-xs link truncate max-w-[120px] md:max-w-[260px] text-right" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</a>
                                @else
                                    <span class="text-xs text-gray-600 truncate max-w-[120px] md:max-w-[260px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</span>
                                @endif
                            @endif
                            <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                                  title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                        </div>
                    </div>

                {{-- Slack / Discord: 2-line layout --}}
                @elseif($activity->display->isSlack || $activity->display->isDiscord)
                    <div class="min-w-0 flex-1 flex flex-col gap-px text-right">
                        <div class="flex items-center gap-1.5 min-w-0 justify-end">
                            @if($activity->display->sourceLabel)
                                <span class="text-xs text-gray-400 shrink-0 font-mono truncate max-w-[100px] md:max-w-[200px]" title="{{ $activity->display->sourceLabel }}">{{ $activity->display->sourceLabel }}</span>
                            @endif
                            @if(($showPersonLink ?? false) && $activity->person)
                                <a href="{{ route('people.show', $activity->person) }}" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[70px] md:max-w-[130px] truncate">{{ $activity->person->full_name }}</a>
                            @endif
                            @if(($showCompanyLink ?? false) && $activity->company)
                                <a href="{{ route('companies.show', $activity->company) }}" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[70px] md:max-w-[130px] truncate">{{ $activity->company->name }}</a>
                            @endif
                            <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                                  title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                        </div>
                        @if($activity->display->participantLinks)
                            <div class="flex items-center justify-end flex-wrap gap-x-1 gap-y-0">
                                @foreach($activity->display->participantLinks as $part)
                                    @if($part['person'])
                                        <a href="{{ route('people.show', $part['person']) }}" class="text-[10px] text-brand-600 hover:underline">{{ $part['name'] }}</a>
                                    @else
                                        <span class="text-[10px] text-gray-400">{{ $part['name'] }}</span>
                                    @endif
                                    @if(!$loop->last)<span class="text-[10px] text-gray-300">,</span>@endif
                                @endforeach
                            </div>
                        @elseif($activity->display->participants)
                            <div class="text-[10px] text-gray-400 truncate max-w-full text-right">{{ $activity->display->participants }}</div>
                        @endif
                    </div>

                {{-- Ticket (WHMCS): 2-line layout --}}
                @elseif($activity->display->isTicket)
                    <div class="min-w-0 flex-1 flex flex-col gap-px text-right">
                        <div class="flex items-center gap-1.5 min-w-0 justify-end">
                            @if($activity->display->ticketNotFound)
                                <span class="text-xs text-red-400 truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText ?: '#' . $activity->display->ticketNotFound }}</span>
                            @elseif($activity->display->titleText && $activity->display->url)
                                <a href="{{ $activity->display->url }}" class="text-xs link truncate max-w-[140px] md:max-w-[300px] text-right" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</a>
                            @elseif($activity->display->titleText)
                                <span class="text-xs text-gray-600 truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</span>
                            @endif
                            <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                                  title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                        </div>
                        @if($activity->display->department)
                            <div class="text-[10px] text-gray-400 truncate max-w-full text-right">{{ $activity->display->department }}</div>
                        @endif
                    </div>

                {{-- MetricsCube: 2-line layout --}}
                @elseif($activity->display->isMetricscube)
                    <div class="min-w-0 flex-1 flex flex-col gap-px text-right">
                        <div class="flex items-center gap-1.5 min-w-0 justify-end">
                            @if($activity->display->ticketNotFound)
                                <span class="text-xs text-red-400 truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText ?: '#' . $activity->display->ticketNotFound }}</span>
                            @elseif($activity->display->titleText && $activity->display->url)
                                <a href="{{ $activity->display->url }}" class="text-xs link truncate max-w-[140px] md:max-w-[300px] text-right" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</a>
                            @elseif($activity->display->titleText)
                                <span class="text-xs text-gray-600 truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</span>
                            @endif
                            <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                                  title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                        </div>
                        @if($activity->display->mcType)
                            <div class="text-[10px] text-gray-400 truncate max-w-full text-right">{{ $activity->display->mcType }}</div>
                        @endif
                    </div>

                {{-- Other: single-line layout --}}
                @else
                    @if($activity->display->sourceLabel)
                        <span class="text-xs text-gray-400 shrink-0 font-mono truncate max-w-[100px] md:max-w-[200px]" title="{{ $activity->display->sourceLabel }}">{{ $activity->display->sourceLabel }}</span>
                    @endif
                    @if($activity->display->titleText)
                        @if($activity->display->modalUrl && !$activity->display->rowClickable)
                            <button type="button" data-modal-src="{{ $activity->display->modalUrl }}" onclick="openActivityModal(this)"
                                    class="text-xs link truncate max-w-[140px] md:max-w-[300px] text-right cursor-pointer" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</button>
                        @elseif($activity->display->ticketNotFound)
                            <span class="text-xs text-red-500 truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</span>
                        @elseif($activity->display->url && !$activity->display->modalUrl)
                            <a href="{{ $activity->display->url }}" class="text-xs link truncate max-w-[140px] md:max-w-[300px] text-right" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</a>
                        @else
                            <span class="text-xs text-gray-600 truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</span>
                        @endif
                    @elseif($activity->display->ticketNotFound)
                        <span class="text-xs text-red-400 font-mono truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">#{{ $activity->display->ticketNotFound }}</span>
                    @endif
                    <div class="flex-1 min-w-0"></div>
                    @if(($showPersonLink ?? false) && $activity->person)
                        <a href="{{ route('people.show', $activity->person) }}" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] md:max-w-[140px] truncate">{{ $activity->person->full_name }}</a>
                    @endif
                    @if(($showCompanyLink ?? false) && $activity->company)
                        <a href="{{ route('companies.show', $activity->company) }}" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] md:max-w-[140px] truncate">{{ $activity->company->name }}</a>
                    @endif
                    <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                          title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                    @if(!$activity->display->modalUrl && $activity->display->url)
                        <a href="{{ $activity->display->url }}" class="opacity-20 group-hover:opacity-60 hover:!opacity-100 shrink-0 transition-opacity">
                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                    @endif
                @endif
            </div>
        @endif
    </div>

    {{-- CENTER dot — vertically centered in the row --}}
    <div class="flex items-center justify-center py-2 relative z-10">
        <div class="w-2.5 h-2.5 rounded-full ring-2 border-2 border-white shrink-0 {{ $activity->dotColor() }}"></div>
    </div>

    {{-- RIGHT cell (internal / our side) --}}
    <div class="flex items-center justify-start py-1.5 pl-2 min-w-0 overflow-hidden">
        @if(!$activity->display->isCustomer)
            <div class="flex items-center gap-1.5 max-w-full group rounded-lg px-2 py-1 transition-colors min-w-0
                        {{ $activity->display->rowClickable ? 'hover:bg-gray-100 cursor-pointer' : 'hover:bg-gray-50' }}"
                 @if($activity->display->rowClickable) onclick="openActivityModal(this)" data-modal-src="{{ $activity->display->modalUrl }}" @endif>

                {{-- Icon --}}
                @if($activity->display->chType)
                    <span @if($activity->display->badgeTitle) title="{{ $activity->display->badgeTitle }}" @endif class="shrink-0">
                        <x-channel-badge :type="$activity->display->chType" :label="false" />
                    </span>
                @else
                    <span class="hidden md:inline shrink-0 text-xs font-medium px-2 py-0.5 rounded-full border {{ $activity->timelineColor() }} whitespace-nowrap">
                        {{ $activity->timelineLabel() }}
                    </span>
                @endif

                {{-- Email: 2-line layout --}}
                @if($activity->display->isEmail)
                    <div class="min-w-0 flex-1 flex flex-col gap-px">
                        @if($activity->display->sourceLabel)
                        <div class="flex items-center gap-1 min-w-0">
                            <span class="text-[10px] text-gray-400 shrink-0">{{ $activity->display->isOutbound ? 'to:' : 'from:' }}</span>
                            @if($activity->display->sourcePerson)
                                <a href="{{ route('people.show', $activity->display->sourcePerson) }}" class="text-[10px] text-brand-600 hover:underline truncate max-w-[120px] md:max-w-[260px]">{{ $activity->display->sourceLabel }}</a>
                            @else
                                <span class="text-[10px] text-gray-500 truncate max-w-[120px] md:max-w-[260px]">{{ $activity->display->sourceLabel }}</span>
                            @endif
                        </div>
                        @endif
                        @if($activity->display->counterpartLabel)
                        <div class="flex items-center gap-1 min-w-0">
                            <span class="text-[10px] text-gray-400 shrink-0">{{ $activity->display->isOutbound ? 'from:' : 'to:' }}</span>
                            @if(($showPersonLink ?? false) && $activity->person)
                                <a href="{{ route('people.show', $activity->person) }}" class="text-[10px] text-brand-600 hover:underline truncate max-w-[120px] md:max-w-[260px]">{{ $activity->display->counterpartLabel }}</a>
                            @elseif(($showCompanyLink ?? false) && $activity->company)
                                <a href="{{ route('companies.show', $activity->company) }}" class="text-[10px] text-brand-600 hover:underline truncate max-w-[120px] md:max-w-[260px]">{{ $activity->display->counterpartLabel }}</a>
                            @else
                                <span class="text-[10px] text-gray-500 truncate max-w-[120px] md:max-w-[260px]">{{ $activity->display->counterpartLabel }}</span>
                            @endif
                        </div>
                        @endif
                        <div class="flex items-center gap-1.5 min-w-0">
                            @if($activity->display->titleText)
                                @if($activity->display->modalUrl)
                                    <button type="button" data-modal-src="{{ $activity->display->modalUrl }}" onclick="openActivityModal(this)"
                                            class="text-xs link truncate max-w-[120px] md:max-w-[260px] text-left cursor-pointer" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</button>
                                @elseif($activity->display->url)
                                    <a href="{{ $activity->display->url }}" class="text-xs link truncate max-w-[120px] md:max-w-[260px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</a>
                                @else
                                    <span class="text-xs text-gray-600 truncate max-w-[120px] md:max-w-[260px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</span>
                                @endif
                            @endif
                            <div class="flex-1 min-w-0"></div>
                            <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                                  title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                        </div>
                    </div>

                {{-- Slack / Discord: 2-line layout --}}
                @elseif($activity->display->isSlack || $activity->display->isDiscord)
                    <div class="min-w-0 flex-1 flex flex-col gap-px">
                        <div class="flex items-center gap-1.5 min-w-0">
                            @if($activity->display->sourceLabel)
                                <span class="text-xs text-gray-400 shrink-0 font-mono truncate max-w-[100px] md:max-w-[200px]" title="{{ $activity->display->sourceLabel }}">{{ $activity->display->sourceLabel }}</span>
                            @endif
                            <div class="flex-1 min-w-0"></div>
                            @if(($showPersonLink ?? false) && $activity->person)
                                <a href="{{ route('people.show', $activity->person) }}" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[70px] md:max-w-[130px] truncate">{{ $activity->person->full_name }}</a>
                            @endif
                            @if(($showCompanyLink ?? false) && $activity->company)
                                <a href="{{ route('companies.show', $activity->company) }}" class="text-[10px] text-brand-600 hover:underline shrink-0 max-w-[70px] md:max-w-[130px] truncate">{{ $activity->company->name }}</a>
                            @endif
                            <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                                  title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                        </div>
                        @if($activity->display->participantLinks)
                            <div class="flex items-center flex-wrap gap-x-1 gap-y-0">
                                @foreach($activity->display->participantLinks as $part)
                                    @if($part['person'])
                                        <a href="{{ route('people.show', $part['person']) }}" class="text-[10px] text-brand-600 hover:underline">{{ $part['name'] }}</a>
                                    @else
                                        <span class="text-[10px] text-gray-400">{{ $part['name'] }}</span>
                                    @endif
                                    @if(!$loop->last)<span class="text-[10px] text-gray-300">,</span>@endif
                                @endforeach
                            </div>
                        @elseif($activity->display->participants)
                            <div class="text-[10px] text-gray-400 truncate max-w-full">{{ $activity->display->participants }}</div>
                        @endif
                    </div>

                {{-- Ticket (WHMCS): 2-line layout --}}
                @elseif($activity->display->isTicket)
                    <div class="min-w-0 flex-1 flex flex-col gap-px">
                        <div class="flex items-center gap-1.5 min-w-0">
                            @if($activity->display->ticketNotFound)
                                <span class="text-xs text-red-400 truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText ?: '#' . $activity->display->ticketNotFound }}</span>
                            @elseif($activity->display->titleText && $activity->display->url)
                                <a href="{{ $activity->display->url }}" class="text-xs link truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</a>
                            @elseif($activity->display->titleText)
                                <span class="text-xs text-gray-600 truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</span>
                            @endif
                            <div class="flex-1 min-w-0"></div>
                            <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                                  title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                        </div>
                        @if($activity->display->department)
                            <div class="text-[10px] text-gray-400 truncate max-w-full">{{ $activity->display->department }}</div>
                        @endif
                    </div>

                {{-- MetricsCube: 2-line layout --}}
                @elseif($activity->display->isMetricscube)
                    <div class="min-w-0 flex-1 flex flex-col gap-px">
                        <div class="flex items-center gap-1.5 min-w-0">
                            @if($activity->display->ticketNotFound)
                                <span class="text-xs text-red-400 font-mono truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">#{{ $activity->display->ticketNotFound }}</span>
                            @elseif($activity->display->titleText && $activity->display->url)
                                <a href="{{ $activity->display->url }}" class="text-xs link truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</a>
                            @elseif($activity->display->titleText)
                                <span class="text-xs text-gray-600 truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</span>
                            @endif
                            <div class="flex-1 min-w-0"></div>
                            <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                                  title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                        </div>
                        @if($activity->display->mcType)
                            <div class="text-[10px] text-gray-400 truncate max-w-full">{{ $activity->display->mcType }}</div>
                        @endif
                    </div>

                {{-- Other: single-line layout --}}
                @else
                    @if($activity->display->sourceLabel)
                        <span class="text-xs text-gray-400 shrink-0 font-mono truncate max-w-[100px] md:max-w-[200px]" title="{{ $activity->display->sourceLabel }}">{{ $activity->display->sourceLabel }}</span>
                    @endif
                    @if($activity->display->titleText)
                        @if($activity->display->modalUrl && !$activity->display->rowClickable)
                            <button type="button" data-modal-src="{{ $activity->display->modalUrl }}" onclick="openActivityModal(this)"
                                    class="text-xs link truncate max-w-[140px] md:max-w-[300px] text-left cursor-pointer" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</button>
                        @elseif($activity->display->ticketNotFound)
                            <span class="text-xs text-red-500 truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</span>
                        @elseif($activity->display->url && !$activity->display->modalUrl)
                            <a href="{{ $activity->display->url }}" class="text-xs link truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</a>
                        @else
                            <span class="text-xs text-gray-600 truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">{{ $activity->display->titleText }}</span>
                        @endif
                    @elseif($activity->display->ticketNotFound)
                        <span class="text-xs text-red-400 font-mono truncate max-w-[140px] md:max-w-[300px]" title="{{ $activity->display->hoverText }}">#{{ $activity->display->ticketNotFound }}</span>
                    @endif
                    <div class="flex-1 min-w-0"></div>
                    @if(($showPersonLink ?? false) && $activity->person)
                        <a href="{{ route('people.show', $activity->person) }}" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] md:max-w-[140px] truncate">{{ $activity->person->full_name }}</a>
                    @endif
                    @if(($showCompanyLink ?? false) && $activity->company)
                        <a href="{{ route('companies.show', $activity->company) }}" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] md:max-w-[140px] truncate">{{ $activity->company->name }}</a>
                    @endif
                    <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                          title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                    @if(!$activity->display->modalUrl && $activity->display->url)
                        <a href="{{ $activity->display->url }}" class="opacity-20 group-hover:opacity-60 hover:!opacity-100 shrink-0 transition-opacity">
                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                    @endif
                @endif
            </div>
        @endif
    </div>

@endforeach

{{-- Sentinel / end --}}
@if(isset($nextCursor) && $nextCursor)
    <div id="timeline-sentinel" data-next-cursor="{{ $nextCursor }}" class="col-span-3 h-2"></div>
@else
    <div class="col-span-3 py-6 flex items-center px-8">
        <div class="flex-1 h-px bg-gray-100"></div>
    </div>
@endif
