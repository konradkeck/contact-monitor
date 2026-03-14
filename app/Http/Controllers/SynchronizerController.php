<?php

namespace App\Http\Controllers;

use App\Models\SynchronizerServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SynchronizerController extends Controller
{
    private function normalizeUrl(string $url): string
    {
        return preg_replace('#^(https?://)(?:localhost|127\.0\.0\.1)#', '$1host.docker.internal', $url);
    }

    private function resolveServer(?int $serverId = null): array
    {
        $servers = SynchronizerServer::orderBy('name')->get();

        if ($servers->isNotEmpty()) {
            if ($serverId) {
                $server = $servers->firstWhere('id', $serverId) ?? $servers->first();
            } else {
                $server = $servers->first();
            }

            return [
                'url' => rtrim($this->normalizeUrl($server->url), '/').'/api',
                'token' => $server->api_token,
                'servers' => $servers,
                'activeServer' => $server,
            ];
        }

        return [
            'url' => rtrim($this->normalizeUrl(config('services.synchronizer.url')), '/').'/api',
            'token' => config('services.synchronizer.token'),
            'servers' => collect(),
            'activeServer' => null,
        ];
    }

    private function api(?int $serverId = null): \Illuminate\Http\Client\PendingRequest
    {
        $id = $serverId ?? (request()->integer('server') ?: null);
        $cfg = $this->resolveServer($id);

        return Http::withToken($cfg['token'])
            ->baseUrl($cfg['url'])
            ->timeout(10)
            ->acceptJson();
    }

    public function index(Request $request)
    {
        $serverId = $request->integer('server') ?: null;
        $cfg = $this->resolveServer($serverId);

        try {
            $response = $this->api($serverId)->get('/connections');
            $connections = $response->json('connections', []);
            $error = $response->failed() ? 'Could not reach Synchronizer.' : null;
        } catch (\Exception $e) {
            $connections = [];
            $error = 'Could not connect to Synchronizer: '.$e->getMessage();
        }

        return view('synchronizer.index', [
            'connections' => $connections,
            'error' => $error,
            'servers' => $cfg['servers'],
            'activeServer' => $cfg['activeServer'],
        ]);
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
            'conn' => null,
            'whmcsConnections' => $whmcsConnections,
        ]);
    }

    public function store(\Illuminate\Http\Request $request)
    {
        try {
            $payload = $this->buildPayload($request);
            $res = $this->api()->post('/connections', $payload);

            if ($res->status() === 422) {
                return back()->withErrors($res->json('errors', []))->withInput();
            }

            $conn = $res->json('connection');

            return redirect()->route('synchronizer.index')
                ->with('success', 'Connection created.');
        } catch (\Exception $e) {
            return back()->withErrors(['api' => $e->getMessage()])->withInput();
        }
    }

    public function edit(string $id)
    {
        try {
            $conn = $this->api()->get("/connections/{$id}")->json('connection');
            $whmcsConnections = collect($this->api()->get('/connections')->json('connections', []))
                ->where('type', 'whmcs')->values();
        } catch (\Exception $e) {
            abort(503, 'Could not connect to Synchronizer: '.$e->getMessage());
        }

        if (! $conn) {
            abort(404);
        }

        return view('synchronizer.form', compact('conn', 'whmcsConnections'));
    }

    public function update(\Illuminate\Http\Request $request, string $id)
    {
        try {
            $payload = $this->buildPayload($request);
            $res = $this->api()->put("/connections/{$id}", $payload);

            if ($res->status() === 422) {
                return back()->withErrors($res->json('errors', []))->withInput();
            }

            return redirect()->route('synchronizer.connections.show', $id)
                ->with('success', 'Connection updated.');
        } catch (\Exception $e) {
            return back()->withErrors(['api' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(string $id)
    {
        try {
            $this->api()->delete("/connections/{$id}");
        } catch (\Exception $e) {
            // ignore
        }

        return redirect()->route('synchronizer.index')->with('success', 'Connection deleted.');
    }

    public function duplicate(string $id)
    {
        try {
            $res = $this->api()->post("/connections/{$id}/duplicate");
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
        $data['is_active'] = $request->boolean('is_active');
        $data['schedule_enabled'] = $request->boolean('schedule_enabled');
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

    public function show(string $id)
    {
        try {
            $conn = $this->api()->get("/connections/{$id}")->json('connection');
            $runs = $this->api()->get("/connections/{$id}/runs")->json('runs', []);
        } catch (\Exception $e) {
            abort(503, 'Could not connect to Synchronizer: '.$e->getMessage());
        }

        if (! $conn) {
            abort(404);
        }

        return view('synchronizer.show', compact('conn', 'runs'));
    }

    public function testConnection(Request $request): JsonResponse
    {
        try {
            $res = $this->api()->post('/connections/test', $request->all());

            return response()->json($res->json(), $res->status());
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 503);
        }
    }

    public function run(Request $request, string $id): JsonResponse
    {
        try {
            $mode = in_array($request->input('mode'), ['partial', 'full']) ? $request->input('mode') : 'partial';
            $res = $this->api()->post("/connections/{$id}/run", ['mode' => $mode]);

            return response()->json($res->json(), $res->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function stop(string $id): JsonResponse
    {
        try {
            $res = $this->api()->post("/connections/{$id}/stop");

            return response()->json($res->json(), $res->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function runAll(Request $request): JsonResponse
    {
        try {
            $mode = in_array($request->input('mode'), ['partial', 'full']) ? $request->input('mode') : 'partial';
            $res = $this->api()->post('/run-all', ['mode' => $mode]);

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

    /** Lightweight status poll — returns {connection_id: {status, run_id}} for all connections */
    public function connectionStatuses(Request $request): JsonResponse
    {
        try {
            $res = $this->api($request->integer('server') ?: null)->get('/connections');
            $connections = $res->json('connections', []);
            $statuses = [];
            foreach ($connections as $conn) {
                $run = $conn['latest_run'] ?? null;
                $statuses[$conn['id']] = [
                    'status' => $run['status'] ?? null,
                    'run_id' => $run['id'] ?? null,
                ];
            }

            return response()->json(['statuses' => $statuses]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }
}
