@extends('layouts.app')
@section('title', 'Activities')

@section('content')
<div class="page-header">
    <span class="page-title">Activities</span>
</div>

{{-- Filters --}}
<form method="GET" class="flex flex-wrap items-center gap-2 mb-4 card px-4 py-3">
    <select name="f_type" class="input" style="width:auto">
        <option value="">All types</option>
        @foreach($types as $t)
            <option value="{{ $t }}" @selected($fType === $t)>{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
        @endforeach
    </select>
    @if($channelTypes->isNotEmpty())
        <select name="f_channel" class="input" style="width:auto">
            <option value="">All channels</option>
            @foreach($channelTypes as $ct)
                <option value="{{ $ct }}" @selected($fChannel === $ct)>{{ ucfirst($ct) }}</option>
            @endforeach
        </select>
    @endif
    <input type="date" name="f_from" value="{{ $fFrom }}" class="input" style="width:auto">
    <span class="text-gray-400">–</span>
    <input type="date" name="f_to" value="{{ $fTo }}" class="input" style="width:auto">
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if($fType || $fChannel || $fFrom || $fTo)
        <a href="{{ route('activities.index') }}" class="btn btn-muted">Clear</a>
    @endif
</form>

{{-- Activity list --}}
<div class="card divide-y divide-gray-100">
    @forelse($activities as $activity)
        @php
            $url      = $activity->targetUrl();
            $meta     = $activity->meta_json ?? [];
            $mainText = $meta['subject'] ?? $meta['text'] ?? $meta['title'] ?? null;
            $secText  = $meta['description'] ?? $meta['body'] ?? null;
            $chType   = $activity->conversationChannelType();
        @endphp
        <div class="flex items-center gap-2.5 px-3 py-2 tbl-row group min-w-0">

            {{-- Colored dot --}}
            <div class="w-2 h-2 rounded-full shrink-0 ring-2 {{ $activity->dotColor() }}"></div>

            {{-- Label badge: channel badge for conversations, colored badge otherwise --}}
            @if($chType)
                <x-channel-badge :type="$chType" class="shrink-0" />
                @if($meta['system_slug'] ?? null)
                    <span class="text-xs text-gray-400 shrink-0">{{ $meta['system_slug'] }}</span>
                @endif
            @else
                <span class="shrink-0 text-xs font-medium px-2 py-0.5 rounded-full border {{ $activity->timelineColor() }}">
                    {{ $activity->timelineLabel() }}
                </span>
            @endif

            {{-- Main text --}}
            @if($mainText)
                <span class="text-sm text-gray-700 truncate">{{ $mainText }}</span>
            @endif

            {{-- Secondary text --}}
            @if($secText)
                <span class="text-xs text-gray-400 truncate">{{ $secText }}</span>
            @endif

            {{-- Spacer --}}
            <div class="flex-1"></div>

            {{-- Company link --}}
            @if($activity->company)
                <a href="{{ route('companies.show', $activity->company) }}"
                   class="text-xs text-brand-600 hover:underline shrink-0 max-w-[130px] truncate">
                    🏢 {{ $activity->company->name }}
                </a>
            @endif

            {{-- Person link --}}
            @if($activity->person)
                <a href="{{ route('people.show', $activity->person) }}"
                   class="text-xs text-brand-600 hover:underline shrink-0 max-w-[130px] truncate">
                    👤 {{ $activity->person->full_name }}
                </a>
            @endif

            {{-- Time (human-readable, full date on hover) --}}
            <span class="text-xs text-gray-400 shrink-0 tabular-nums"
                  title="{{ $activity->occurred_at->format('d M Y H:i') }}">
                {{ $activity->occurred_at->diffForHumans() }}
            </span>

            {{-- External link icon (visible on row hover) --}}
            @if($url)
                <a href="{{ $url }}" target="_blank"
                   class="opacity-0 group-hover:opacity-60 hover:!opacity-100 shrink-0 transition-opacity">
                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </a>
            @endif

        </div>
    @empty
        <div class="px-4 py-10 text-center text-gray-400 italic">No activities found.</div>
    @endforelse
</div>

@if($activities->hasPages())
    <div class="mt-4">{{ $activities->links() }}</div>
@endif
@endsection
