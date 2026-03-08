<?php

namespace App\Integrations;

class WhmcsIntegration extends BaseIntegration
{
    public function label(): string { return 'WHMCS'; }

    /** Badge: white "W" on WHMCS blue */
    public function badgeCls(): string { return 'text-white'; }
    public function badgeStyle(): ?string { return 'background:#1a6fe0'; }

    public function badgeIconSvg(string $sizeClass = 'w-3 h-3'): string
    {
        return '<svg class="'.$sizeClass.' shrink-0" viewBox="0 0 20 14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 0h3.2l2.4 8.4L8 0h4l2.4 8.4L16.8 0H20l-4 14h-3.6L10 5.6 7.6 14H4L0 0Z"/>
        </svg>';
    }

    public function servicesWidgetView(): ?string
    {
        return 'integrations.whmcs.services-widget';
    }
}
