@extends('layouts.app')
@section('title', 'Filtering — Data Relations')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Filtering</h1>
        <p class="text-xs text-gray-400 mt-0.5">Block unwanted email addresses, domains, and companies from appearing in your data. Add filters here to suppress noise from automated senders, internal tools, and known spam sources.</p>
    </div>
</div>

{{-- Tabs --}}
<div class="tabs-bar flex gap-0 border-b border-gray-200 mb-5">
    @foreach([
        'domains'  => ['label' => 'Domains',  'count' => count($filterDomains)],
        'emails'   => ['label' => 'Emails',   'count' => count($filterEmails)],
        'subjects' => ['label' => 'Subjects', 'count' => count($filterSubjects)],
        'contacts' => ['label' => 'Contacts', 'count' => $filterContacts->count()],
    ] as $tab => $cfg)
        <a href="{{ request()->fullUrlWithQuery(['tab' => $tab]) }}"
           class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition whitespace-nowrap shrink-0
                  {{ $activeTab === $tab
                     ? 'border-brand-600 text-brand-700'
                     : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            {{ $cfg['label'] }}
            @if($cfg['count'] > 0)
                <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-xs
                             {{ $activeTab === $tab ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $cfg['count'] }}
                </span>
            @endif
        </a>
    @endforeach
</div>

{{-- ── TAB: FILTER DOMAINS ── --}}
@if($activeTab === 'domains')
<div class="max-w-xl">
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Filter Domains</h2>
            <p class="text-xs text-gray-400 mt-0.5">
                Conversations or contacts from these domains will be excluded from views.
            </p>
        </div>
        <div class="px-5 py-4" x-data="filterTags({{ Js::from($filterDomains) }})">
            <form x-ref="form" action="{{ route('filtering.domains.save') }}" method="POST">
                @csrf
                <input type="hidden" name="domains" :value="tags.join('\n')">
                <div class="w-full min-h-[44px] bg-white border border-gray-200 rounded-lg px-2 py-1.5
                            flex flex-wrap gap-1.5 focus-within:border-brand-400 focus-within:ring-2
                            focus-within:ring-brand-100 cursor-text transition"
                     @click="$refs.tagInput.focus()">
                    <template x-for="tag in tags" :key="tag">
                        <span class="inline-flex items-center gap-1 bg-brand-100 text-brand-800
                                     text-xs font-mono px-2 py-0.5 rounded-full max-w-[220px]">
                            <span class="truncate" x-text="tag"></span>
                            <button type="button" @click.stop="remove(tag)"
                                    class="text-brand-400 hover:text-brand-700 shrink-0 leading-none">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                            </button>
                        </span>
                    </template>
                    <input x-ref="tagInput" type="text" x-model="input" @keydown="onKey"
                           placeholder="example.com — Enter or , to add"
                           class="flex-1 min-w-[160px] text-xs text-gray-700 font-mono outline-none
                                  border-none bg-transparent py-0.5 placeholder-gray-300">
                </div>
            </form>
            <p class="text-xs text-gray-400 mt-1.5">Press <kbd class="px-1 py-0.5 text-xs bg-gray-100 border border-gray-200 rounded">Enter</kbd> or <kbd class="px-1 py-0.5 text-xs bg-gray-100 border border-gray-200 rounded">,</kbd> to add. Click × to remove.</p>
        </div>
    </div>
</div>
@endif

{{-- ── TAB: FILTER EMAILS ── --}}
@if($activeTab === 'emails')
<div class="max-w-xl">
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Filter Emails</h2>
            <p class="text-xs text-gray-400 mt-0.5">
                Specific email addresses to exclude from views.
            </p>
        </div>
        <div class="px-5 py-4" x-data="filterTags({{ Js::from($filterEmails) }})">
            <form x-ref="form" action="{{ route('filtering.emails.save') }}" method="POST">
                @csrf
                <input type="hidden" name="emails" :value="tags.join('\n')">
                <div class="w-full min-h-[44px] bg-white border border-gray-200 rounded-lg px-2 py-1.5
                            flex flex-wrap gap-1.5 focus-within:border-brand-400 focus-within:ring-2
                            focus-within:ring-brand-100 cursor-text transition"
                     @click="$refs.tagInput.focus()">
                    <template x-for="tag in tags" :key="tag">
                        <span class="inline-flex items-center gap-1 bg-brand-100 text-brand-800
                                     text-xs font-mono px-2 py-0.5 rounded-full max-w-[220px]">
                            <span class="truncate" x-text="tag"></span>
                            <button type="button" @click.stop="remove(tag)"
                                    class="text-brand-400 hover:text-brand-700 shrink-0 leading-none">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                            </button>
                        </span>
                    </template>
                    <input x-ref="tagInput" type="text" x-model="input" @keydown="onKey"
                           placeholder="noreply@example.com — Enter or , to add"
                           class="flex-1 min-w-[160px] text-xs text-gray-700 font-mono outline-none
                                  border-none bg-transparent py-0.5 placeholder-gray-300">
                </div>
            </form>
            <p class="text-xs text-gray-400 mt-1.5">Press <kbd class="px-1 py-0.5 text-xs bg-gray-100 border border-gray-200 rounded">Enter</kbd> or <kbd class="px-1 py-0.5 text-xs bg-gray-100 border border-gray-200 rounded">,</kbd> to add. Click × to remove.</p>
        </div>
    </div>
</div>
@endif

{{-- ── TAB: FILTER SUBJECTS ── --}}
@if($activeTab === 'subjects')
<div class="max-w-xl">
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Filter Subjects</h2>
            <p class="text-xs text-gray-400 mt-0.5">
                Conversations whose subject/title matches these entries will be excluded from views.
                Exact match.
            </p>
        </div>
        <div class="px-5 py-4" x-data="filterTags({{ Js::from($filterSubjects) }}, false)">
            <form x-ref="form" action="{{ route('filtering.subjects.save') }}" method="POST">
                @csrf
                <input type="hidden" name="subjects" :value="tags.join('\n')">
                <div class="w-full min-h-[44px] bg-white border border-gray-200 rounded-lg px-2 py-1.5
                            flex flex-wrap gap-1.5 focus-within:border-brand-400 focus-within:ring-2
                            focus-within:ring-brand-100 cursor-text transition"
                     @click="$refs.tagInput.focus()">
                    <template x-for="tag in tags" :key="tag">
                        <span class="inline-flex items-center gap-1 bg-brand-100 text-brand-800
                                     text-xs px-2 py-0.5 rounded-full max-w-[300px]">
                            <span class="truncate" x-text="tag"></span>
                            <button type="button" @click.stop="remove(tag)"
                                    class="text-brand-400 hover:text-brand-700 shrink-0 leading-none">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                            </button>
                        </span>
                    </template>
                    <input x-ref="tagInput" type="text" x-model="input" @keydown="onKey"
                           placeholder="Re: Your invoice — Enter to add"
                           class="flex-1 min-w-[160px] text-xs text-gray-700 outline-none
                                  border-none bg-transparent py-0.5 placeholder-gray-300">
                </div>
            </form>
            <p class="text-xs text-gray-400 mt-1.5">Press <kbd class="px-1 py-0.5 text-xs bg-gray-100 border border-gray-200 rounded">Enter</kbd> to add (no comma splitting — subjects may contain commas). Click × to remove.</p>
        </div>
    </div>
</div>
@endif

{{-- ── TAB: FILTERED CONTACTS ── --}}
@if($activeTab === 'contacts')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Filtered Contacts</h2>
            <p class="text-xs text-gray-400 mt-0.5">
                Contacts whose activity will be excluded from views. Add from the People list or contact details.
            </p>
        </div>

        @if($filterContacts->isNotEmpty())
            <div class="divide-y divide-gray-100">
                @foreach($filterContacts as $person)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 flex items-center
                                    justify-center text-xs font-bold shrink-0">
                            {{ strtoupper(substr($person->first_name,0,1)) }}{{ strtoupper(substr($person->last_name??'',0,1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800">{{ $person->full_name }}</p>
                        </div>
                        <a href="{{ route('people.show', $person) }}"
                           class="text-xs text-brand-600 hover:underline shrink-0">View →</a>
                        <form action="{{ route('filtering.contacts.remove', $person) }}" method="POST"
                              onsubmit="return confirm('Remove {{ $person->full_name }} from filtered contacts?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-400 hover:text-red-600 font-medium">✕ Remove</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @else
            <div class="px-6 py-10 text-center">
                <p class="text-gray-400 text-sm italic">No filtered contacts yet.</p>
                <p class="text-gray-300 text-xs mt-1">Add contacts from the People list or their detail page.</p>
            </div>
        @endif
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
function filterTags(initial, splitComma = true) {
    return {
        tags: initial,
        input: '',
        add() {
            const sep = splitComma ? /[,\n]+/ : /\n+/;
            const vals = this.input.split(sep).map(s => s.trim()).filter(Boolean);
            vals.forEach(v => { if (!this.tags.includes(v)) this.tags.push(v); });
            this.input = '';
            if (vals.length) this.$nextTick(() => this.$refs.form.submit());
        },
        remove(tag) {
            this.tags = this.tags.filter(t => t !== tag);
            this.$nextTick(() => this.$refs.form.submit());
        },
        onKey(e) {
            if (e.key === 'Enter' || (splitComma && e.key === ',')) {
                e.preventDefault();
                this.add();
            } else if (e.key === 'Backspace' && !this.input && this.tags.length) {
                this.tags.pop();
                this.$nextTick(() => this.$refs.form.submit());
            }
        }
    };
}
</script>
@endpush
