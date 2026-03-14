@if($href)
    <a href="{{ $href }}" target="_blank" rel="noopener"
       title="{{ $iconTitle }}: {{ $value }}"
       class="inline-flex items-center justify-center w-5 h-5 rounded text-xs shrink-0 {{ $cls }}"
       @if($style) style="{{ $style }}" @endif>
        <svg class="w-3 h-3" @if($stroke) fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" @else fill="currentColor" @endif viewBox="0 0 24 24">
            <path d="{{ $d }}"/>
        </svg>
    </a>
@else
    <span title="{{ $iconTitle }}: {{ $value }}"
          class="inline-flex items-center justify-center w-5 h-5 rounded text-xs shrink-0 {{ $cls }}"
          @if($style) style="{{ $style }}" @endif>
        <svg class="w-3 h-3" @if($stroke) fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" @else fill="currentColor" @endif viewBox="0 0 24 24">
            <path d="{{ $d }}"/>
        </svg>
    </span>
@endif
