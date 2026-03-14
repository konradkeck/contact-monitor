<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Contact Monitor') — Contact Monitor</title>
    <link rel="icon" type="image/svg+xml" href="/logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex flex-col min-h-screen bg-gray-50">
<a href="#main-content"
   class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50
          focus:px-4 focus:py-2 focus:bg-white focus:text-brand-700 focus:text-sm focus:font-medium
          focus:rounded focus:border focus:border-brand-300 focus:shadow">
    Skip to content
</a>

{{-- ─── TOP BAR ─── --}}
<header class="flex-shrink-0 z-20 sticky top-0 border-b" style="background:#24292f; border-color:#1b1f24">
    <div class="flex items-center h-16 px-5 gap-6">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 flex-shrink-0">
            <img src="/logo.svg" alt="" class="w-6 h-6">
            <span class="font-bold text-base tracking-tight text-white">Contact Monitor</span>
        </a>

        @php
            $disabledMsg = 'Configure a synchronizer server first';

            $isConfigRoute = request()->routeIs(
                'synchronizer.*', 'data-relations.*', 'our-company.*',
                'filtering.*', 'segmentation.*', 'configuration.*'
            );

            $topSections = [
                'Browse Data' => [
                    'route'    => 'dashboard',
                    'pattern'  => ['dashboard', 'companies.*', 'people.*', 'conversations.*', 'activity.*'],
                    'disabled' => !$hasServers,
                    'type'     => 'normal',
                ],
                'Analyse' => [
                    'route'    => null,
                    'pattern'  => [],
                    'disabled' => true,
                    'type'     => 'ai',
                ],
                'Configuration' => [
                    'route'    => $hasServers ? 'synchronizer.index' : 'synchronizer.servers.index',
                    'pattern'  => ['synchronizer.*', 'data-relations.*', 'our-company.*', 'filtering.*', 'segmentation.*', 'configuration.*'],
                    'disabled' => false,
                    'type'     => 'config',
                    'dot'      => $configNeedsAttention,
                ],
            ];
        @endphp

        <nav class="flex items-center gap-0.5 ml-8" aria-label="Primary">
            @foreach($topSections as $label => $section)
                @php
                    $isActive  = !empty($section['pattern']) && request()->routeIs($section['pattern']);
                    $disabled  = $section['disabled'];
                    $href      = ($disabled || $section['route'] === null) ? '#' : route($section['route']);
                @endphp

                @if($section['type'] === 'ai')
                    <span title="Coming soon"
                          class="flex items-center px-4 py-2 rounded text-sm font-medium opacity-40 cursor-not-allowed text-gray-400">
                        {{ $label }}
                        <img src="{{ asset('ai-icon.svg') }}" class="ml-1 w-5 h-5 shrink-0" alt="">
                    </span>
                @else
                    <a href="{{ $href }}"
                       @if($disabled) title="{{ $disabledMsg }}" onclick="return false" @endif
                       class="flex items-center px-4 py-2 rounded text-sm font-medium transition
                              {{ $isActive ? 'bg-white/10 text-white' : 'text-gray-400 hover:text-white hover:bg-white/8' }}
                              {{ $disabled ? 'opacity-40 cursor-not-allowed' : '' }}">
                        {{ $label }}
                        @if(($section['dot'] ?? false))
                            <span class="ml-1.5 w-1.5 h-1.5 rounded-full bg-red-500 inline-block shrink-0"></span>
                        @endif
                    </a>
                @endif
            @endforeach
        </nav>
    </div>
</header>

{{-- ─── BODY: SIDEBAR + CONTENT ─── --}}
<div class="flex flex-1">

    {{-- Left sidebar --}}
    <aside class="w-52 flex-shrink-0 flex flex-col overflow-y-auto fixed top-16 left-0 h-[calc(100vh-4rem)] bg-white border-r border-gray-200">
        <nav class="flex-1 px-2 py-3 space-y-0.5" aria-label="Sidebar">

        @if($isConfigRoute)
            {{-- ── Configuration sidebar ── --}}
            @php
                $onMapping = request()->routeIs('data-relations.mapping', 'configuration.mapping');
                $currentMapping = (request()->route('systemType') && request()->route('systemSlug'))
                    ? request()->route('systemType').'/'.request()->route('systemSlug')
                    : null;
            @endphp

            {{-- Settings (coming soon) --}}
            <p class="px-2 pt-1 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400">Settings</p>
            @foreach(['Setup Checklist', 'General Settings', 'Team Access'] as $comingSoon)
                <span class="flex items-center gap-2.5 px-2 py-1.5 rounded text-sm text-gray-300 cursor-not-allowed select-none"
                      title="Coming soon">{{ $comingSoon }}</span>
            @endforeach

            <div class="pt-2 pb-1 px-2"><div class="border-t border-gray-100"></div></div>

            {{-- Synchronization --}}
            <p class="px-2 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400">Synchronization</p>
            @php
                $syncItems = [
                    ['label' => 'Connections',          'route' => 'synchronizer.index',         'match' => ['synchronizer.index', 'synchronizer.connections.*', 'synchronizer.runs*', 'synchronizer.kill-all', 'synchronizer.run-all'], 'disabled' => !$hasServers, 'dot' => false,
                     'icon' => '<circle cx="6" cy="17" r="2.75" stroke-width="1.75"/><circle cx="20" cy="4" r="2" stroke-width="1.75"/><circle cx="20" cy="15" r="2" stroke-width="1.75"/><circle cx="10" cy="5" r="2" stroke-width="1.75"/><path stroke-linecap="round" stroke-width="1.75" d="M8 15L18.5 5.5M8 16.5L18.5 14.5M7.5 14.5L9.5 7"/>'],
                    ['label' => 'Synchronizer Servers', 'route' => 'synchronizer.servers.index', 'match' => ['synchronizer.servers.*', 'synchronizer.wizard.*'], 'disabled' => false, 'dot' => $serverNeedsAttention,
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>'],
                ];
            @endphp
            @foreach($syncItems as $item)
                @php $active = request()->routeIs($item['match']); @endphp
                @if($item['disabled'])
                    <span class="flex items-center gap-2.5 px-2 py-1.5 rounded text-sm cursor-not-allowed select-none text-gray-300"
                          title="{{ $disabledMsg }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                        {{ $item['label'] }}
                    </span>
                @else
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-2.5 px-2 py-1.5 rounded text-sm transition
                              {{ $active ? 'bg-blue-100 text-blue-800 font-semibold' : 'text-gray-900 hover:bg-gray-50' }}">
                        <svg class="w-4 h-4 flex-shrink-0 {{ $active ? 'text-blue-700' : 'text-gray-400' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                        <span class="flex-1">{{ $item['label'] }}</span>
                        @if($item['dot'] ?? false)
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0"></span>
                        @endif
                    </a>
                @endif
            @endforeach

            <div class="pt-2 pb-1 px-2"><div class="border-t border-gray-100"></div></div>

            {{-- Data Relations --}}
            <p class="px-2 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400">Data Relations</p>
            @php
                $drItems = [
                    ['href' => route('configuration.mapping'), 'label' => 'Mapping',         'active' => request()->routeIs('data-relations.index', 'configuration.mapping', 'data-relations.mapping'), 'dot' => $mappingNeedsAttention,
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>'],
                    ['href' => route('filtering.index'),       'label' => 'Filtering',       'active' => request()->routeIs('filtering.*'), 'dot' => false,
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>'],
                    ['href' => route('our-company.index'),     'label' => 'Our Organization','active' => request()->routeIs('our-company.*'), 'dot' => false,
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 21h18M4 21V7l8-4 8 4v14M9 21v-6h6v6"/>'],
                ];
            @endphp
            @foreach($drItems as $item)
                @php $active = $item['active']; @endphp
                <a href="{{ $item['href'] }}"
                   class="flex items-center gap-2.5 px-2 py-1.5 rounded text-sm transition
                          {{ $active ? 'bg-blue-100 text-blue-800 font-semibold' : 'text-gray-900 hover:bg-gray-50' }}">
                    <svg class="w-4 h-4 flex-shrink-0 {{ $active ? 'text-blue-700' : 'text-gray-400' }}"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                    <span class="flex-1">{{ $item['label'] }}</span>
                    @if($item['dot'] ?? false)
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0"></span>
                    @endif
                </a>
            @endforeach

            <div class="pt-2 pb-1 px-2"><div class="border-t border-gray-100"></div></div>

            {{-- Segmentation --}}
            <p class="px-2 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400">Segmentation</p>
            @php $segActive = request()->routeIs('segmentation.*'); @endphp
            <a href="{{ route('segmentation.index') }}"
               class="flex items-center gap-2.5 px-2 py-1.5 rounded text-sm transition
                      {{ $segActive ? 'bg-blue-100 text-blue-800 font-semibold' : 'text-gray-900 hover:bg-gray-50' }}">
                <svg class="w-4 h-4 flex-shrink-0 {{ $segActive ? 'text-blue-700' : 'text-gray-400' }}"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                Segmentation
            </a>

        @else
            {{-- ── Browse Data sidebar ── --}}
            <p class="px-2 pt-1 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Browse Data</p>
            @php
                $sidebarItems = [
                    ['label' => 'Dashboard',     'route' => 'dashboard',          'match' => ['dashboard'],         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>'],
                    ['label' => 'Companies',     'route' => 'companies.index',    'match' => ['companies.*'],       'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>'],
                    ['label' => 'People',        'route' => 'people.index',       'match' => ['people.*'],          'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>'],
                    ['label' => 'Conversations', 'route' => 'conversations.index','match' => ['conversations.*'],   'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>'],
                    ['label' => 'Activity',      'route' => 'activity.index',     'match' => ['activity.*'],        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 10V3L4 14h7v7l9-11h-7z"/>'],
                ];
            @endphp

            @foreach($sidebarItems as $item)
                @php $active = request()->routeIs($item['match']); @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-2.5 px-2 py-1.5 rounded text-sm transition
                          {{ $active ? 'bg-blue-100 text-blue-800 font-semibold' : 'text-gray-900 hover:bg-gray-50' }}">
                    <svg class="w-4 h-4 flex-shrink-0 {{ $active ? 'text-blue-800' : 'text-gray-500' }}"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {!! $item['icon'] !!}
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        @endif

        </nav>
    </aside>

    {{-- Secondary sidebar: Mapping connections (only on mapping routes) --}}
    @if($isConfigRoute && $onMapping && $mappingSystems->isNotEmpty())
    <aside class="w-44 flex-shrink-0 flex flex-col overflow-y-auto fixed top-16 h-[calc(100vh-4rem)] bg-gray-50 border-r border-gray-200" style="left: 13rem;">
        <div class="px-2 py-3 space-y-0.5">
            <a href="{{ route('configuration.mapping') }}"
               class="flex items-center gap-1.5 px-2 py-1.5 mb-1 rounded text-xs text-gray-400 hover:text-gray-700 hover:bg-white transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back
            </a>
            <p class="px-2 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400">Connections</p>
            @foreach($mappingSystems as $sys)
                @php $subActive = $currentMapping === $sys->system_type.'/'.$sys->system_slug; @endphp
                <a href="{{ route('data-relations.mapping', [$sys->system_type, $sys->system_slug]) }}"
                   class="flex items-center gap-2 px-2 py-1.5 rounded text-sm transition
                          {{ $subActive ? 'bg-blue-100 text-blue-800 font-semibold' : 'text-gray-700 hover:bg-white hover:shadow-sm' }}">
                    <x-channel-badge :type="$sys->system_type" />
                    <span class="truncate text-xs">{{ $sys->system_slug }}</span>
                </a>
            @endforeach
        </div>
    </aside>
    @endif

    {{-- Main content --}}
    @php $mainMargin = ($isConfigRoute && $onMapping && $mappingSystems->isNotEmpty()) ? '24rem' : '13rem'; @endphp
    <main id="main-content" class="flex-1 min-w-0" style="margin-left: {{ $mainMargin }};">

        <div class="px-6 py-5 max-w-screen-2xl mx-auto">
        {{-- Flash messages --}}
        @if(session('success'))
            <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm flex items-center gap-3">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <span class="flex-1">{{ session('success') }}</span>
                <button onclick="this.parentElement.remove()" aria-label="Dismiss"
                        class="opacity-40 hover:opacity-70 transition-opacity text-lg leading-none shrink-0">×</button>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm flex items-center gap-3">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008zm9.303-3.376c-.866 1.5.217 3.374 1.948 3.374H2.749c-1.73 0-2.813-1.874-1.948-3.374L10.052 3.378c.866-1.5 3.032-1.5 3.898 0L21.303 13.374z"/></svg>
                <span class="flex-1">{{ session('error') }}</span>
                <button onclick="this.parentElement.remove()" aria-label="Dismiss"
                        class="opacity-40 hover:opacity-70 transition-opacity text-lg leading-none shrink-0">×</button>
            </div>
        @endif
        @if($serverNeedsAttention && request()->routeIs('synchronizer.servers.*'))
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm flex items-start gap-2">
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                <span>{{ $serverBadCount }} server(s) not responding. Check your synchronizer server connection.</span>
            </div>
        @endif
        @if($mappingNeedsAttention && request()->routeIs('data-relations.*', 'configuration.mapping'))
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm flex items-start gap-2">
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                <span>Mapping is not configured in at least 50% for: {{ implode(', ', $mappingUnhealthySystems) }}</span>
            </div>
        @endif
            @yield('content')
        </div>
    </main>

</div>

@stack('scripts')
<x-activity-modal />
</body>
</html>
