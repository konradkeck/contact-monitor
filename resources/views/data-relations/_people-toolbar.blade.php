{{-- Search + Unlinked/Linked toggle toolbar --}}
<div class="flex items-center gap-3 mb-4">
    <form method="GET" action="" class="flex-1 flex gap-2 items-center">
        <input type="hidden" name="tab" value="{{ request('tab', 'people') }}">
        <input type="hidden" name="view" value="{{ request('view', 'unlinked') }}">
        <input type="text" name="q" value="{{ request('q', '') }}"
               placeholder="Search…"
               class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-72 focus:outline-none focus:border-brand-400">
        <button type="submit" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded-lg transition">Search</button>
        @if(request('q', ''))
            <a href="{{ request()->fullUrlWithQuery(['q' => '', 'page' => null]) }}" class="text-xs text-gray-400 hover:text-gray-600">clear</a>
        @endif
    </form>

    <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
        <a href="{{ request()->fullUrlWithQuery(['view' => 'unlinked', 'page' => null]) }}"
           class="px-4 py-1.5 font-medium transition
                  {{ request('view', 'unlinked') === 'unlinked' ? 'bg-amber-50 text-amber-700 border-r border-gray-200' : 'bg-white text-gray-500 hover:bg-gray-50 border-r border-gray-200' }}">
            Unlinked <span class="ml-1 font-bold">{{ $unlinkedCount }}</span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['view' => 'linked', 'page' => null]) }}"
           class="px-4 py-1.5 font-medium transition
                  {{ request('view', 'unlinked') === 'linked' ? 'bg-green-50 text-green-700' : 'bg-white text-gray-500 hover:bg-gray-50' }}">
            Linked <span class="ml-1 font-bold">{{ $linkedCount }}</span>
        </a>
    </div>
</div>
