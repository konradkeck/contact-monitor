<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SalesOS') — SalesOS</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>💪</text></svg>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                fontFamily: {
                    sans: ['"IBM Plex Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                },
                extend: {
                    colors: {
                        brand: {
                            50:  '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }

        /* ── Cards ── */
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; }
        .card-header { padding: 0.75rem 1rem; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; }
        .card-inner { border-top: 1px solid #e5e7eb; }

        /* ── Buttons ── */
        .btn { display: inline-flex; align-items: center; gap: 0.35rem; border-radius: 6px;
               padding: 0.4rem 0.85rem; font-size: 0.8rem; font-weight: 500;
               transition: background .12s, border-color .12s; cursor: pointer; border: 1px solid transparent;
               text-decoration: none; white-space: nowrap; }
        .btn-sm { padding: 0.25rem 0.6rem; font-size: 0.75rem; }
        .btn-primary   { background: #2563eb; border-color: #1d4ed8; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #fff; border-color: #d1d5db; color: #374151; }
        .btn-secondary:hover { background: #f9fafb; border-color: #9ca3af; }
        .btn-danger    { background: transparent; border-color: rgba(220,38,38,.3); color: #dc2626; }
        .btn-danger:hover { background: rgba(220,38,38,.05); border-color: rgba(220,38,38,.5); }
        .btn-muted     { background: #f3f4f6; border-color: #e5e7eb; color: #6b7280; }
        .btn-muted:hover { background: #e5e7eb; color: #374151; }

        /* ── Inputs ── */
        .input { background: #fff; border: 1px solid #d1d5db; color: #111827;
                 border-radius: 6px; padding: 0.4rem 0.65rem; font-size: 0.875rem; width: 100%; }
        .input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
        .input::placeholder { color: #9ca3af; }
        .label { display: block; font-size: 0.75rem; font-weight: 600;
                 color: #6b7280; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .3rem; }

        /* ── Badges ── */
        .badge { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.7rem;
                 font-weight: 600; padding: 0.15rem 0.55rem; border-radius: 999px; }
        .badge-gray    { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }
        .badge-blue    { background: rgba(37,99,235,.1); color: #1d4ed8; border: 1px solid rgba(37,99,235,.2); }
        .badge-green   { background: rgba(22,163,74,.1); color: #15803d; border: 1px solid rgba(22,163,74,.2); }
        .badge-red     { background: rgba(220,38,38,.1); color: #dc2626; border: 1px solid rgba(220,38,38,.2); }
        .badge-yellow  { background: rgba(202,138,4,.1); color: #a16207; border: 1px solid rgba(202,138,4,.2); }

        /* ── Table rows ── */
        .tbl-header { background: #f9fafb; border-bottom: 1px solid #e5e7eb;
                      font-size: 0.65rem; font-weight: 500; color: #9ca3af;
                      text-transform: uppercase; letter-spacing: .06em; }
        .tbl-row { border-top: 1px solid #f3f4f6; }
        .tbl-row:hover { background: #f9fafb; }

        /* ── Page header ── */
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
        .page-title  { font-size: 1rem; font-weight: 700; color: #111827; }
    </style>
</head>
<body class="flex flex-col min-h-screen" style="background:#f6f8fa">

{{-- ─── TOP BAR ─── --}}
<header class="flex-shrink-0 z-20 sticky top-0" style="background:#24292f; border-bottom:1px solid #1b1f24">
    <div class="flex items-center h-12 px-5 gap-6">
        <a href="{{ route('dashboard') }}" class="font-bold text-sm tracking-tight text-white flex-shrink-0">
            SalesOS
        </a>

        @php
            $topSections = [
                'SalesOS'        => ['route' => 'dashboard',          'pattern' => ['dashboard', 'companies.*', 'people.*', 'conversations.*', 'campaigns.*', 'activities.*']],
                'Mielonka'       => ['route' => 'mielonka.index',       'pattern' => ['mielonka.*']],
                'Data Relations' => ['route' => 'data-relations.index','pattern' => ['data-relations.*', 'our-company.*', 'filtering.*']],
                'Configuration'  => ['route' => 'brand-products.index','pattern' => ['brand-products.*']],
            ];
        @endphp

        <nav class="flex items-center gap-0.5">
            @foreach($topSections as $label => $section)
                @php
                    $isActive = !empty($section['pattern']) && request()->routeIs($section['pattern']);
                    $href     = $section['route'] ? route($section['route']) : '#';
                @endphp
                <a href="{{ $href }}"
                   class="px-3 py-1.5 rounded text-xs font-medium transition
                          {{ $isActive
                              ? 'bg-white/10 text-white'
                              : 'text-gray-400 hover:text-white hover:bg-white/8' }}
                          {{ $section['route'] === null ? 'opacity-40 cursor-not-allowed pointer-events-none' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    </div>
</header>

{{-- ─── BODY: SIDEBAR + CONTENT ─── --}}
<div class="flex flex-1">

    {{-- Left sidebar --}}
    <aside class="w-52 flex-shrink-0 flex flex-col overflow-y-auto sticky top-12 self-start h-[calc(100vh-3rem)]"
           style="background:#fff; border-right:1px solid #d0d7de">
        <nav class="flex-1 px-2 py-3 space-y-0.5">

        @if(request()->routeIs('data-relations.*') || request()->routeIs('our-company.*') || request()->routeIs('filtering.*'))
            {{-- ── Data Relations sidebar ── --}}
            @php
                $identityToSystem = [
                    'email'        => 'imap',
                    'slack_user'   => 'slack',
                    'discord_user' => 'discord',
                ];
                $_accountSystems = \App\Models\Account::select('system_type', 'system_slug')
                    ->whereNotNull('system_type')->whereNotNull('system_slug')
                    ->distinct()->orderBy('system_type')->orderBy('system_slug')->get();
                $_identSystems = \App\Models\Identity::select('system_slug', 'type')
                    ->whereNotNull('system_slug')
                    ->distinct()->orderBy('type')->orderBy('system_slug')->get()
                    ->map(fn($r) => (object)[
                        'system_type' => $identityToSystem[$r->type] ?? $r->type,
                        'system_slug' => $r->system_slug,
                    ]);
                $mappingSystems = $_accountSystems->concat($_identSystems)
                    ->unique(fn($s) => $s->system_type.'/'.$s->system_slug)
                    ->sortBy(['system_type', 'system_slug'])
                    ->values();
                $currentMapping = request()->route('systemType') && request()->route('systemSlug')
                    ? request()->route('systemType').'/'.request()->route('systemSlug')
                    : null;
            @endphp

            <p class="px-2 pt-1 pb-1 text-xs font-semibold uppercase tracking-wider" style="color:#57606a">Data Relations</p>

            @php $overviewActive  = request()->routeIs('data-relations.index'); @endphp
            @php $filteringActive = request()->routeIs('filtering.*'); @endphp
            @php $ourCoActive     = request()->routeIs('our-company.*'); @endphp

            @foreach([
                ['href' => route('data-relations.index'), 'label' => 'Overview',          'active' => $overviewActive],
                ['href' => route('filtering.index'),      'label' => 'Filtering',          'active' => $filteringActive],
                ['href' => route('our-company.index'),    'label' => 'Our Organization',   'active' => $ourCoActive],
            ] as $item)
                <a href="{{ $item['href'] }}"
                   class="flex items-center gap-2 px-2 py-1.5 rounded text-sm transition"
                   style="{{ $item['active']
                       ? 'background:#dbeafe; color:#1e40af; font-weight:600'
                       : 'color:#24292f' }}"
                   @if(!$item['active']) onmouseover="this.style.background='#f6f8fa'" onmouseout="this.style.background=''" @endif>
                    {{ $item['label'] }}
                </a>
            @endforeach

            @if($mappingSystems->isNotEmpty())
                <div class="pt-2 pb-1 px-2">
                    <div style="border-top:1px solid #d0d7de"></div>
                </div>
                <p class="px-2 pb-1 text-xs font-semibold uppercase tracking-wider" style="color:#57606a">Mapping</p>
                @foreach($mappingSystems as $sys)
                    @php $key = $sys->system_type.'/'.$sys->system_slug; $active = $currentMapping === $key; @endphp
                    <a href="{{ route('data-relations.mapping', [$sys->system_type, $sys->system_slug]) }}"
                       class="flex items-center gap-2 px-2 py-1.5 rounded text-sm transition"
                       style="{{ $active ? 'background:#dbeafe; color:#1e40af; font-weight:600' : 'color:#24292f' }}"
                       @if(!$active) onmouseover="this.style.background='#f6f8fa'" onmouseout="this.style.background=''" @endif>
                        <x-channel-badge :type="$sys->system_type" />
                        <span class="truncate text-xs">{{ $sys->system_slug }}</span>
                    </a>
                @endforeach
            @endif

        @else
            {{-- ── Main SalesOS sidebar ── --}}
            @php
                $sidebarItems = [
                    ['label' => 'Dashboard',     'route' => 'dashboard',          'match' => ['dashboard'],         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>'],
                    ['label' => 'Companies',     'route' => 'companies.index',    'match' => ['companies.*'],       'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>'],
                    ['label' => 'People',        'route' => 'people.index',       'match' => ['people.*'],          'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>'],
                    ['label' => 'Conversations', 'route' => 'conversations.index','match' => ['conversations.*'],   'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>'],
                    ['label' => 'Campaigns',     'route' => 'campaigns.index',    'match' => ['campaigns.*'],       'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>'],
                    ['label' => 'Activities',    'route' => 'activities.index',   'match' => ['activities.*'],      'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 10V3L4 14h7v7l9-11h-7z"/>'],
                ];
            @endphp

            @foreach($sidebarItems as $item)
                @php $active = request()->routeIs($item['match']); @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-2.5 px-2 py-1.5 rounded text-sm transition"
                   style="{{ $active
                       ? 'background:#dbeafe; color:#1e40af; font-weight:600'
                       : 'color:#24292f' }}"
                   @if(!$active) onmouseover="this.style.background='#f6f8fa'" onmouseout="this.style.background=''" @endif>
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         style="{{ $active ? 'color:#1e40af' : 'color:#57606a' }}">
                        {!! $item['icon'] !!}
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach

            <div class="pt-3 pb-1 px-2">
                <div style="border-top:1px solid #d0d7de"></div>
            </div>
            <p class="px-2 pb-1 text-xs font-semibold uppercase tracking-wider" style="color:#57606a">Configuration</p>

            @php $bpActive = request()->routeIs('brand-products.*'); @endphp
            <a href="{{ route('brand-products.index') }}"
               class="flex items-center gap-2.5 px-2 py-1.5 rounded text-sm transition"
               style="{{ $bpActive ? 'background:#dbeafe; color:#1e40af; font-weight:600' : 'color:#24292f' }}"
               @if(!$bpActive) onmouseover="this.style.background='#f6f8fa'" onmouseout="this.style.background=''" @endif>
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                     style="{{ $bpActive ? 'color:#1e40af' : 'color:#57606a' }}">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                          d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                Segmentation
            </a>
        @endif

        </nav>
    </aside>

    {{-- Main content --}}
    <main class="flex-1 min-w-0">

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="px-6 py-2.5 text-sm border-b" style="background:#dafbe1; border-color:#a7f3d0; color:#166534">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="px-6 py-2.5 text-sm border-b" style="background:#fff0f0; border-color:#fca5a5; color:#991b1b">
                {{ session('error') }}
            </div>
        @endif

        <div class="px-6 py-5 max-w-screen-2xl mx-auto">
            @yield('content')
        </div>
    </main>

</div>

@stack('scripts')
<x-activity-modal />
</body>
</html>
