<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SynchronizerController extends Controller
{
    private function api(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken(env('SYNCHRONIZER_API_TOKEN'))
            ->baseUrl(rtrim(env('SYNCHRONIZER_URL', 'http://127.0.0.1:8011'), '/') . '/api')
            ->timeout(10)
            ->acceptJson();
    }

    public function index()
    {
        try {
            $response    = $this->api()->get('/connections');
            $connections = $response->json('connections', []);
            $error       = $response->failed() ? 'Could not reach Synchronizer.' : null;
        } catch (\Exception $e) {
            $connections = [];
            $error       = 'Could not connect to Synchronizer: ' . $e->getMessage();
        }

        return view('synchronizer.index', compact('connections', 'error'));
    }

    public function show(int $id)
    {
        try {
            $conn = $this->api()->get("/connections/{$id}")->json('connection');
            $runs = $this->api()->get("/connections/{$id}/runs")->json('runs', []);
        } catch (\Exception $e) {
            abort(503, 'Could not connect to Synchronizer: ' . $e->getMessage());
        }

        if (!$conn) abort(404);

        return view('synchronizer.show', compact('conn', 'runs'));
    }

    public function run(Request $request, int $id): JsonResponse
    {
        try {
            $mode = in_array($request->input('mode'), ['partial', 'full']) ? $request->input('mode') : 'partial';
            $res  = $this->api()->post("/connections/{$id}/run", ['mode' => $mode]);
            return response()->json($res->json(), $res->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function stop(int $id): JsonResponse
    {
        try {
            $res = $this->api()->post("/connections/{$id}/stop");
            return response()->json($res->json(), $res->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function killAll(): JsonResponse
    {
        try {
            $res = $this->api()->post('/kill-all');
            return response()->json($res->json(), $res->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function runStatus(int $runId): JsonResponse
    {
        try {
            $res = $this->api()->get("/runs/{$runId}");
            return response()->json($res->json(), $res->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function runLogs(int $runId): JsonResponse
    {
        try {
            $res = $this->api()->get("/runs/{$runId}/logs");
            return response()->json($res->json(), $res->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function runs(Request $request): JsonResponse
    {
        try {
            $res = $this->api()->get('/runs', $request->only(['status', 'since', 'page']));
            return response()->json($res->json(), $res->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }
}
