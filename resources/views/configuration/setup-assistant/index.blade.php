@extends('layouts.app')
@section('title', 'Setup Assistant')

@section('content')

<div class="max-w-2xl">

<div class="page-header">
    <div>
        <h1 class="page-title">Setup Assistant</h1>
        <p class="text-xs text-gray-400 mt-0.5">Complete these steps to get Contact Monitor fully operational.</p>
    </div>
</div>

{{-- ── REQUIRES YOUR ATTENTION ── --}}
@if($attention->isNotEmpty())
<section class="mb-6">
    <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Requires Your Attention</h2>
    <div class="space-y-2">
        @foreach($attention as $item)
            <div class="rounded-lg border-l-4 {{ $item['cardClass'] }} px-5 py-4">
                <div class="flex items-center gap-3 mb-2">
                    <svg class="w-4 h-4 shrink-0 {{ $item['iconColor'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {!! $item['icon'] !!}
                    </svg>
                    <span class="font-semibold text-gray-900 text-sm flex-1">{{ $item['name'] }}</span>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $item['badge'] }}">{{ $item['label'] }}</span>
                </div>
                <p class="text-sm text-gray-500 leading-relaxed ml-7 mb-3">{{ $item['description'] }}</p>
                <div class="flex justify-end">
                    @if($item['action_href'] && $item['status'] !== 'disabled')
                        <a href="{{ $item['action_href'] }}" class="text-xs font-medium px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-gray-700 hover:border-brand-400 hover:text-brand-700 transition">
                            {{ $item['action_label'] }} →
                        </a>
                    @else
                        <span class="text-xs font-medium px-3 py-1.5 rounded-lg border border-gray-200 bg-white/60 text-gray-300 cursor-not-allowed">
                            {{ $item['action_label'] ?? '—' }}
                        </span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

{{-- ── COMPLETED ── --}}
@if($completed->isNotEmpty())
<section>
    <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Completed</h2>
    <div class="space-y-2">
        @foreach($completed as $item)
            <div class="rounded-lg border-l-4 {{ $item['cardClass'] }} px-5 py-4">
                <div class="flex items-center gap-3 mb-2">
                    <svg class="w-4 h-4 shrink-0 {{ $item['iconColor'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {!! $item['icon'] !!}
                    </svg>
                    <span class="font-semibold text-gray-900 text-sm flex-1">{{ $item['name'] }}</span>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $item['badge'] }}">{{ $item['label'] }}</span>
                </div>
                <p class="text-sm text-gray-500 leading-relaxed ml-7 mb-3">{{ $item['description'] }}</p>
                <div class="flex justify-end">
                    <span class="text-xs font-medium px-3 py-1.5 rounded-lg border border-green-200 bg-white/60 text-green-600">Done</span>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

@if($attention->isEmpty())
<div class="mt-6 bg-green-50 border border-green-200 rounded-lg px-5 py-4 text-center">
    <p class="text-sm font-semibold text-green-800">Everything is set up correctly.</p>
    <p class="text-xs text-green-600 mt-0.5">Contact Monitor is fully operational.</p>
</div>
@endif

</div>
@endsection
