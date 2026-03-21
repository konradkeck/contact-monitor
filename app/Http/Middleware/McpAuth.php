<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class McpAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SystemSetting::get('mcp_enabled', false)) {
            return $this->mcpError($request, -32002, 'MCP server is disabled');
        }

        if ($this->isLocalRequest($request)) {
            return $next($request);
        }

        if (! SystemSetting::get('mcp_external_enabled', false)) {
            return $this->mcpError($request, -32001, 'External MCP access is disabled');
        }

        $storedKey = SystemSetting::get('mcp_api_key', null);
        if (! $storedKey) {
            return $this->mcpError($request, -32001, 'No API key configured');
        }

        $bearer = $request->bearerToken();
        if (! $bearer || ! hash_equals($storedKey, hash('sha256', $bearer))) {
            return $this->mcpError($request, -32001, 'Unauthorized');
        }

        return $next($request);
    }

    private function isLocalRequest(Request $request): bool
    {
        $ip = $request->ip();
        return in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)
            || str_starts_with($ip ?? '', '172.')   // Docker internal networks
            || str_starts_with($ip ?? '', '10.');    // Docker internal networks
    }

    private function mcpError(Request $request, int $code, string $message): Response
    {
        $id = null;
        try {
            $body = json_decode($request->getContent(), true);
            $id   = $body['id'] ?? null;
        } catch (\Throwable) {
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => ['code' => $code, 'message' => $message],
        ], 200); // MCP always returns 200, error is in body
    }
}
