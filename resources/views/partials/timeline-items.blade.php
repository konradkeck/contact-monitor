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
    <div class="flex items-center justify-end py-1.5 pr-2">
        @if($activity->_display->isCustomer)
            <div class="flex items-center gap-1.5 max-w-full group rounded-lg px-2 py-1 transition-colors min-w-0
                        {{ $activity->_display->rowClickable ? 'hover:bg-gray-100 cursor-pointer' : 'hover:bg-gray-50' }}"
                 @if($activity->_display->rowClickable) onclick="openActivityModal(this)" data-modal-src="{{ $activity->_display->modalUrl }}" @endif>

                {{-- Icon with tooltip --}}
                @if($activity->_display->chType)
                    <span @if($activity->_display->badgeTitle) title="{{ $activity->_display->badgeTitle }}" @endif class="shrink-0">
                        <x-channel-badge :type="$activity->_display->chType" :label="false" />
                    </span>
                @else
                    <span class="shrink-0 text-xs font-medium px-2 py-0.5 rounded-full border {{ $activity->timelineColor() }} whitespace-nowrap">
                        {{ $activity->timelineLabel() }}
                    </span>
                @endif

                {{-- Source label --}}
                @if($activity->_display->sourceLabel)
                    <span class="text-xs text-gray-400 shrink-0 font-mono truncate max-w-[120px]" title="{{ $activity->_display->sourceLabel }}">{{ $activity->_display->sourceLabel }}</span>
                @endif

                {{-- Title / main text --}}
                @if($activity->_display->titleText)
                    @if($activity->_display->modalUrl && !$activity->_display->rowClickable)
                        <button type="button" data-modal-src="{{ $activity->_display->modalUrl }}"
                                onclick="openActivityModal(this)"
                                class="text-xs text-blue-600 hover:text-blue-800 truncate max-w-[180px] text-right cursor-pointer"
                                title="{{ $activity->_display->hoverText }}">{{ $activity->_display->titleText }}</button>
                    @elseif($activity->_display->ticketNotFound)
                        <span class="text-xs text-red-500 truncate max-w-[180px]"
                              title="{{ $activity->_display->hoverText }}">{{ $activity->_display->titleText }}</span>
                    @elseif($activity->_display->url && !$activity->_display->modalUrl)
                        <a href="{{ $activity->_display->url }}" class="text-xs text-blue-600 hover:text-blue-800 truncate max-w-[180px] text-right" title="{{ $activity->_display->hoverText }}">{{ $activity->_display->titleText }}</a>
                    @else
                        <span class="text-xs text-gray-600 truncate max-w-[180px]" title="{{ $activity->_display->hoverText }}">{{ $activity->_display->titleText }}</span>
                    @endif
                @elseif($activity->_display->ticketNotFound)
                    <span class="text-xs text-red-400 font-mono truncate max-w-[180px]" title="{{ $activity->_display->hoverText }}">#{{ $activity->_display->ticketNotFound }}</span>
                @endif

                <div class="flex-1 min-w-0"></div>
                @if(($showPersonLink ?? false) && $activity->person)
                    <a href="{{ route('people.show', $activity->person) }}"
                       class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] truncate">
                        {{ $activity->person->full_name }}
                    </a>
                @endif
                @if(($showCompanyLink ?? false) && $activity->company)
                    <a href="{{ route('companies.show', $activity->company) }}"
                       class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] truncate">
                        {{ $activity->company->name }}
                    </a>
                @endif
                <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                      title="{{ $activity->occurred_at->format('d M Y H:i') }}">
                    {{ $activity->occurred_at->diffForHumans(null, true, true) }}
                </span>
                @if(!$activity->_display->modalUrl && $activity->_display->url)
                    <a href="{{ $activity->_display->url }}"
                       class="opacity-20 group-hover:opacity-60 hover:!opacity-100 shrink-0 transition-opacity">
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
        @if(!$activity->_display->isCustomer)
            <div class="flex items-center gap-1.5 max-w-full group rounded-lg px-2 py-1 transition-colors min-w-0
                        {{ $activity->_display->rowClickable ? 'hover:bg-gray-100 cursor-pointer' : 'hover:bg-gray-50' }}"
                 @if($activity->_display->rowClickable) onclick="openActivityModal(this)" data-modal-src="{{ $activity->_display->modalUrl }}" @endif>

                {{-- Icon with tooltip --}}
                @if($activity->_display->chType)
                    <span @if($activity->_display->badgeTitle) title="{{ $activity->_display->badgeTitle }}" @endif class="shrink-0">
                        <x-channel-badge :type="$activity->_display->chType" :label="false" />
                    </span>
                @else
                    <span class="shrink-0 text-xs font-medium px-2 py-0.5 rounded-full border {{ $activity->timelineColor() }} whitespace-nowrap">
                        {{ $activity->timelineLabel() }}
                    </span>
                @endif

                {{-- Source label --}}
                @if($activity->_display->sourceLabel)
                    <span class="text-xs text-gray-400 shrink-0 font-mono truncate max-w-[120px]" title="{{ $activity->_display->sourceLabel }}">{{ $activity->_display->sourceLabel }}</span>
                @endif

                {{-- Title / main text --}}
                @if($activity->_display->titleText)
                    @if($activity->_display->modalUrl && !$activity->_display->rowClickable)
                        <button type="button" data-modal-src="{{ $activity->_display->modalUrl }}"
                                onclick="openActivityModal(this)"
                                class="text-xs text-blue-600 hover:text-blue-800 truncate max-w-[180px] text-left cursor-pointer"
                                title="{{ $activity->_display->hoverText }}">{{ $activity->_display->titleText }}</button>
                    @elseif($activity->_display->ticketNotFound)
                        <span class="text-xs text-red-500 truncate max-w-[180px]"
                              title="{{ $activity->_display->hoverText }}">{{ $activity->_display->titleText }}</span>
                    @elseif($activity->_display->url && !$activity->_display->modalUrl)
                        <a href="{{ $activity->_display->url }}" class="text-xs text-blue-600 hover:text-blue-800 truncate max-w-[180px]" title="{{ $activity->_display->hoverText }}">{{ $activity->_display->titleText }}</a>
                    @else
                        <span class="text-xs text-gray-600 truncate max-w-[180px]" title="{{ $activity->_display->hoverText }}">{{ $activity->_display->titleText }}</span>
                    @endif
                @elseif($activity->_display->ticketNotFound)
                    <span class="text-xs text-red-400 font-mono truncate max-w-[180px]" title="{{ $activity->_display->hoverText }}">#{{ $activity->_display->ticketNotFound }}</span>
                @endif

                <div class="flex-1 min-w-0"></div>
                @if(($showPersonLink ?? false) && $activity->person)
                    <a href="{{ route('people.show', $activity->person) }}"
                       class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] truncate">
                        {{ $activity->person->full_name }}
                    </a>
                @endif
                @if(($showCompanyLink ?? false) && $activity->company)
                    <a href="{{ route('companies.show', $activity->company) }}"
                       class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] truncate">
                        {{ $activity->company->name }}
                    </a>
                @endif
                <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                      title="{{ $activity->occurred_at->format('d M Y H:i') }}">
                    {{ $activity->occurred_at->diffForHumans(null, true, true) }}
                </span>
                @if(!$activity->_display->modalUrl && $activity->_display->url)
                    <a href="{{ $activity->_display->url }}"
                       class="opacity-20 group-hover:opacity-60 hover:!opacity-100 shrink-0 transition-opacity">
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
    <div class="col-span-3 py-6 flex items-center px-8">
        <div class="flex-1 h-px bg-gray-100"></div>
    </div>
@endif
