<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mcp\McpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpController extends Controller
{
    public function handle(Request $request, McpServer $server): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => ['code' => -32700, 'message' => 'Parse error'],
            ]);
        }

        // Batch support (array of requests)
        if (isset($body[0])) {
            $responses = array_map(fn($req) => $server->handle($req), $body);
            return response()->json($responses);
        }

        return response()->json($server->handle($body));
    }
}
