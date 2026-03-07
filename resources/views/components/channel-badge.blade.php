{{--
    Canonical channel/system badge — single source of truth for icons + colors.
    Usage: <x-channel-badge type="discord" />   <x-channel-badge type="slack_user" />
    Optional: <x-channel-badge type="email" :label="false" /> to hide text
--}}
@props(['type', 'label' => true])
@php
    $t = strtolower($type ?? '');

    // SVG path definitions (all 24×24 viewBox)
    $icons = [
        'mail' => [
            'stroke' => true,
            'd' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        ],
        'ticket' => [
            'stroke' => true,
            'd' => 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z',
        ],
        'slack' => [
            'stroke' => false,
            'd' => 'M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52zm1.271 0a2.527 2.527 0 0 1 2.521-2.52 2.527 2.527 0 0 1 2.521 2.52v6.313A2.528 2.528 0 0 1 8.834 24a2.528 2.528 0 0 1-2.521-2.522v-6.313zM8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834 0a2.528 2.528 0 0 1 2.521 2.522v2.52H8.834zm0 1.271a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H2.522A2.528 2.528 0 0 1 0 8.834a2.528 2.528 0 0 1 2.522-2.521h6.312zm10.122 2.521a2.528 2.528 0 0 1 2.522-2.521A2.528 2.528 0 0 1 24 8.834a2.528 2.528 0 0 1-2.522 2.521h-2.522V8.834zm-1.268 0a2.528 2.528 0 0 1-2.523 2.521 2.527 2.527 0 0 1-2.52-2.521V2.522A2.527 2.527 0 0 1 15.165 0a2.528 2.528 0 0 1 2.523 2.522v6.312zm-2.523 10.122a2.528 2.528 0 0 1 2.523 2.522A2.528 2.528 0 0 1 15.165 24a2.527 2.527 0 0 1-2.52-2.522v-2.522h2.52zm0-1.268a2.527 2.527 0 0 1-2.52-2.523 2.526 2.526 0 0 1 2.52-2.52h6.313A2.527 2.527 0 0 1 24 15.165a2.528 2.528 0 0 1-2.522 2.523h-6.313z',
        ],
        'discord' => [
            'stroke' => false,
            'd' => 'M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.01.04.028.054a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994.021-.041.001-.09-.041-.106a13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z',
        ],
        'dot' => [
            'stroke' => false,
            'd' => 'M12 8a4 4 0 100 8 4 4 0 000-8z',
        ],
    ];

    $map = [
        'email'        => ['label' => 'email',       'cls' => 'bg-sky-100 text-sky-700',             'icon' => 'mail'],
        'imap'         => ['label' => 'email',        'cls' => 'bg-sky-100 text-sky-700',             'icon' => 'mail'],
        'ticket'       => ['label' => 'ticket',       'cls' => 'bg-amber-100 text-amber-700',         'icon' => 'ticket'],
        'whmcs'        => ['label' => 'whmcs',        'cls' => 'bg-amber-100 text-amber-700',         'icon' => 'ticket'],
        'slack'        => ['label' => 'slack',        'cls' => 'text-white',  'style' => 'background:#4A154B', 'icon' => 'slack'],
        'slack_user'   => ['label' => 'slack',        'cls' => 'text-white',  'style' => 'background:#4A154B', 'icon' => 'slack'],
        'discord'      => ['label' => 'discord',      'cls' => 'text-white',  'style' => 'background:#5865F2', 'icon' => 'discord'],
        'discord_user' => ['label' => 'discord',      'cls' => 'text-white',  'style' => 'background:#5865F2', 'icon' => 'discord'],
        'metricscube'  => ['label' => 'metricscube',  'cls' => 'bg-green-100 text-green-700',         'icon' => 'dot'],
    ];

    $cfg  = $map[$t] ?? ['label' => $t, 'cls' => 'bg-gray-100 text-gray-600', 'icon' => 'dot'];
    $icon = $icons[$cfg['icon']] ?? $icons['dot'];
@endphp
<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium {{ $cfg['cls'] }}"
      @isset($cfg['style']) style="{{ $cfg['style'] }}" @endisset>
    @if($icon['stroke'])
        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="{{ $icon['d'] }}"/>
        </svg>
    @else
        <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 24 24">
            <path d="{{ $icon['d'] }}"/>
        </svg>
    @endif
    @if($label)<span>{{ $cfg['label'] }}</span>@endif
</span>
