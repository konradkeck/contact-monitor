<?php

namespace App\Integrations;

class TicketIntegration extends BaseIntegration
{
    public function label(): string { return 'ticket'; }
    public function badgeCls(): string { return 'bg-amber-100 text-amber-700'; }

    public function badgeIconSvg(string $sizeClass = 'w-full h-full'): string
    {
        return '<svg class="'.$sizeClass.'" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" viewBox="2 4 20 16">
            <path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
        </svg>';
    }
}
