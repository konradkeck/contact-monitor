<?php

namespace App\Http\Controllers;

use App\Ai\Providers\AiProviderFactory;
use App\Models\AiCredential;
use App\Models\AiModelConfig;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiConfigController extends Controller
{
    public function index(Request $request): View
    {
        $credentials  = AiCredential::orderBy('provider')->orderBy('name')->get();
        $providers    = AiProviderFactory::providers();
        $modelConfigs = AiModelConfig::with('credential')->get()->keyBy('action_type');
        $actionTypes  = AiModelConfig::actionLabels();
        $activeTab    = $request->input('tab', 'credentials');

        return view('configuration.ai.index', compact(
            'credentials', 'providers',
            'modelConfigs', 'actionTypes', 'activeTab'
        ));
    }

    public function mcpServer(): View
    {
        $enabled         = (bool) SystemSetting::get('mcp_enabled', false);
        $externalEnabled = (bool) SystemSetting::get('mcp_external_enabled', false);
        $hasApiKey       = (bool) SystemSetting::get('mcp_api_key', null);
        $endpointUrl     = url('/api/mcp');
        $tab             = 'settings';
        $logs            = null;

        return view('configuration.ai.mcp-server', compact(
            'enabled', 'externalEnabled', 'hasApiKey', 'endpointUrl',
            'tab', 'logs'
        ));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        SystemSetting::set('mcp_enabled', (bool) $request->input('mcp_enabled', false));
        SystemSetting::set('mcp_external_enabled', (bool) $request->input('mcp_external_enabled', false));

        return back()->with('success', 'Settings saved.');
    }

    public function regenerateKey(): RedirectResponse
    {
        $raw = bin2hex(random_bytes(32));
        SystemSetting::set('mcp_api_key', hash('sha256', $raw));

        return back()->with('api_key_plain', $raw)
            ->with('success', 'API key regenerated. Copy it now — it will not be shown again.');
    }
}
