<?php

namespace App\Integrations;

class GenericIntegration extends BaseIntegration
{
    public function __construct(private readonly string $systemType = 'generic') {}

    public function label(): string { return ucfirst($this->systemType); }
    public function badgeCls(): string { return 'bg-gray-100 text-gray-600'; }

    public function badgeIconSvg(string $sizeClass = 'w-full h-full'): string
    {
        return '<svg class="'.$sizeClass.'" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" viewBox="7 7 10 10">
            <circle cx="12" cy="12" r="4"/>
        </svg>';
    }
}
