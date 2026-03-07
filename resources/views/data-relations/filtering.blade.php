@extends('layouts.app')
@section('title', 'Filtering — Data Relations')

@section('content')

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('data-relations.index') }}" class="text-gray-400 hover:text-gray-600 text-sm">← Data Relations</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">Filtering</h1>
</div>

@if(session('success'))
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
        {{ session('success') }}
    </div>
@endif

{{-- Tabs --}}
<div class="flex gap-0 border-b border-gray-200 mb-6">
    @foreach([
        'domains'  => ['label' => 'Domains',  'count' => count($filterDomains)],
        'emails'   => ['label' => 'Emails',   'count' => count($filterEmails)],
        'subjects' => ['label' => 'Subjects', 'count' => count($filterSubjects)],
        'contacts' => ['label' => 'Contacts', 'count' => $filterContacts->count()],
    ] as $tab => $cfg)
        <a href="{{ request()->fullUrlWithQuery(['tab' => $tab]) }}"
           class="px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition
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

        @if(!empty($filterDomains))
            <ul class="divide-y divide-gray-50">
                @foreach($filterDomains as $domain)
                    <li class="flex items-center justify-between px-5 py-2.5">
                        <span class="font-mono text-sm text-gray-700">{{ $domain }}</span>
                        <form action="{{ route('filtering.domains.remove') }}" method="POST">
                            @csrf
                            <input type="hidden" name="domain" value="{{ $domain }}">
                            <button class="text-xs text-red-400 hover:text-red-600 font-bold">✕ remove</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="px-5 py-4 text-sm text-gray-400 italic">No filter domains configured.</p>
        @endif

        <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
            <form action="{{ route('filtering.domains.save') }}" method="POST" class="space-y-2">
                @csrf
                <textarea name="domains" rows="3" placeholder="example.com&#10;spam.net"
                          class="w-full text-sm font-mono border border-gray-200 rounded-lg px-3 py-2
                                 placeholder-gray-300 text-gray-700 resize-none focus:outline-none
                                 focus:ring-2 focus:ring-brand-300">{{ implode("\n", $filterDomains) }}</textarea>
                <button class="w-full py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg transition">
                    Save
                </button>
            </form>
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

        @if(!empty($filterEmails))
            <ul class="divide-y divide-gray-50">
                @foreach($filterEmails as $email)
                    <li class="flex items-center justify-between px-5 py-2.5">
                        <span class="font-mono text-sm text-gray-700">{{ $email }}</span>
                        <form action="{{ route('filtering.emails.remove') }}" method="POST">
                            @csrf
                            <input type="hidden" name="email" value="{{ $email }}">
                            <button class="text-xs text-red-400 hover:text-red-600 font-bold">✕ remove</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="px-5 py-4 text-sm text-gray-400 italic">No filter emails configured.</p>
        @endif

        <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
            <form action="{{ route('filtering.emails.save') }}" method="POST" class="space-y-2">
                @csrf
                <textarea name="emails" rows="4" placeholder="noreply@example.com&#10;spam@domain.net"
                          class="w-full text-sm font-mono border border-gray-200 rounded-lg px-3 py-2
                                 placeholder-gray-300 text-gray-700 resize-none focus:outline-none
                                 focus:ring-2 focus:ring-brand-300">{{ implode("\n", $filterEmails) }}</textarea>
                <button class="w-full py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg transition">
                    Save
                </button>
            </form>
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
                One entry per line, exact match.
            </p>
        </div>

        @if(!empty($filterSubjects))
            <ul class="divide-y divide-gray-50">
                @foreach($filterSubjects as $subject)
                    <li class="flex items-center justify-between px-5 py-2.5 gap-3">
                        <span class="text-sm text-gray-700 truncate" title="{{ $subject }}">{{ $subject }}</span>
                        <form action="{{ route('filtering.subjects.remove') }}" method="POST" class="shrink-0">
                            @csrf
                            <input type="hidden" name="subject" value="{{ $subject }}">
                            <button class="text-xs text-red-400 hover:text-red-600 font-bold">✕ remove</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="px-5 py-4 text-sm text-gray-400 italic">No filter subjects configured.</p>
        @endif

        <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
            <form action="{{ route('filtering.subjects.save') }}" method="POST" class="space-y-2">
                @csrf
                <textarea name="subjects" rows="4"
                          placeholder="Re: Your invoice #12345&#10;[SPAM] Free offer"
                          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2
                                 placeholder-gray-300 text-gray-700 resize-none focus:outline-none
                                 focus:ring-2 focus:ring-brand-300">{{ implode("\n", $filterSubjects) }}</textarea>
                <button class="w-full py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg transition">
                    Save
                </button>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ── TAB: FILTER CONTACTS ── --}}
@if($activeTab === 'contacts')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Filter Contacts</h2>
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
                              onsubmit="return confirm('Remove {{ $person->full_name }} from filter contacts?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-400 hover:text-red-600 font-medium">✕ Remove</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @else
            <div class="px-6 py-10 text-center">
                <p class="text-gray-400 text-sm italic">No filter contacts yet.</p>
                <p class="text-gray-300 text-xs mt-1">Add contacts from the People list or their detail page.</p>
            </div>
        @endif
    </div>
</div>
@endif

@endsection
