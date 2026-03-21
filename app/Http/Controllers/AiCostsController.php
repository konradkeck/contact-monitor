<?php

namespace App\Http\Controllers;

use App\Ai\Pricing\PricingRegistry;
use App\Models\AiUsageLog;
use Illuminate\Http\Request;

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

        return view('ai-costs.index', compact('logs', 'totals', 'actionTypes'));
    }

    public function pricingIndex()
    {
        $defaults  = PricingRegistry::allDefaults();
        $overrides = \App\Models\SystemSetting::get('ai_pricing_overrides', []) ?? [];

        return view('ai-costs.pricing', compact('defaults', 'overrides'));
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
