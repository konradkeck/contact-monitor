<?php

namespace App\Integrations;

class MetricscubeIntegration extends BaseIntegration
{
    public function label(): string { return 'metricscube'; }
    public function badgeCls(): string { return 'bg-green-100 text-green-700'; }

    public function badgeIconSvg(string $sizeClass = 'w-3 h-3'): string
    {
        return '<svg class="'.$sizeClass.' shrink-0" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 8a4 4 0 100 8 4 4 0 000-8z"/>
        </svg>';
    }

}
