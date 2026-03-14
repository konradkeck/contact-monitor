<div id="popup-bp-{{ $company->id }}-{{ $bp->id }}"
     class="hidden fixed z-50 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
            w-[340px] bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <div class="min-w-0">
            <h3 class="font-semibold text-gray-800 truncate">
                {{ $bp->name }}{{ $bp->variant ? ' · '.$bp->variant : '' }}
            </h3>
            <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $company->name }}</p>
        </div>
        <button type="button" onclick="closeAllPopups()"
                class="text-gray-400 hover:text-gray-700 text-2xl leading-none ml-3 shrink-0">&times;</button>
    </div>
    <div class="px-5 py-4">
        <div class="flex items-center gap-4">
            <div class="relative w-16 h-16 shrink-0">
                <svg width="64" height="64" viewBox="0 0 64 64" style="transform:rotate(-90deg)">
                    <circle cx="32" cy="32" r="{{ $bpR }}" fill="none" stroke="#e5e7eb" stroke-width="5"/>
                    @if($bpSc)
                        <circle cx="32" cy="32" r="{{ $bpR }}" fill="none"
                                stroke="{{ $bpClr }}" stroke-width="5"
                                stroke-linecap="round"
                                style="stroke-dasharray:{{ number_format($bpCirc,3) }};stroke-dashoffset:{{ number_format($bpOff,3) }}"/>
                    @endif
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-xl font-bold text-gray-900">{{ $bpSc ?? '—' }}</span>
                </div>
            </div>
            <div>
                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $bpBadge }}">
                    {{ $status->stage }}
                </span>
                @if($status->last_evaluated_at)
                    <p class="text-xs text-gray-400 mt-1"
                       title="{{ $status->last_evaluated_at->format('D, j M Y \a\t H:i') }}">
                        Evaluated {{ $status->last_evaluated_at->diffForHumans() }}
                    </p>
                @endif
            </div>
        </div>
        @if($status->evaluation_notes)
            <div class="mt-3 bg-gray-50 rounded-lg px-3 py-2.5 text-sm text-gray-700 leading-relaxed">
                {{ $status->evaluation_notes }}
            </div>
        @endif
    </div>
    <div class="px-5 pb-4">
        <a href="{{ route('companies.show', $company) }}"
           class="text-sm text-brand-600 hover:text-brand-700 font-medium transition">
            View {{ $company->name }} →
        </a>
    </div>
</div>
