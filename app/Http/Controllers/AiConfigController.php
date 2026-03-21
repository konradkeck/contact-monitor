<?php

namespace App\Http\Controllers;

use App\Ai\Providers\AiProviderFactory;
use App\Models\AiCredential;
use App\Models\AiModelConfig;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AiConfigController extends Controller
{
    public function index(Request $request)
    {
        $credentials  = AiCredential::orderBy('provider')->orderBy('name')->get();
        $providers    = AiProviderFactory::providers();
        $modelConfigs = AiModelConfig::with('credential')->get()->keyBy('action_type');
        $actionTypes  = AiModelConfig::actionLabels();
        $activeTab    = $request->input('tab', 'credentials');

        return Inertia::render('AiConfig/Index', [
            'credentials'  => $credentials,
            'providers'    => $providers,
            'modelConfigs' => $modelConfigs,
            'actionTypes'  => $actionTypes,
            'activeTab'    => $activeTab,
        ]);
    }

    public function mcpServer()
    {
        $enabled         = (bool) SystemSetting::get('mcp_enabled', false);
        $externalEnabled = (bool) SystemSetting::get('mcp_external_enabled', false);
        $hasApiKey       = (bool) SystemSetting::get('mcp_api_key', null);
        $endpointUrl     = url('/api/mcp');

        return Inertia::render('McpServer', [
            'enabled'         => $enabled,
            'externalEnabled' => $externalEnabled,
            'hasApiKey'       => $hasApiKey,
            'endpointUrl'     => $endpointUrl,
            'tab'             => 'settings',
            'logs'            => null,
        ]);
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
