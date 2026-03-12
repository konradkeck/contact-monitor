<?php

namespace App\Integrations;

class EmailIntegration extends BaseIntegration
{
    public function label(): string { return 'email'; }
    public function badgeCls(): string { return 'bg-sky-100 text-sky-700'; }
    public function badgePadding(): string { return 'p-[2px]'; }

    public function badgeIconSvg(string $sizeClass = 'w-full h-full'): string
    {
        return '<svg class="'.$sizeClass.'" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" viewBox="2 4 20 16">
            <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>';
    }

}
