<?php

namespace App\Http\Controllers;

use App\Ai\CompanyAnalysis\DomainSyncService;
use App\Models\AnalysisStep;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyAnalysisConfigController extends Controller
{
    public function index()
    {
        $steps = AnalysisStep::ordered()->get();
        $domainSync = (new DomainSyncService())->stats();

        return Inertia::render('CompanyAnalysisConfig/Index', [
            'steps' => $steps,
            'domainSync' => $domainSync,
        ]);
    }

    public function storeStep(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => 'required|string|max:80|regex:/^[a-z][a-z0-9_]*$/|unique:analysis_steps,key',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'prompt_template' => 'required|string',
            'is_enabled' => 'boolean',
        ]);

        $data['sort_order'] = (AnalysisStep::max('sort_order') ?? 0) + 10;

        AnalysisStep::create($data);

        return redirect()->route('company-analysis.config.index')->with('success', 'Step created.');
    }

    public function updateStep(Request $request, AnalysisStep $step): RedirectResponse
    {
        $data = $request->validate([
            'key' => 'required|string|max:80|regex:/^[a-z][a-z0-9_]*$/|unique:analysis_steps,key,' . $step->id,
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'prompt_template' => 'required|string',
            'is_enabled' => 'boolean',
        ]);

        $step->update($data);

        return redirect()->route('company-analysis.config.index')->with('success', 'Step updated.');
    }

    public function destroyStep(AnalysisStep $step): RedirectResponse
    {
        $step->delete();

        return redirect()->route('company-analysis.config.index')->with('success', 'Step deleted.');
    }

    public function reorderSteps(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'steps' => 'required|array',
            'steps.*.id' => 'required|integer|exists:analysis_steps,id',
            'steps.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($data['steps'] as $item) {
            AnalysisStep::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return redirect()->route('company-analysis.config.index')->with('success', 'Order updated.');
    }

    public function domainSync(): RedirectResponse
    {
        $service = new DomainSyncService();
        $count = $service->sync();

        return redirect()->route('company-analysis.config.index')
            ->with('success', "Domain lists synced. {$count} domains loaded.");
    }

    public function domainSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'auto_enabled' => 'boolean',
            'sources' => 'nullable|array',
            'sources.disposable' => 'nullable|url|max:500',
            'sources.free_email' => 'nullable|url|max:500',
        ]);

        SystemSetting::set('domain_sync_auto_enabled', $data['auto_enabled'] ?? false);

        if (isset($data['sources'])) {
            SystemSetting::set('domain_sync_sources', $data['sources']);
        }

        return redirect()->route('company-analysis.config.index')->with('success', 'Settings saved.');
    }
}
