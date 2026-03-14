<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class ScoreRing extends Component
{
    public string $scClr;
    public float $r = 26;
    public float $circ;
    public float $offset;

    private const SCORE_COLORS = [
        1  => '#ef4444', 2  => '#f97316', 3  => '#f59e0b', 4  => '#eab308',
        5  => '#84cc16', 6  => '#4ade80', 7  => '#22c55e', 8  => '#16a34a',
        9  => '#15803d', 10 => '#166534',
    ];

    public function __construct(public int $score)
    {
        $this->scClr  = self::SCORE_COLORS[$score] ?? '#e5e7eb';
        $this->circ   = 2 * M_PI * $this->r;
        $this->offset = $this->circ * (1 - $score / 10);
    }

    public function render(): View
    {
        return view('components.score-ring');
    }
}
