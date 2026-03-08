<?php

namespace App\Integrations;

abstract class BaseIntegration
{
    /** Human-readable name shown in UI */
    abstract public function label(): string;

    /** Tailwind classes for the small badge wrapper (bg + text color) */
    abstract public function badgeCls(): string;

    /**
     * Small SVG icon for inline badges — MUST use currentColor so the badge
     * text/bg classes control the color. Size controlled by $sizeClass.
     */
    abstract public function badgeIconSvg(string $sizeClass = 'w-3 h-3'): string;

    /** Optional inline style on the badge wrapper (for brand colors not in Tailwind). */
    public function badgeStyle(): ?string
    {
        return null;
    }

    /**
     * Renders the full badge HTML (wrapper + icon [+ label]) at any icon size.
     * This is the canonical render used by x-channel-badge and _type_icon alike —
     * guaranteeing a visually identical appearance everywhere.
     */
    public function iconHtml(string $iconSizeClass = 'w-3 h-3', bool $label = false): string
    {
        $style    = $this->badgeStyle() ? ' style="'.e($this->badgeStyle()).'"' : '';
        $labelTag = $label ? '<span>'.e($this->label()).'</span>' : '';
        return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium '
            .$this->badgeCls().'"'.$style.'>'
            .$this->badgeIconSvg($iconSizeClass)
            .$labelTag
            .'</span>';
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
