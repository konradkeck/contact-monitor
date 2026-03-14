<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class BrandStatusCell extends Component
{
    public string $stageBadge;
    public string $cellBg;
    public ?int $sc;
    public string $scClr;
    public string $scTxtClr;

    private const SCORE_COLORS = [
        1  => '#ef4444', 2  => '#f97316', 3  => '#f59e0b', 4  => '#eab308',
        5  => '#84cc16', 6  => '#4ade80', 7  => '#22c55e', 8  => '#16a34a',
        9  => '#15803d', 10 => '#166534',
    ];

    public function __construct(
        public object $status,
        public int $companyId,
        public int $bpId,
    ) {
        [$this->stageBadge, $this->cellBg] = match (strtolower($status->stage)) {
            'lead'     => ['bg-blue-100 text-blue-700', 'bg-blue-50'],
            'prospect' => ['bg-purple-100 text-purple-700', 'bg-purple-50'],
            'trial'    => ['bg-yellow-100 text-yellow-800', 'bg-yellow-50'],
            'active'   => ['bg-green-100 text-green-700', 'bg-green-50'],
            'churned'  => ['bg-red-100 text-red-700', 'bg-red-50'],
            default    => ['bg-gray-100 text-gray-600', 'bg-gray-50'],
        };
        $this->sc       = $status->evaluation_score;
        $this->scClr    = $this->sc ? (self::SCORE_COLORS[$this->sc] ?? '#e5e7eb') : '#e5e7eb';
        $this->scTxtClr = ($this->sc && $this->sc >= 3 && $this->sc <= 6) ? '#374151' : '#ffffff';
    }

    public function render(): View
    {
        return view('components.brand-status-cell');
    }
}
