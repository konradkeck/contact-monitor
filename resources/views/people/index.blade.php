@extends('layouts.app')
@section('title', 'People')

@section('content')

<div class="page-header">
    <span class="page-title">People</span>
    <div class="flex items-center gap-2">
        @if($showFiltered)
            <a href="{{ request()->fullUrlWithQuery(['show_filtered' => null]) }}"
               class="btn btn-danger btn-sm">
                ← All People
            </a>
        @else
            <a href="{{ request()->fullUrlWithQuery(['show_filtered' => 1]) }}"
               class="btn btn-secondary btn-sm">
                Filtered
                @if($filteredCount > 0)
                    <span class="ml-1 inline-flex items-center justify-center bg-brand-600 text-white text-xs font-bold rounded-full w-4 h-4 leading-none">
                        {{ $filteredCount }}
                    </span>
                @else
                    <span class="ml-1 text-xs text-gray-400">(0)</span>
                @endif
            </a>
        @endif
        @can('data_write')
        <a href="{{ route('people.create') }}" class="btn btn-primary">+ New Person</a>
        @endcan
    </div>
</div>

<div class="flex gap-0 border-b border-gray-200 mb-5">
    <a href="{{ request()->fullUrlWithQuery(['tab' => 'clients', 'page' => null]) }}"
       class="flex items-center gap-1.5 px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition
              {{ $tab === 'clients' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
        Clients
        <span class="px-1.5 py-0.5 rounded-full text-xs {{ $tab === 'clients' ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-500' }}">
            {{ number_format($tabCounts['clients']) }}
        </span>
    </a>
    <a href="{{ request()->fullUrlWithQuery(['tab' => 'our_org', 'page' => null]) }}"
       class="flex items-center gap-1.5 px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition
              {{ $tab === 'our_org' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
        Our Organization
        <span class="px-1.5 py-0.5 rounded-full text-xs {{ $tab === 'our_org' ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-500' }}">
            {{ number_format($tabCounts['our_org']) }}
        </span>
    </a>
</div>

<form method="GET" class="mb-4">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="hidden" name="sort" value="{{ $sort }}">
    <input type="hidden" name="dir"  value="{{ $dir }}">
    <div class="flex gap-2 max-w-sm">
        <input type="text" name="q" value="{{ $search }}" placeholder="Search by name or identity…" class="input" style="max-width:240px">
        <button type="submit" class="btn btn-secondary">Search</button>
        @if($search)
            <a href="{{ route('people.index', ['sort' => $sort, 'dir' => $dir]) }}" class="btn btn-muted">Clear</a>
        @endif
    </div>
</form>

<form id="people-bulk-form" method="POST" action="{{ route('filtering.contacts.bulk-add') }}">
@csrf
<div class="card overflow-hidden">
    {{-- Bulk action bar --}}
    <div id="people-bulk-bar" class="hidden items-center gap-3 px-4 py-2 border-b bulk-bar">
        <span id="people-bulk-count" class="text-sm font-medium bulk-bar-text"></span>
        @can('data_write')
        @if($tab === 'clients')
        <button type="button" onclick="peopleOpenFilterModal()" class="btn btn-danger btn-sm">Filter…</button>
        <button type="button" onclick="peopleOpenAssignCompanyModal()" class="btn btn-secondary btn-sm">Assign Company…</button>
        <button type="button" onclick="peopleBulkMarkOurOrg()" class="btn btn-secondary btn-sm">Mark as our company</button>
        @endif
        @endcan
        <button type="button" onclick="peopleClearSelection()" class="text-xs text-gray-500 hover:text-gray-700">Clear</button>
    </div>
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th class="px-3 py-2.5 w-8">
                    <input type="checkbox" id="people-select-all" class="rounded border-gray-300 cursor-pointer"
                           onchange="peopleToggleAll(this)">
                </th>
                <th class="px-4 py-2.5 text-left w-56">
                    <a href="{{ $sortLink('first_name') }}" class="flex items-center justify-between gap-2 hover:text-gray-700">
                        Name <span class="shrink-0 opacity-50">{{ $sortIcon('first_name') }}</span>
                    </a>
                </th>
                <th class="px-4 py-2.5 text-left w-36">Communication</th>
                <th class="px-4 py-2.5 text-left">Companies</th>
                <th class="px-4 py-2.5 text-left w-[28rem]">
                    <a href="{{ $sortLink('updated_at') }}" class="flex items-center justify-between gap-2 hover:text-gray-700">
                        Last Contact <span class="shrink-0 opacity-50">{{ $sortIcon('updated_at') }}</span>
                    </a>
                </th>
                <th class="px-4 py-2.5 w-12"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($people as $person)
                <tr class="tbl-row">
                    <td class="px-3 py-3">
                        <input type="checkbox" name="ids[]" value="{{ $person->id }}"
                               class="people-row-check rounded border-gray-300 cursor-pointer"
                               onchange="peopleUpdateBulkBar()">
                    </td>

                    {{-- Name + note icon (always visible) --}}
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <a href="{{ route('people.show', $person) }}" class="shrink-0">
                                <x-person-avatar :person="$person" size="8" class="border border-gray-100 bg-gray-100" />
                            </a>
                            <a href="{{ route('people.show', $person) }}" class="font-medium link truncate">
                                {{ $person->full_name }}
                            </a>
                            @if($showFiltered && isset($filteredReasons[$person->id]))
                                <span title="{{ $filteredReasons[$person->id] }}"
                                      class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-red-100 text-red-600 text-[10px] font-bold shrink-0 cursor-default leading-none">
                                    i
                                </span>
                            @endif
                            <div class="flex-1"></div>
                            <x-notes-popup :notes="$person->notes" linkable-type="person" :linkable-id="$person->id" :entity-name="$person->full_name" />
                        </div>
                    </td>

                    {{-- Communication icons --}}
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1">
                            @foreach($person->identities->take(6) as $identity)
                                @if(isset(\App\View\Components\IdentityIcon::getMap()[$identity->type]))
                                    <x-identity-icon :type="$identity->type" :value="$identity->value" />
                                @endif
                            @endforeach
                            @if($person->identities_count > 6)
                                <span class="text-xs text-gray-400 ml-0.5">+{{ $person->identities_count - 6 }}</span>
                            @endif
                        </div>
                    </td>

                    {{-- Companies (clickable) --}}
                    <td class="px-4 py-3 text-xs">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            @foreach($person->companies as $company)
                                <a href="{{ route('companies.show', $company) }}"
                                   class="link whitespace-nowrap">{{ $company->name }}</a>
                            @endforeach
                            @if($person->companies->isEmpty())
                                <span class="text-gray-300">—</span>
                            @endif
                            @can('data_write')
                            <button type="button"
                                    onclick="peopleOpenAssignCompanyModal([{{ $person->id }}])"
                                    class="text-[10px] text-gray-400 hover:text-brand-600 transition border border-gray-200 hover:border-brand-300 rounded px-1 py-0 leading-4 cursor-pointer">
                                Assign
                            </button>
                            @endcan
                        </div>
                    </td>

                    {{-- Last Contact: latest conversation message involving this person --}}
                    <td class="px-4 py-3">
                        @if($person->last_conv)
                            @if($person->last_conv->last_conv_id)
                                <button type="button"
                                        onclick="openActivityModal(this)"
                                        data-modal-src="{{ route('conversations.modal', ['conversation' => $person->last_conv->last_conv_id]) . (!empty($person->last_conv->activity_date) ? '?date=' . $person->last_conv->activity_date : '') }}"
                                        class="flex items-center gap-1.5 text-left hover:opacity-75 transition w-full">
                            @else
                                <div class="flex items-center gap-1.5">
                            @endif
                                <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium {{ $channelBadge[$person->last_conv->channel_type ?? ''] ?? 'bg-slate-100 text-slate-700' }} shrink-0">
                                    {{ ucfirst($person->last_conv->channel_type) }}
                                </span>
                                <span class="text-xs text-gray-500 truncate"
                                      title="{{ $person->last_conv->conv_subject }}">{{ $person->last_conv->conv_subject ?: '—' }}</span>
                                <span class="text-xs text-gray-300 shrink-0"
                                      title="{{ \Carbon\Carbon::parse($person->last_conv->occurred_at)->format('D, j M Y H:i') }}">
                                    {{ \Carbon\Carbon::parse($person->last_conv->occurred_at)->diffForHumans() }}
                                </span>
                            @if($person->last_conv->last_conv_id)
                                </button>
                            @else
                                </div>
                            @endif
                        @else
                            <span class="text-xs text-gray-300">No contact</span>
                        @endif
                    </td>

                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            @can('data_write')
                            @if($tab === 'clients')
                            <button type="button"
                                    onclick="peopleOpenFilterModal([{{ $person->id }}])"
                                    class="btn btn-sm btn-danger"
                                    title="Filter">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
                                Filter
                            </button>
                            @if($person->is_our_org)
                                <span class="btn btn-sm btn-org">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M4 21V7l8-4 8 4v14M9 21v-6h6v6"/></svg>
                                    Our Org
                                </span>
                            @else
                                <button type="button"
                                        onclick="peopleMarkOurOrg({{ $person->id }}, this)"
                                        class="btn btn-sm btn-muted"
                                        title="Mark as Our Organization">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M4 21V7l8-4 8 4v14M9 21v-6h6v6"/></svg>
                                    Our Org
                                </button>
                            @endif
                            @endif
                            <a href="{{ route('people.edit', $person) }}" class="row-action">Edit</a>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">No people found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($people->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $people->links() }}
        </div>
    @endif
</div>
</form>

<script>
function peopleUpdateBulkBar() {
    const checked = document.querySelectorAll('.people-row-check:checked');
    const bar = document.getElementById('people-bulk-bar');
    const count = document.getElementById('people-bulk-count');
    if (checked.length > 0) {
        bar.classList.remove('hidden');
        bar.classList.add('flex');
        count.textContent = checked.length + ' selected';
    } else {
        bar.classList.add('hidden');
        bar.classList.remove('flex');
    }
    document.getElementById('people-select-all').indeterminate =
        checked.length > 0 && checked.length < document.querySelectorAll('.people-row-check').length;
    document.getElementById('people-select-all').checked =
        checked.length === document.querySelectorAll('.people-row-check').length;
}
function peopleToggleAll(cb) {
    document.querySelectorAll('.people-row-check').forEach(c => c.checked = cb.checked);
    peopleUpdateBulkBar();
}
function peopleClearSelection() {
    document.querySelectorAll('.people-row-check, #people-select-all').forEach(c => c.checked = false);
    peopleUpdateBulkBar();
}
function peopleOpenFilterModal(ids) {
    if (!ids) ids = [...document.querySelectorAll('.people-row-check:checked')].map(c => c.value);
    if (!ids.length) return;
    const qs = ids.map(id => 'ids[]=' + id).join('&');
    openActivityModal({ dataset: { modalSrc: '{{ route('people.filter-modal') }}?' + qs } });
}
function peopleOpenAssignCompanyModal(ids) {
    if (!ids) ids = [...document.querySelectorAll('.people-row-check:checked')].map(c => c.value);
    if (!ids.length) return;
    const qs = ids.map(id => 'ids[]=' + id).join('&');
    openActivityModal({ dataset: { modalSrc: '{{ route('people.assign-company-modal') }}?' + qs } });
}
function peopleBulkMarkOurOrg() {
    const ids = [...document.querySelectorAll('.people-row-check:checked')].map(c => c.value);
    if (!ids.length) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('{{ route('people.bulk-mark-our-org') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ ids }),
    }).then(r => r.json()).then(d => { if (d.ok) { peopleClearSelection(); } })
    .catch(() => {});
}
function peopleMarkOurOrg(id, btn) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    btn.disabled = true;
    fetch(`/people/${id}/mark-our-org`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({}),
    }).then(r => r.json()).then(d => { if (d.ok) btn.style.color = '#3b82f6'; else btn.disabled = false; })
    .catch(() => { btn.disabled = false; });
}
</script>
@endsection
