<?php

namespace App\Http\Controllers;

use App\Models\McpLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class McpLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = McpLog::with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(50);

        $enabled         = (bool) SystemSetting::get('mcp_enabled', false);
        $externalEnabled = (bool) SystemSetting::get('mcp_external_enabled', false);
        $hasApiKey       = (bool) SystemSetting::get('mcp_api_key', null);
        $endpointUrl     = url('/api/mcp');
        $tab             = 'log';

        return view('configuration.ai.mcp-server', compact(
            'enabled', 'externalEnabled', 'hasApiKey', 'endpointUrl',
            'tab', 'logs'
        ));
    }
}
