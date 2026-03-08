<?php

namespace App\Integrations;

class GenericIntegration extends BaseIntegration
{
    public function __construct(private readonly string $systemType = 'generic') {}

    public function label(): string { return ucfirst($this->systemType); }
    public function badgeCls(): string { return 'bg-gray-100 text-gray-600'; }

    public function badgeIconSvg(string $sizeClass = 'w-3 h-3'): string
    {
        return '<svg class="'.$sizeClass.' shrink-0" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="4"/>
        </svg>';
    }
}
