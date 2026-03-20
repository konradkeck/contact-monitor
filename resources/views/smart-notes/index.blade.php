@extends('layouts.app')
@section('title', 'Smart Notes')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title flex items-center gap-2">
            <img src="/ai-icon.svg" class="w-5 h-5" alt="">
            Smart Notes
        </h1>
    </div>
</div>

@if(!$smartNotesEnabled)
<div class="alert-warning mb-5 flex items-center gap-3">
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12" stroke-width="1.75" stroke-linecap="round"/><circle cx="12" cy="16" r="0.75" fill="currentColor" stroke="none"/></svg>
    <span>Smart Notes is disabled. <a href="{{ route('smart-notes.config.index') }}" class="font-semibold underline hover:no-underline">Enable it in Configuration → Smart Notes</a> to start capturing notes.</span>
</div>
@endif

{{-- Tabs --}}
<div class="flex gap-0 border-b border-gray-200 mb-5">
    <a href="{{ route('smart-notes.index', ['tab' => 'unrecognized']) }}"
       class="px-4 py-2.5 text-sm font-medium border-b-2 transition -mb-px flex items-center gap-1.5
              {{ $tab === 'unrecognized' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
        Unrecognized
        @if($unrecognizedCount > 0)
            <span class="text-xs bg-red-100 text-red-700 border border-red-200 rounded-full px-1.5 py-0.5 font-medium">{{ $unrecognizedCount }}</span>
        @endif
    </a>
    <a href="{{ route('smart-notes.index', ['tab' => 'recognized']) }}"
       class="px-4 py-2.5 text-sm font-medium border-b-2 transition -mb-px flex items-center gap-1.5
              {{ $tab === 'recognized' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
        Recognized
        @if($recognizedCount > 0)
            <span class="text-xs bg-gray-100 text-gray-500 border border-gray-200 rounded-full px-1.5 py-0.5 font-medium">{{ $recognizedCount }}</span>
        @endif
    </a>
</div>

<div class="card-xl-overflow">
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th class="px-4 py-2.5 text-left">Content</th>
                <th class="px-4 py-2.5 text-left">Source</th>
                <th class="px-4 py-2.5 text-left">Sender</th>
                <th class="px-4 py-2.5 text-left">Filter</th>
                <th class="px-4 py-2.5 text-left">Date</th>
                <th class="px-4 py-2.5 text-right w-32"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($notes as $note)
            <tr class="tbl-row">
                <td class="px-4 py-3 max-w-sm">
                    <p class="line-clamp-2 text-gray-800 text-xs leading-relaxed">{{ $note->content }}</p>
                    @if($note->as_internal_note)
                        <span class="badge badge-blue mt-1">Internal</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <span class="badge badge-gray">{{ $note->sourceLabel() }}</span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-600">
                    @if($note->sender_name)
                        <span class="font-medium">{{ $note->sender_name }}</span>
                        @if($note->sender_value)
                            <br><span class="text-gray-400 font-mono">{{ $note->sender_value }}</span>
                        @endif
                    @elseif($note->sender_value)
                        <span class="font-mono text-gray-500">{{ $note->sender_value }}</span>
                    @else
                        <span class="text-gray-300">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-xs text-gray-500">
                    {{ $note->filter?->typeLabel() ?? '—' }}
                </td>
                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                    {{ $note->occurred_at?->format('d M Y') ?? '—' }}
                </td>
                <td class="px-4 py-3">
                    <div class="row-actions-desktop items-center justify-end gap-1.5">
                        @if($tab === 'unrecognized')
                            <a href="{{ route('smart-notes.recognize', $note) }}" class="row-action text-xs font-medium text-brand-600 hover:text-brand-700">Recognize</a>
                        @else
                            <form method="POST" action="{{ route('smart-notes.unrecognize', $note) }}">
                                @csrf
                                <button type="submit" class="row-action text-xs">Unrecognize</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('smart-notes.destroy', $note) }}"
                              onsubmit="return confirm('Delete this Smart Note?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="row-action-danger text-xs">Delete</button>
                        </form>
                    </div>
                    <div class="row-actions-mobile relative" x-data="{open:false}" @click.outside="open=false">
                        <button @click="open=!open" class="text-gray-400 hover:text-gray-600 px-2 py-1 rounded text-base leading-none">···</button>
                        <div x-show="open" x-cloak class="absolute right-0 top-full mt-1 w-36 bg-white border border-gray-200 rounded-xl shadow-lg py-1 z-10 text-xs">
                            @if($tab === 'unrecognized')
                                <a href="{{ route('smart-notes.recognize', $note) }}" class="block px-3 py-2 text-brand-600 hover:bg-brand-50">Recognize</a>
                            @else
                                <form method="POST" action="{{ route('smart-notes.unrecognize', $note) }}">
                                    @csrf
                                    <button class="block w-full text-left px-3 py-2 text-gray-700 hover:bg-gray-50">Unrecognize</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('smart-notes.destroy', $note) }}"
                                  onsubmit="return confirm('Delete?')">
                                @csrf
                                @method('DELETE')
                                <button class="block w-full text-left px-3 py-2 text-red-600 hover:bg-red-50">Delete</button>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-10 text-center empty-state italic">
                    @if($tab === 'unrecognized')
                        No unrecognized Smart Notes. {{ $smartNotesEnabled ? 'Filters will capture new messages automatically.' : '' }}
                    @else
                        No recognized Smart Notes yet.
                    @endif
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if($notes->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">{{ $notes->appends(request()->query())->links() }}</div>
    @endif
</div>

@endsection
