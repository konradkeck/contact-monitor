<?php

namespace App\Integrations;

class MetricscubeIntegration extends BaseIntegration
{
    public function label(): string { return 'MetricsCube'; }
    public function badgeCls(): string { return ''; }
    public function badgePadding(): string { return ''; }

    public function badgeIconSvg(string $sizeClass = 'w-full h-full'): string
    {
        return '<img src="/img/integrations/metricscube.png" class="'.$sizeClass.' object-cover" alt="MetricsCube">';
    }
}
