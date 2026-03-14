<div class="relative w-16 h-16">
    <svg width="64" height="64" viewBox="0 0 64 64" style="transform:rotate(-90deg)">
        <circle cx="32" cy="32" r="{{ $r }}" fill="none" stroke="#e5e7eb" stroke-width="5"/>
        <circle cx="32" cy="32" r="{{ $r }}" fill="none"
                stroke="{{ $scClr }}" stroke-width="5"
                stroke-linecap="round"
                style="stroke-dasharray:{{ number_format($circ,3) }};stroke-dashoffset:{{ number_format($offset,3) }}"/>
    </svg>
    <div class="absolute inset-0 flex items-center justify-center">
        <span class="text-xl font-bold text-gray-900">{{ $score }}</span>
    </div>
</div>
