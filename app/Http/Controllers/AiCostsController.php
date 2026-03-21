<?php

namespace App\Http\Controllers;

use App\Ai\Pricing\PricingRegistry;
use App\Models\AiUsageLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AiCostsController extends Controller
{
    public function index(Request $request)
    {
        $query = AiUsageLog::query()->orderByDesc('created_at');

        if ($actionType = $request->input('action_type')) {
            $query->where('action_type', $actionType);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->paginate(50)->withQueryString();

        $totals = AiUsageLog::selectRaw(
            'SUM(input_tokens) as total_input, SUM(output_tokens) as total_output, SUM(cost_input_usd + cost_output_usd) as total_cost'
        )->first();

        $actionTypes = \App\Models\AiModelConfig::actionLabels();

        return Inertia::render('AiCosts', [
            'logs'        => $logs,
            'totals'      => [
                'total_input'  => (int) ($totals->total_input ?? 0),
                'total_output' => (int) ($totals->total_output ?? 0),
                'total_cost'   => (float) ($totals->total_cost ?? 0),
            ],
            'actionTypes' => $actionTypes,
            'filters'     => [
                'action_type' => $request->input('action_type', ''),
                'from'        => $request->input('from', ''),
                'to'          => $request->input('to', ''),
            ],
        ]);
    }

    public function pricingIndex()
    {
        $defaults  = PricingRegistry::allDefaults();
        $overrides = \App\Models\SystemSetting::get('ai_pricing_overrides', []) ?? [];

        return Inertia::render('AiCostsPricing', [
            'defaults'  => $defaults,
            'overrides' => $overrides,
        ]);
    }

    public function pricingUpdate(Request $request)
    {
        $data = $request->validate([
            'overrides'               => ['nullable', 'array'],
            'overrides.*.model'       => ['required', 'string'],
            'overrides.*.input_price' => ['required', 'numeric', 'min:0'],
            'overrides.*.output_price'=> ['required', 'numeric', 'min:0'],
        ]);

        PricingRegistry::clearCache();
        $newOverrides = [];

        foreach ($data['overrides'] ?? [] as $row) {
            $newOverrides[$row['model']] = [
                'input'  => (float) $row['input_price'],
                'output' => (float) $row['output_price'],
            ];
        }

        \App\Models\SystemSetting::set('ai_pricing_overrides', $newOverrides);

        return redirect()->route('ai-costs.pricing')
            ->with('success', 'Pricing overrides saved.');
    }
}
