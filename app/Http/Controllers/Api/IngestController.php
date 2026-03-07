<?php

namespace App\Http\Controllers\Api;

use App\DataRelations\AutoResolver;
use App\Http\Controllers\Controller;
use App\Ingest\BatchProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngestController extends Controller
{
    public function batch(Request $request): JsonResponse
    {
        // Auth: shared secret
        $secret = config('ingest.secret');
        if (empty($secret) || $request->header('X-Ingest-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $body = $request->json()->all();

        // Basic structural validation
        if (empty($body['batch_id']) || !isset($body['items']) || !is_array($body['items'])) {
            return response()->json(['error' => 'Invalid batch payload'], 422);
        }

        if (empty($body['source_type']) || empty($body['source_slug'])) {
            return response()->json(['error' => 'source_type and source_slug are required'], 422);
        }

        // Validate each item has required fields
        foreach ($body['items'] as $i => $item) {
            foreach (['idempotency_key', 'type', 'system_type', 'system_slug', 'external_id', 'payload_hash', 'payload'] as $field) {
                if (!isset($item[$field])) {
                    return response()->json(['error' => "Item [{$i}] missing field: {$field}"], 422);
                }
            }
        }

        $stats = (new BatchProcessor())->ingest($body);

        // Auto-resolve after every batch so links are immediately available
        (new AutoResolver())->resolveAll();

        return response()->json([
            'ok'   => true,
            'data' => $stats,
        ]);
    }
}
