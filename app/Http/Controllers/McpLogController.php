<?php

namespace App\Http\Controllers;

use App\Models\McpLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class McpLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = McpLog::with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(50);

        $logs->getCollection()->transform(function ($log) {
            $log->created_at_human = $log->created_at?->diffForHumans();
            return $log;
        });

        $enabled         = (bool) SystemSetting::get('mcp_enabled', false);
        $externalEnabled = (bool) SystemSetting::get('mcp_external_enabled', false);
        $hasApiKey       = (bool) SystemSetting::get('mcp_api_key', null);
        $endpointUrl     = url('/api/mcp');

        return Inertia::render('McpServer', [
            'enabled'         => $enabled,
            'externalEnabled' => $externalEnabled,
            'hasApiKey'       => $hasApiKey,
            'endpointUrl'     => $endpointUrl,
            'tab'             => 'log',
            'logs'            => $logs,
        ]);
    }
}
