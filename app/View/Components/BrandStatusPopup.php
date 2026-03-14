<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class BrandStatusPopup extends Component
{
    public ?int $bpSc;
    public string $bpClr;
    public float $bpR = 26;
    public float $bpCirc;
    public float $bpOff;
    public string $bpBadge;

    private const SCORE_COLORS = [
        1  => '#ef4444', 2  => '#f97316', 3  => '#f59e0b', 4  => '#eab308',
        5  => '#84cc16', 6  => '#4ade80', 7  => '#22c55e', 8  => '#16a34a',
        9  => '#15803d', 10 => '#166534',
    ];

    public function __construct(
        public object $status,
        public object $bp,
        public object $company,
    ) {
        $this->bpSc   = $status->evaluation_score;
        $this->bpClr  = $this->bpSc ? (self::SCORE_COLORS[$this->bpSc] ?? '#e5e7eb') : '#e5e7eb';
        $this->bpCirc = 2 * M_PI * $this->bpR;
        $this->bpOff  = $this->bpSc ? $this->bpCirc * (1 - $this->bpSc / 10) : $this->bpCirc;
        $this->bpBadge = match (strtolower($status->stage)) {
            'lead'     => 'bg-blue-100 text-blue-700',
            'prospect' => 'bg-purple-100 text-purple-700',
            'trial'    => 'bg-yellow-100 text-yellow-800',
            'active'   => 'bg-green-100 text-green-700',
            'churned'  => 'bg-red-100 text-red-700',
            default    => 'bg-gray-100 text-gray-600',
        };
    }

    public function render(): View
    {
        return view('components.brand-status-popup');
    }
}
