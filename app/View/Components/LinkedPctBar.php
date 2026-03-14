<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class LinkedPctBar extends Component
{
    public string $color;

    public function __construct(public int $pct)
    {
        $this->color = match (true) {
            $pct >= 90 => '#22c55e',
            $pct >= 70 => '#f59e0b',
            $pct >= 50 => '#f97316',
            default    => '#ef4444',
        };
    }

    public function render(): View
    {
        return view('components.linked-pct-bar');
    }
}
