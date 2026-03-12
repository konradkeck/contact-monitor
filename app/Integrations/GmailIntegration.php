<?php

namespace App\Integrations;

class GmailIntegration extends BaseIntegration
{
    public function label(): string { return 'Gmail'; }
    public function badgeCls(): string { return 'bg-white'; }
    public function badgePadding(): string { return ''; }

    public function badgeIconSvg(string $sizeClass = 'w-full h-full'): string
    {
        return '<img src="/img/integrations/gmail.png" class="'.$sizeClass.' object-contain" alt="Gmail">';
    }
}
