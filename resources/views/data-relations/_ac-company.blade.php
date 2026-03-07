<form action="{{ $action }}" method="POST" class="flex gap-2 items-center">
    @csrf
    <div class="relative flex-1" data-autocomplete="company" data-search-url="{{ route('companies.search') }}">
        <input type="text" placeholder="{{ $placeholder ?? 'Search company…' }}" autocomplete="off"
               class="ac-input text-xs border border-gray-300 rounded px-2 py-1 w-full focus:outline-none focus:border-brand-400">
        <input type="hidden" name="company_id" class="ac-value">
        <ul class="ac-results hidden absolute z-10 w-full bg-white border border-gray-200 rounded shadow-lg mt-0.5 max-h-48 overflow-y-auto text-xs"></ul>
    </div>
    <button type="submit" disabled
            class="ac-submit px-2 py-1 bg-brand-600 text-white text-xs rounded hover:bg-brand-700 transition whitespace-nowrap disabled:opacity-40 disabled:cursor-not-allowed">Link</button>
</form>
