<?php

namespace App\Integrations;

abstract class BaseIntegration
{
    /** Human-readable name shown in UI */
    abstract public function label(): string;

    /** Tailwind classes for the square badge wrapper (bg + text color) */
    abstract public function badgeCls(): string;

    /**
     * Inner icon HTML — SVG or <img> — sized via $sizeClass.
     * When called from iconHtml(), $sizeClass is always 'w-full h-full'.
     */
    abstract public function badgeIconSvg(string $sizeClass = 'w-full h-full'): string;

    /** Optional inline style on the badge wrapper (for brand colors not in Tailwind). */
    public function badgeStyle(): ?string
    {
        return null;
    }

    /** Tailwind padding class for the badge wrapper. Override to adjust per-integration. */
    public function badgePadding(): string
    {
        return 'p-[3px]';
    }

    /**
     * Renders a square badge: a rounded box containing the icon.
     * $size — Tailwind size class for the box, e.g. 'w-5 h-5'.
     * $label — when true, appends the integration label as text next to the box.
     */
    public function iconHtml(string $size = 'w-5 h-5', bool $label = false): string
    {
        $style = $this->badgeStyle() ? ' style="'.e($this->badgeStyle()).'"' : '';
        $pad = $this->badgePadding() ? $this->badgePadding().' ' : '';
        $cls = 'inline-flex items-center justify-center shrink-0 rounded overflow-hidden '.$pad.$this->badgeCls();

        $badge = '<span class="'.trim($cls).' '.$size.'"'.$style.'>'
            .$this->badgeIconSvg('w-full h-full')
            .'</span>';

        if ($label) {
            return '<span class="inline-flex items-center gap-1.5">'
                .$badge
                .'<span class="text-xs font-medium">'.e($this->label()).'</span>'
                .'</span>';
        }

        return $badge;
    }

    /**
     * Blade view for the services widget panel, or null if not applicable.
     * Receives: $sys (array), $slug (string)
     */
    public function servicesWidgetView(): ?string
    {
        return null;
    }

    /** Transform $sys data before passing to the services widget view. */
    public function prepareWidgetData(array $sys, string $slug): array
    {
        return compact('sys', 'slug');
    }
}
