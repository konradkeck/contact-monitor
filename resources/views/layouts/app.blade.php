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
<body class="flex flex-col min-h-screen bg-gray-50" x-data="{ sidebarOpen: false }">
<a href="#main-content"
   class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50
          focus:px-4 focus:py-2 focus:bg-white focus:text-brand-700 focus:text-sm focus:font-medium
          focus:rounded focus:border focus:border-brand-300 focus:shadow">
    Skip to content
</a>

{{-- ─── TOP BAR ─── --}}
<header class="flex-shrink-0 z-20 sticky top-0 border-b" style="background:rgba(33,39,49,0.97);border-color:rgba(255,255,255,0.07);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)">
    <div class="flex items-center h-16 px-5 gap-6">
        {{-- Hamburger (mobile only) --}}
        <button @click="sidebarOpen = !sidebarOpen"
                class="md:hidden flex items-center justify-center w-9 h-9 -ml-2 rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition shrink-0"
                aria-label="Toggle navigation">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 flex-shrink-0">
            <img src="/logo.svg" alt="" class="w-6 h-6">
            <span class="font-medium text-base tracking-tight text-white hidden sm:inline">Contact Monitor</span>
        </a>

        {{-- Mobile section switcher dropdown --}}
        <div class="md:hidden relative ml-1" x-data="{ sectOpen: false }" @click.outside="sectOpen = false">
            <button @click="sectOpen = !sectOpen"
                    class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-sm font-medium text-slate-200 hover:text-white hover:bg-white/10 transition">
                @foreach($topSections as $label => $section)
                    @if(auth()->check() && !auth()->user()->hasPermission($section['permKey'])) @continue @endif
                    @if($section['isActive'])
                        <span>{{ $label }}</span>
                    @endif
                @endforeach
                <svg class="w-3.5 h-3.5 opacity-50 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="sectOpen" x-cloak
                 x-transition:enter="transition duration-150 ease-out"
                 x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition duration-100 ease-in"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                 style="transform-origin: top left"
                 class="absolute left-0 top-full mt-1 w-44 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50">
                @foreach($topSections as $label => $section)
                    @if(auth()->check() && !auth()->user()->hasPermission($section['permKey'])) @continue @endif
                    <a href="{{ $section['disabled'] ? '#' : $section['href'] }}"
                       @if($section['disabled']) onclick="return false" @endif
                       class="flex items-center gap-2 px-3 py-2.5 text-sm transition
                              {{ $section['isActive'] ? 'text-brand-700 font-semibold bg-brand-50' : 'text-gray-700 hover:bg-gray-50' }}
                              {{ $section['disabled'] ? 'opacity-40 pointer-events-none' : '' }}">
                        @if($section['type'] === 'ai')
                            <img src="/ai-icon.svg" class="w-5 h-5 shrink-0" alt="">
                        @endif
                        <span class="flex-1">{{ $label }}</span>
                        @if($section['isActive'])
                            <svg class="w-3.5 h-3.5 text-brand-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        @endif
                        @if($section['dot'] ?? false)
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0"></span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        <nav class="hidden md:flex items-center gap-0.5 ml-8" aria-label="Primary">
            @foreach($topSections as $label => $section)
                @if(auth()->check() && !auth()->user()->hasPermission($section['permKey']))
                    @continue
                @endif

                <a href="{{ $section['href'] }}"
                   @if($section['disabled']) title="{{ $disabledMsg }}" onclick="return false" @endif
                   @if($section['isActive']) aria-current="page" @endif
                   class="flex items-center gap-1.5 px-4 py-2 rounded text-sm font-medium transition
                          {{ $section['isActive'] ? 'bg-white/12 text-white' : 'text-slate-300 hover:text-white hover:bg-white/10' }}
                          {{ $section['disabled'] ? 'opacity-40 cursor-not-allowed' : '' }}">
                    @if($section['type'] === 'ai')
                        <img src="/ai-icon.svg" class="w-5 h-5 shrink-0" alt="">
                    @endif
                    {{ $label }}
                    @if(($section['dot'] ?? false))
                        <span class="ml-0.5 w-1.5 h-1.5 rounded-full bg-red-500 inline-block shrink-0"></span>
                    @endif
                </a>
            @endforeach
        </nav>

        {{-- ─── User dropdown ─── --}}
        <div class="ml-auto relative" x-data="{ open: false }" @click.outside="open = false">
            <button @click="open = !open"
                    class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-slate-200 hover:text-white hover:bg-white/10 transition">
                <img src="{{ 'https://www.gravatar.com/avatar/' . md5(strtolower(trim(auth()->user()->email ?? ''))) . '?s=32&d=mp' }}"
                     class="w-6 h-6 rounded-full" alt="">
                <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-cloak
                 x-transition:enter="transition duration-150 ease-out"
                 x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition duration-100 ease-in"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                 style="transform-origin: top right"
                 class="absolute right-0 top-full mt-1 w-48 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50 text-sm">
                <div class="px-3 py-2 border-b border-gray-100">
                    <p class="font-medium text-gray-800 truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-gray-400 truncate">{{ auth()->user()->email }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ auth()->user()->group?->name }}</p>
                </div>
                <a href="{{ route('auth.change-password') }}"
                   class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-50 transition">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    Change Password
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex items-center gap-2 w-full px-3 py-2 text-red-600 hover:bg-red-50 transition text-left">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

{{-- ─── BODY: SIDEBAR + CONTENT ─── --}}
<div class="flex flex-1">

    {{-- Mobile sidebar backdrop --}}
    <div x-show="sidebarOpen" @click="sidebarOpen = false" x-cloak
         x-transition:enter="transition-opacity duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-30 bg-black/50 md:hidden"></div>

    {{-- Left sidebar --}}
    <aside :class="sidebarOpen ? '!translate-x-0' : ''"
           class="sidebar w-52 flex-shrink-0 flex flex-col overflow-y-auto fixed top-16 left-0 h-[calc(100vh-4rem)]
                  -translate-x-full md:translate-x-0 transition-transform duration-200 ease-out z-40">
        <nav class="flex-1 px-2 py-3 space-y-0.5" aria-label="Sidebar" @click="sidebarOpen = false">

        @if($isConfigRoute)
            {{-- ── Configuration sidebar ── --}}

            <span class="sidebar-section pt-1">General</span>

            {{-- Setup Assistant --}}
            <a href="{{ route('setup-assistant.index') }}" class="sidebar-link {{ $saActive ? 'is-active' : '' }}" {{ $saActive ? 'aria-current="page"' : '' }}>
                <svg class="sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                <span class="flex-1">Setup Assistant</span>
                @if($setupStatus === 'active')
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0"></span>
                @elseif($setupStatus === 'partially_active')
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
                @elseif($setupStatus === 'completed')
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 shrink-0"></span>
                @endif
            </a>

            {{-- Team Access --}}
            <a href="{{ route('team-access.index') }}" class="sidebar-link {{ $taActive ? 'is-active' : '' }}" {{ $taActive ? 'aria-current="page"' : '' }}>
                <svg class="sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Team Access
            </a>

            <div class="sidebar-divider"></div>

            {{-- Synchronization --}}
            <span class="sidebar-section">Synchronization</span>
            @foreach($syncItems as $item)
                @if($item['disabled'])
                    <span class="sidebar-link is-disabled select-none" title="{{ $disabledMsg }}" aria-disabled="true" tabindex="-1" role="link">
                        @if(!empty($item['ai']))
                            <img src="/ai-icon.svg" class="sidebar-icon w-4 h-4 shrink-0 opacity-40" alt="">
                        @else
                            <svg class="sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                        @endif
                        {{ $item['label'] }}
                    </span>
                @else
                    <a href="{{ route($item['route']) }}" class="sidebar-link {{ $item['active'] ? 'is-active' : '' }}" {{ $item['active'] ? 'aria-current="page"' : '' }}>
                        @if(!empty($item['ai']))
                            <img src="/ai-icon.svg" class="sidebar-icon w-4 h-4 shrink-0" alt="">
                        @else
                            <svg class="sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                        @endif
                        <span class="flex-1">{{ $item['label'] }}</span>
                        @if($item['dot'] ?? false)
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0"></span>
                        @endif
                    </a>
                @endif
            @endforeach

            <div class="sidebar-divider"></div>

            {{-- Data Relations --}}
            <span class="sidebar-section">Data Relations</span>
            @foreach($drItems as $item)
                <a href="{{ $item['href'] }}" class="sidebar-link {{ $item['active'] ? 'is-active' : '' }}" {{ $item['active'] ? 'aria-current="page"' : '' }}>
                    <svg class="sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                    <span class="flex-1">{{ $item['label'] }}</span>
                    @if($item['dot'] ?? false)
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0"></span>
                    @endif
                </a>
            @endforeach

            <div class="sidebar-divider"></div>

            {{-- Segmentation --}}
            <span class="sidebar-section">Segmentation</span>
            <a href="{{ route('segmentation.index') }}" class="sidebar-link {{ $segActive ? 'is-active' : '' }}" {{ $segActive ? 'aria-current="page"' : '' }}>
                <svg class="sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                Segmentation
            </a>

        @elseif(auth()->user()->hasPermission('browse_data'))
            {{-- ── Browse Data sidebar ── --}}
            @foreach($sidebarItems as $item)
                @if($item['disabled'] ?? false)
                    <span class="sidebar-link is-disabled select-none" title="{{ $item['disabledMsg'] ?? '' }}" aria-disabled="true" tabindex="-1" role="link">
                        @if(!empty($item['ai']))
                            <img src="/ai-icon.svg" class="sidebar-icon w-4 h-4 shrink-0 opacity-40" alt="">
                        @else
                            <svg class="sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                        @endif
                        <span class="flex-1">{{ $item['label'] }}</span>
                    </span>
                @else
                    <a href="{{ route($item['route']) }}" class="sidebar-link {{ $item['active'] ? 'is-active' : '' }}" {{ $item['active'] ? 'aria-current="page"' : '' }}>
                        @if(!empty($item['ai']))
                            <img src="/ai-icon.svg" class="sidebar-icon w-4 h-4 shrink-0" alt="">
                        @else
                            <svg class="sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                        @endif
                        <span class="flex-1">{{ $item['label'] }}</span>
                        @if(isset($item['count']) && $item['count'] > 0)
                            <span class="text-xs opacity-50 shrink-0">{{ number_format($item['count']) }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        @endif

        </nav>
    </aside>

    {{-- Secondary sidebar: Mapping connections (only on mapping routes) --}}
    @if($isConfigRoute && $onMapping && $mappingSystems->isNotEmpty())
    <aside class="sidebar w-44 flex-shrink-0 flex flex-col overflow-y-auto fixed top-16 h-[calc(100vh-4rem)] hidden md:flex z-40" style="left: 13rem;">
        <div class="px-2 py-3 space-y-0.5">
            <a href="{{ route('configuration.mapping') }}"
               class="sidebar-link text-xs mb-1">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back
            </a>
            <span class="sidebar-section">Connections</span>
            @foreach($mappingSystems as $sys)
                <a href="{{ route('data-relations.mapping', [$sys->system_type, $sys->system_slug]) }}"
                   class="sidebar-link {{ $currentMapping === $sys->system_type.'/'.$sys->system_slug ? 'is-active' : '' }}">
                    <x-channel-badge :type="$sys->system_type" />
                    <span class="truncate text-xs">{{ $sys->system_slug }}</span>
                </a>
            @endforeach
        </div>
    </aside>
    @endif

    {{-- Main content --}}
    <main id="main-content" class="flex-1 min-w-0" style="margin-left: {{ $mainMargin }};">

        <div class="px-6 py-5 max-w-screen-2xl mx-auto">
        {{-- Flash messages --}}
        @if(session('success'))
            <div class="flash-msg mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm flex items-center gap-3">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path class="flash-check" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <span class="flex-1">{{ session('success') }}</span>
                <button onclick="dismissFlash(this)" aria-label="Dismiss"
                        class="opacity-40 hover:opacity-70 transition-opacity text-lg leading-none shrink-0">×</button>
            </div>
        @endif
        @if(session('error'))
            <div class="flash-msg mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm flex items-center gap-3">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008zm9.303-3.376c-.866 1.5.217 3.374 1.948 3.374H2.749c-1.73 0-2.813-1.874-1.948-3.374L10.052 3.378c.866-1.5 3.032-1.5 3.898 0L21.303 13.374z"/></svg>
                <span class="flex-1">{{ session('error') }}</span>
                <button onclick="dismissFlash(this)" aria-label="Dismiss"
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
<script>
function dismissFlash(btn) {
    var el = btn.closest('.flash-msg');
    el.classList.add('is-dismissing');
    setTimeout(function() { el.remove(); }, 200);
}
/* Developer easter egg */
console.log(
    '%c Contact Monitor ',
    'background:#a40057;color:#fff;font-size:13px;font-weight:700;padding:3px 10px;border-radius:4px;'
);
console.log('%cPowered by Laravel · Tailwind CSS · PostgreSQL', 'color:#9ca3af;font-size:11px;');
</script>
<script>
window.drp = (function () {
    var CDN = 'https://cdn.jsdelivr.net/npm/@easepick/bundle@1.2.1/dist/index.css';

    var COMPACT = [
        ':host{--day-width:30px;--day-height:26px}',
        '.container{font-size:11px !important}',
        '.calendar{padding:5px !important}',
        '.calendar>.header{padding:3px 4px !important}',
        '.month-name{font-size:11px !important}',
        '.previous-button,.next-button{padding:1px 5px !important;font-size:13px !important}',
        '.dayname{font-size:10px !important;padding:2px 0 !important}',
        '.day{font-size:11px !important;padding:2px 0 !important}',
        '.preset-plugin-container{padding:6px !important;width:110px !important;flex-direction:column !important;justify-content:flex-start !important;gap:2px !important}',
        '.preset-plugin-container>button{padding:9px 10px !important;font-size:11px !important;margin:0 !important;display:block;width:100% !important;text-align:left !important;white-space:nowrap !important}',
    ].join('');

    function ld(d) {
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    }
    function parseYmd(s) {
        var p = s.split('-'); return new Date(+p[0], +p[1]-1, +p[2]);
    }

    function init(inputId, onApply, opts) {
        opts = opts || {};
        var EP = window._EP;
        var input = document.getElementById(inputId);
        var wrap  = document.getElementById(inputId + '-wrap');
        var clearBtn = wrap ? wrap.querySelector('.drp-clear') : null;

        var n = new Date();
        var presets = opts.presets || {
            'Today':          [new Date(n.getFullYear(), n.getMonth(), n.getDate()), n],
            'Last 7 days':    [new Date(+n - 6*86400000),   n],
            'Last 30 days':   [new Date(+n - 29*86400000),  n],
            'Last 90 days':   [new Date(+n - 89*86400000),  n],
            'Last 365 days':  [new Date(+n - 364*86400000), n],
            'This year':      [new Date(n.getFullYear(), 0, 1), n],
        };

        var startDate, endDate, fireInitial = false;
        if (opts.defaultDays) {
            startDate = new Date(+n - (opts.defaultDays - 1) * 86400000);
            endDate   = n;
            fireInitial = true;
        } else if (opts.defaultFrom && opts.defaultTo) {
            startDate = parseYmd(opts.defaultFrom);
            endDate   = parseYmd(opts.defaultTo);
        }

        var cfg = {
            element: input,
            css: function () {
                var sr = this.ui.shadowRoot, wrapper = this.ui.wrapper;
                var link = document.createElement('link');
                link.href = CDN; link.rel = 'stylesheet';
                var done = function () { wrapper.style.display = ''; };
                link.addEventListener('load', done);
                link.addEventListener('error', done);
                sr.append(link);
                var style = document.createElement('style');
                style.textContent = COMPACT;
                sr.append(style);
            },
            plugins: [EP.RangePlugin, EP.PresetPlugin],
            format: 'D MMM YYYY',
            zIndex: 9999,
            calendars: 1,
            RangePlugin: { tooltip: true },
            PresetPlugin: { position: 'left', customPreset: presets },
            setup: function (picker) {
                picker.on('show', function () {
                    /* Fix easepick's broken right-overflow detection */
                    var inp = document.getElementById(inputId);
                    var wrapper = picker.ui.wrapper;
                    var container = picker.ui.container;
                    var iRect = inp.getBoundingClientRect();
                    var wRect = wrapper.getBoundingClientRect();
                    var cRect = container.getBoundingClientRect();
                    if (iRect.left + cRect.width > window.innerWidth - 8) {
                        container.style.left = Math.round(iRect.right - wRect.left - cRect.width) + 'px';
                    }
                });
                picker.on('select', function (e) {
                    var s = e.detail.start, en = e.detail.end;
                    if (s && en) {
                        if (clearBtn) clearBtn.classList.remove('hidden');
                        onApply(ld(new Date(s)), ld(new Date(en)));
                    }
                });
            }
        };
        if (startDate) {
            cfg.RangePlugin.startDate = startDate;
            cfg.RangePlugin.endDate   = endDate;
        }

        var picker = new EP.easepick.create(cfg);

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                picker.clear();
                clearBtn.classList.add('hidden');
                onApply('', '');
            });
        }

        if (fireInitial) { onApply(ld(startDate), ld(endDate)); }

        return picker;
    }

    return { init: init };
}());
</script>
</body>
</html>
