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
@php $d = $activity->_display; @endphp

    {{-- LEFT cell (customer) --}}
    <div class="flex items-start justify-end py-1.5 pr-2">
        @if($d->isCustomer)
            <div class="flex items-start gap-1.5 max-w-full group rounded-lg px-2 py-1 transition-colors min-w-0
                        {{ $d->rowClickable ? 'hover:bg-gray-100 cursor-pointer' : 'hover:bg-gray-50' }}"
                 @if($d->rowClickable) onclick="openActivityModal(this)" data-modal-src="{{ $d->modalUrl }}" @endif>

                {{-- Icon --}}
                @if($d->chType)
                    <span @if($d->badgeTitle) title="{{ $d->badgeTitle }}" @endif class="shrink-0 mt-0.5">
                        <x-channel-badge :type="$d->chType" :label="false" />
                    </span>
                @else
                    <span class="shrink-0 text-xs font-medium px-2 py-0.5 rounded-full border {{ $activity->timelineColor() }} whitespace-nowrap mt-0.5">
                        {{ $activity->timelineLabel() }}
                    </span>
                @endif

                {{-- Email: 2-line layout --}}
                @if($d->isEmail)
                    <div class="min-w-0 flex-1 flex flex-col gap-px text-right">
                        <div class="flex items-center justify-end gap-1 min-w-0">
                            <span class="text-[10px] text-gray-400 shrink-0">{{ $d->isOutbound ? 'to:' : 'from:' }}</span>
                            <span class="text-[10px] text-gray-500 truncate max-w-[160px]">{{ $d->sourceLabel }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 min-w-0">
                            <div class="flex-1 min-w-0"></div>
                            @if(($showPersonLink ?? false) && $activity->person)
                                <a href="{{ route('people.show', $activity->person) }}" class="text-[10px] text-brand-600 hover:underline shrink-0 max-w-[70px] truncate">{{ $activity->person->full_name }}</a>
                            @endif
                            @if(($showCompanyLink ?? false) && $activity->company)
                                <a href="{{ route('companies.show', $activity->company) }}" class="text-[10px] text-brand-600 hover:underline shrink-0 max-w-[70px] truncate">{{ $activity->company->name }}</a>
                            @endif
                            @if($d->titleText)
                                @if($d->modalUrl)
                                    <button type="button" data-modal-src="{{ $d->modalUrl }}" onclick="openActivityModal(this)"
                                            class="text-xs link truncate max-w-[160px] text-right cursor-pointer" title="{{ $d->hoverText }}">{{ $d->titleText }}</button>
                                @elseif($d->url)
                                    <a href="{{ $d->url }}" class="text-xs link truncate max-w-[160px] text-right" title="{{ $d->hoverText }}">{{ $d->titleText }}</a>
                                @else
                                    <span class="text-xs text-gray-600 truncate max-w-[160px]" title="{{ $d->hoverText }}">{{ $d->titleText }}</span>
                                @endif
                            @endif
                            <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                                  title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                        </div>
                    </div>

                {{-- Slack / Discord: 2-line layout --}}
                @elseif($d->isSlack || $d->isDiscord)
                    <div class="min-w-0 flex-1 flex flex-col gap-px text-right">
                        <div class="flex items-center gap-1.5 min-w-0 justify-end">
                            @if($d->sourceLabel)
                                <span class="text-xs text-gray-400 shrink-0 font-mono truncate max-w-[120px]" title="{{ $d->sourceLabel }}">{{ $d->sourceLabel }}</span>
                            @endif
                            @if(($showPersonLink ?? false) && $activity->person)
                                <a href="{{ route('people.show', $activity->person) }}" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[70px] truncate">{{ $activity->person->full_name }}</a>
                            @endif
                            @if(($showCompanyLink ?? false) && $activity->company)
                                <a href="{{ route('companies.show', $activity->company) }}" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[70px] truncate">{{ $activity->company->name }}</a>
                            @endif
                            <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                                  title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                        </div>
                        @if($d->participants)
                            <div class="text-[10px] text-gray-400 truncate max-w-full text-right">{{ $d->participants }}</div>
                        @endif
                    </div>

                {{-- Other: single-line layout --}}
                @else
                    @if($d->sourceLabel)
                        <span class="text-xs text-gray-400 shrink-0 font-mono truncate max-w-[120px]" title="{{ $d->sourceLabel }}">{{ $d->sourceLabel }}</span>
                    @endif
                    @if($d->titleText)
                        @if($d->modalUrl && !$d->rowClickable)
                            <button type="button" data-modal-src="{{ $d->modalUrl }}" onclick="openActivityModal(this)"
                                    class="text-xs link truncate max-w-[180px] text-right cursor-pointer" title="{{ $d->hoverText }}">{{ $d->titleText }}</button>
                        @elseif($d->ticketNotFound)
                            <span class="text-xs text-red-500 truncate max-w-[180px]" title="{{ $d->hoverText }}">{{ $d->titleText }}</span>
                        @elseif($d->url && !$d->modalUrl)
                            <a href="{{ $d->url }}" class="text-xs link truncate max-w-[180px] text-right" title="{{ $d->hoverText }}">{{ $d->titleText }}</a>
                        @else
                            <span class="text-xs text-gray-600 truncate max-w-[180px]" title="{{ $d->hoverText }}">{{ $d->titleText }}</span>
                        @endif
                    @elseif($d->ticketNotFound)
                        <span class="text-xs text-red-400 font-mono truncate max-w-[180px]" title="{{ $d->hoverText }}">#{{ $d->ticketNotFound }}</span>
                    @endif
                    <div class="flex-1 min-w-0"></div>
                    @if(($showPersonLink ?? false) && $activity->person)
                        <a href="{{ route('people.show', $activity->person) }}" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] truncate">{{ $activity->person->full_name }}</a>
                    @endif
                    @if(($showCompanyLink ?? false) && $activity->company)
                        <a href="{{ route('companies.show', $activity->company) }}" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] truncate">{{ $activity->company->name }}</a>
                    @endif
                    <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                          title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                    @if(!$d->modalUrl && $d->url)
                        <a href="{{ $d->url }}" class="opacity-20 group-hover:opacity-60 hover:!opacity-100 shrink-0 transition-opacity">
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

    {{-- CENTER dot --}}
    <div class="flex items-start justify-center pt-[10px] pb-1.5 relative z-10">
        <div class="w-2.5 h-2.5 rounded-full ring-2 border-2 border-white shrink-0 {{ $activity->dotColor() }}"></div>
    </div>

    {{-- RIGHT cell (internal / our side) --}}
    <div class="flex items-start justify-start py-1.5 pl-2">
        @if(!$d->isCustomer)
            <div class="flex items-start gap-1.5 max-w-full group rounded-lg px-2 py-1 transition-colors min-w-0
                        {{ $d->rowClickable ? 'hover:bg-gray-100 cursor-pointer' : 'hover:bg-gray-50' }}"
                 @if($d->rowClickable) onclick="openActivityModal(this)" data-modal-src="{{ $d->modalUrl }}" @endif>

                {{-- Icon --}}
                @if($d->chType)
                    <span @if($d->badgeTitle) title="{{ $d->badgeTitle }}" @endif class="shrink-0 mt-0.5">
                        <x-channel-badge :type="$d->chType" :label="false" />
                    </span>
                @else
                    <span class="shrink-0 text-xs font-medium px-2 py-0.5 rounded-full border {{ $activity->timelineColor() }} whitespace-nowrap mt-0.5">
                        {{ $activity->timelineLabel() }}
                    </span>
                @endif

                {{-- Email: 2-line layout --}}
                @if($d->isEmail)
                    <div class="min-w-0 flex-1 flex flex-col gap-px">
                        <div class="flex items-center gap-1 min-w-0">
                            <span class="text-[10px] text-gray-400 shrink-0">{{ $d->isOutbound ? 'to:' : 'from:' }}</span>
                            <span class="text-[10px] text-gray-500 truncate max-w-[160px]">{{ $d->sourceLabel }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 min-w-0">
                            @if($d->titleText)
                                @if($d->modalUrl)
                                    <button type="button" data-modal-src="{{ $d->modalUrl }}" onclick="openActivityModal(this)"
                                            class="text-xs link truncate max-w-[160px] text-left cursor-pointer" title="{{ $d->hoverText }}">{{ $d->titleText }}</button>
                                @elseif($d->url)
                                    <a href="{{ $d->url }}" class="text-xs link truncate max-w-[160px]" title="{{ $d->hoverText }}">{{ $d->titleText }}</a>
                                @else
                                    <span class="text-xs text-gray-600 truncate max-w-[160px]" title="{{ $d->hoverText }}">{{ $d->titleText }}</span>
                                @endif
                            @endif
                            <div class="flex-1 min-w-0"></div>
                            @if(($showPersonLink ?? false) && $activity->person)
                                <a href="{{ route('people.show', $activity->person) }}" class="text-[10px] text-brand-600 hover:underline shrink-0 max-w-[70px] truncate">{{ $activity->person->full_name }}</a>
                            @endif
                            @if(($showCompanyLink ?? false) && $activity->company)
                                <a href="{{ route('companies.show', $activity->company) }}" class="text-[10px] text-brand-600 hover:underline shrink-0 max-w-[70px] truncate">{{ $activity->company->name }}</a>
                            @endif
                            <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                                  title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                        </div>
                    </div>

                {{-- Slack / Discord: 2-line layout --}}
                @elseif($d->isSlack || $d->isDiscord)
                    <div class="min-w-0 flex-1 flex flex-col gap-px">
                        <div class="flex items-center gap-1.5 min-w-0">
                            @if($d->sourceLabel)
                                <span class="text-xs text-gray-400 shrink-0 font-mono truncate max-w-[120px]" title="{{ $d->sourceLabel }}">{{ $d->sourceLabel }}</span>
                            @endif
                            <div class="flex-1 min-w-0"></div>
                            @if(($showPersonLink ?? false) && $activity->person)
                                <a href="{{ route('people.show', $activity->person) }}" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[70px] truncate">{{ $activity->person->full_name }}</a>
                            @endif
                            @if(($showCompanyLink ?? false) && $activity->company)
                                <a href="{{ route('companies.show', $activity->company) }}" class="text-[10px] text-brand-600 hover:underline shrink-0 max-w-[70px] truncate">{{ $activity->company->name }}</a>
                            @endif
                            <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                                  title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                        </div>
                        @if($d->participants)
                            <div class="text-[10px] text-gray-400 truncate max-w-full">{{ $d->participants }}</div>
                        @endif
                    </div>

                {{-- Other: single-line layout --}}
                @else
                    @if($d->sourceLabel)
                        <span class="text-xs text-gray-400 shrink-0 font-mono truncate max-w-[120px]" title="{{ $d->sourceLabel }}">{{ $d->sourceLabel }}</span>
                    @endif
                    @if($d->titleText)
                        @if($d->modalUrl && !$d->rowClickable)
                            <button type="button" data-modal-src="{{ $d->modalUrl }}" onclick="openActivityModal(this)"
                                    class="text-xs link truncate max-w-[180px] text-left cursor-pointer" title="{{ $d->hoverText }}">{{ $d->titleText }}</button>
                        @elseif($d->ticketNotFound)
                            <span class="text-xs text-red-500 truncate max-w-[180px]" title="{{ $d->hoverText }}">{{ $d->titleText }}</span>
                        @elseif($d->url && !$d->modalUrl)
                            <a href="{{ $d->url }}" class="text-xs link truncate max-w-[180px]" title="{{ $d->hoverText }}">{{ $d->titleText }}</a>
                        @else
                            <span class="text-xs text-gray-600 truncate max-w-[180px]" title="{{ $d->hoverText }}">{{ $d->titleText }}</span>
                        @endif
                    @elseif($d->ticketNotFound)
                        <span class="text-xs text-red-400 font-mono truncate max-w-[180px]" title="{{ $d->hoverText }}">#{{ $d->ticketNotFound }}</span>
                    @endif
                    <div class="flex-1 min-w-0"></div>
                    @if(($showPersonLink ?? false) && $activity->person)
                        <a href="{{ route('people.show', $activity->person) }}" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] truncate">{{ $activity->person->full_name }}</a>
                    @endif
                    @if(($showCompanyLink ?? false) && $activity->company)
                        <a href="{{ route('companies.show', $activity->company) }}" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] truncate">{{ $activity->company->name }}</a>
                    @endif
                    <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap"
                          title="{{ $activity->occurred_at->format('d M Y H:i') }}">{{ $activity->occurred_at->diffForHumans(null, true, true) }}</span>
                    @if(!$d->modalUrl && $d->url)
                        <a href="{{ $d->url }}" class="opacity-20 group-hover:opacity-60 hover:!opacity-100 shrink-0 transition-opacity">
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
