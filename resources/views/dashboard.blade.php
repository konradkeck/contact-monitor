@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

<div class="flex gap-6 items-start">

{{-- ── Main content ─────────────────────────────────────────────────────── --}}
<div class="flex-1 min-w-0">

    {{-- Page header + date range picker --}}
    <div class="page-header">
        <h1 class="page-title">Dashboard</h1>
        <form id="dash-date-form" method="GET">
            <div class="drp-wrap" id="dash-date-range-wrap">
                <input id="dash-date-range" type="text" placeholder="Date range…"
                       class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-600 bg-white
                              focus:outline-none cursor-pointer w-44">
            </div>
            <input type="hidden" id="dash-from" name="from" value="{{ $from->format('Y-m-d') }}">
            <input type="hidden" id="dash-to"   name="to"   value="{{ $to->format('Y-m-d') }}">
        </form>
    </div>

    {{-- Stats cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <a href="{{ route('conversations.index') }}" class="card p-5 hover:shadow-sm transition">
            <p class="text-3xl font-bold text-gray-900">{{ number_format($conversationsCount) }}</p>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mt-1">Conversations</p>
        </a>
        <a href="{{ route('companies.index') }}" class="card p-5 hover:shadow-sm transition">
            <p class="text-3xl font-bold text-gray-900">{{ number_format($newCompaniesCount) }}</p>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mt-1">New Companies</p>
        </a>
        <a href="{{ route('people.index') }}" class="card p-5 hover:shadow-sm transition">
            <p class="text-3xl font-bold text-gray-900">{{ number_format($newPeopleCount) }}</p>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mt-1">New People</p>
        </a>
    </div>

    {{-- Two summary tables --}}
    <div class="grid grid-cols-2 gap-6">

        {{-- Most Active People --}}
        <div class="card overflow-hidden">
            <div class="section-header">
                <span class="section-header-title">Most Active People</span>
                <span class="text-xs text-gray-400">by conversations</span>
            </div>
            <table class="w-full text-sm">
                <thead class="tbl-header">
                    <tr>
                        <th scope="col" class="px-4 py-2 text-left">Person</th>
                        <th scope="col" class="px-4 py-2 text-right w-24">Conversations</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activePeople as $person)
                        <tr class="tbl-row">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <x-person-avatar :person="$person" size="6" class="border border-gray-100 bg-gray-100 shrink-0" />
                                    <a href="{{ route('people.show', $person) }}" class="link truncate">{{ $person->full_name }}</a>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 text-right font-semibold text-gray-700">{{ $person->conv_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-center text-sm text-gray-400">No activity in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Most Active Team Members --}}
        <div class="card overflow-hidden">
            <div class="section-header">
                <span class="section-header-title">Most Active Team Members</span>
                <span class="text-xs text-gray-400">by conversations</span>
            </div>
            <table class="w-full text-sm">
                <thead class="tbl-header">
                    <tr>
                        <th scope="col" class="px-4 py-2 text-left">Member</th>
                        <th scope="col" class="px-4 py-2 text-right w-24">Conversations</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeTeam as $person)
                        <tr class="tbl-row">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <x-person-avatar :person="$person" size="6" class="border border-gray-100 bg-gray-100 shrink-0" />
                                    <a href="{{ route('people.show', $person) }}" class="link truncate">{{ $person->full_name }}</a>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 text-right font-semibold text-gray-700">{{ $person->conv_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-center text-sm text-gray-400">No activity in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>

{{-- ── Right sidebar: Recent Notes ──────────────────────────────────────── --}}
<div class="w-72 shrink-0">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">Recent Notes</h3>

    @if($recentNotes->isEmpty())
        <p class="text-sm text-gray-400 italic">No notes yet.</p>
    @else
        <div class="flex flex-col gap-2.5">
            @foreach($recentNotes as $note)
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 shadow-sm">
                    <div class="flex items-center justify-between gap-1 mb-1.5">
                        @if($note->entity_url && $note->entity_name)
                            <a href="{{ $note->entity_url }}" class="text-xs font-semibold link truncate" title="{{ $note->entity_name }}">
                                {{ $note->entity_name }}
                            </a>
                        @else
                            <span class="text-xs text-gray-400 italic">—</span>
                        @endif
                        <span class="text-[10px] text-gray-400 shrink-0">{{ $note->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="text-xs text-gray-700 leading-relaxed line-clamp-4">{{ $note->content }}</p>
                    @if($note->user)
                        <p class="text-[10px] text-gray-400 mt-1.5">by {{ $note->user->name }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

</div>

<script>
(function () {
    function localDateStr(d) {
        return d.getFullYear() + '-' +
               String(d.getMonth() + 1).padStart(2, '0') + '-' +
               String(d.getDate()).padStart(2, '0');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var fromInput = document.getElementById('dash-from');
        var toInput   = document.getElementById('dash-to');
        var form      = document.getElementById('dash-date-form');

        drp.init('dash-date-range', function (from, to) {
            if (!from) return;
            fromInput.value = from;
            toInput.value   = to;
            form.submit();
        }, { defaultFrom: fromInput.value, defaultTo: toInput.value });
    });
}());
</script>

@endsection
