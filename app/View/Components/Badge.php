<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Badge extends Component
{
    public string $cls;

    private const COLORS = [
        'gray'   => 'bg-gray-100 text-gray-700',
        'blue'   => 'bg-blue-100 text-blue-700',
        'green'  => 'bg-green-100 text-green-700',
        'yellow' => 'bg-yellow-100 text-yellow-700',
        'red'    => 'bg-red-100 text-red-700',
        'purple' => 'bg-purple-100 text-purple-700',
    ];

    public function __construct(public string $color = 'gray')
    {
        $this->cls = self::COLORS[$color] ?? self::COLORS['gray'];
    }

    public function render(): View
    {
        return view('components.badge');
    }
}
