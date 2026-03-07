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

    public function create()
    {
        try {
            // Fetch whmcs connections for metricscube linking
            $whmcsConnections = collect($this->api()->get('/connections')->json('connections', []))
                ->where('type', 'whmcs')->values();
        } catch (\Exception $e) {
            $whmcsConnections = collect();
        }

        return view('synchronizer.form', [
            'conn'            => null,
            'whmcsConnections'=> $whmcsConnections,
        ]);
    }

    public function store(\Illuminate\Http\Request $request)
    {
        try {
            $payload = $this->buildPayload($request);
            $res     = $this->api()->post('/connections', $payload);

            if ($res->status() === 422) {
                return back()->withErrors($res->json('errors', []))->withInput();
            }

            $conn = $res->json('connection');
            return redirect()->route('synchronizer.connections.show', $conn['id'])
                ->with('success', 'Connection created.');
        } catch (\Exception $e) {
            return back()->withErrors(['api' => $e->getMessage()])->withInput();
        }
    }

    public function edit(int $id)
    {
        try {
            $conn             = $this->api()->get("/connections/{$id}")->json('connection');
            $whmcsConnections = collect($this->api()->get('/connections')->json('connections', []))
                ->where('type', 'whmcs')->values();
        } catch (\Exception $e) {
            abort(503, 'Could not connect to Synchronizer: ' . $e->getMessage());
        }

        if (!$conn) abort(404);

        return view('synchronizer.form', compact('conn', 'whmcsConnections'));
    }

    public function update(\Illuminate\Http\Request $request, int $id)
    {
        try {
            $payload = $this->buildPayload($request);
            $res     = $this->api()->put("/connections/{$id}", $payload);

            if ($res->status() === 422) {
                return back()->withErrors($res->json('errors', []))->withInput();
            }

            return redirect()->route('synchronizer.connections.show', $id)
                ->with('success', 'Connection updated.');
        } catch (\Exception $e) {
            return back()->withErrors(['api' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(int $id)
    {
        try {
            $this->api()->delete("/connections/{$id}");
        } catch (\Exception $e) {
            // ignore
        }

        return redirect()->route('synchronizer.index')->with('success', 'Connection deleted.');
    }

    public function duplicate(int $id)
    {
        try {
            $res  = $this->api()->post("/connections/{$id}/duplicate");
            $conn = $res->json('connection');
            return redirect()->route('synchronizer.connections.edit', $conn['id']);
        } catch (\Exception $e) {
            return back()->withErrors(['api' => $e->getMessage()]);
        }
    }

    private function buildPayload(\Illuminate\Http\Request $request): array
    {
        $data = $request->all();

        // Convert checkbox booleans
        $data['is_active']             = $request->boolean('is_active');
        $data['schedule_enabled']      = $request->boolean('schedule_enabled');
        $data['schedule_full_enabled'] = $request->boolean('schedule_full_enabled');

        // Array fields that may come as textarea (newline-separated)
        foreach (['settings.entities', 'settings.excluded_labels', 'settings.excluded_mailboxes',
                  'settings.guild_allowlist', 'settings.channel_allowlist'] as $path) {
            $val = data_get($data, $path, '');
            if (is_string($val)) {
                data_set($data, $path, array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $val)))));
            }
        }

        return $data;
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
