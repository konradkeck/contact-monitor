<?php

namespace App\Integrations;

class WhmcsIntegration extends BaseIntegration
{
    public function label(): string { return 'WHMCS'; }
    public function badgeCls(): string { return 'bg-white'; }
    public function badgePadding(): string { return ''; }

    public function badgeIconSvg(string $sizeClass = 'w-full h-full'): string
    {
        return '<img src="/img/integrations/whmcs.png" class="'.$sizeClass.' object-contain" alt="WHMCS">';
    }

    public function servicesWidgetView(): ?string
    {
        return 'integrations.whmcs.services-widget';
    }
}
