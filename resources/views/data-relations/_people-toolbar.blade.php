{{-- Search + Unlinked/Linked toggle toolbar --}}
<div class="flex flex-col gap-2 md:flex-row md:items-center md:gap-3 mb-4">
    <form method="GET" action="" class="flex gap-2 items-center min-w-0">
        <input type="hidden" name="tab" value="{{ request('tab', 'people') }}">
        <input type="hidden" name="view" value="{{ request('view', 'unlinked') }}">
        <input type="text" name="q" value="{{ request('q', '') }}"
               placeholder="Search…"
               class="input flex-1 min-w-0 md:w-52 md:flex-none py-1.5 text-sm">
        <button type="submit" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded-lg transition shrink-0">Search</button>
        @if(request('q', ''))
            <a href="{{ request()->fullUrlWithQuery(['q' => '', 'page' => null]) }}" class="text-xs text-gray-400 hover:text-gray-600 shrink-0">clear</a>
        @endif
    </form>

    <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm shrink-0 self-start md:self-auto">
        <a href="{{ request()->fullUrlWithQuery(['view' => 'unlinked', 'page' => null]) }}"
           class="px-3 py-1.5 font-medium transition
                  {{ request('view', 'unlinked') === 'unlinked' ? 'bg-amber-50 text-amber-700 border-r border-gray-200' : 'bg-white text-gray-500 hover:bg-gray-50 border-r border-gray-200' }}">
            Unlinked <span class="ml-1 font-bold">{{ $unlinkedCount }}</span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['view' => 'linked', 'page' => null]) }}"
           class="px-3 py-1.5 font-medium transition
                  {{ request('view', 'unlinked') === 'linked' ? 'bg-green-50 text-green-700' : 'bg-white text-gray-500 hover:bg-gray-50' }}">
            Linked <span class="ml-1 font-bold">{{ $linkedCount }}</span>
        </a>
    </div>
</div>
