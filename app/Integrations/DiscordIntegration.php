<?php

namespace App\Integrations;

class DiscordIntegration extends BaseIntegration
{
    public function label(): string
    {
        return 'discord';
    }

    public function badgeCls(): string
    {
        return '';
    }

    public function badgeStyle(): ?string
    {
        return 'background:#5865F2';
    }

    public function badgeIconSvg(string $sizeClass = 'w-full h-full'): string
    {
        return '<img src="/img/integrations/discord.png" class="'.$sizeClass.' object-contain" alt="Discord">';
    }
}
