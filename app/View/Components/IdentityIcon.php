<?php

namespace App\View\Components;

class IdentityIcon
{
    /**
     * Return an appropriate href for the given identity type and value.
     */
    public static function hrefFor(string $type, ?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return match ($type) {
            'email' => 'mailto:' . $value,
            default => null,
        };
    }
}
