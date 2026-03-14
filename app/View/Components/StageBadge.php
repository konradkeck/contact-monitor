<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class StageBadge extends Component
{
    public string $color;

    private const MAP = [
        'lead'     => 'blue',
        'prospect' => 'purple',
        'trial'    => 'yellow',
        'active'   => 'green',
        'churned'  => 'red',
    ];

    public function __construct(public ?string $stage = null)
    {
        $this->color = self::MAP[strtolower($stage ?? '')] ?? 'gray';
    }

    public function render(): View
    {
        return view('components.stage-badge');
    }
}
