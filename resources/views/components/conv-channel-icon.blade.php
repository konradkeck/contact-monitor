<a href="{{ route('conversations.index', ['company_id' => $companyId, 'channel_type' => $channelType]) }}"
   title="{{ $channelType }}"
   class="inline-flex items-center justify-center w-6 h-6 rounded shrink-0 hover:opacity-80 transition {{ $cls }}"
   @if($bStyle) style="{{ $bStyle }}" @endif>
    @if($iconDef['stroke'])
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="{{ $iconDef['d'] }}"/>
        </svg>
    @else
        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
            <path d="{{ $iconDef['d'] }}"/>
        </svg>
    @endif
</a>
