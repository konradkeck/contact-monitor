<?php

namespace App\Http\Controllers;

use App\Models\SynchronizerServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SynchronizerServerController extends Controller
{
    private function resolveUrl(string $url): string
    {
        return preg_replace('#^(https?://)(?:localhost|127\.0\.0\.1)#', '$1host.docker.internal', $url);
    }

    /** Test connection using raw url+token from request body (for the form) */
    public function test(Request $request)
    {
        $url   = rtrim($this->resolveUrl($request->input('url', '')), '/');
        $token = $request->input('api_token', '');

        if (!$url || !$token) {
            return response()->json(['ok' => false, 'error' => 'URL and API token are required.']);
        }

        try {
            $res = Http::withToken($token)
                ->baseUrl($url . '/api')
                ->timeout(5)
                ->acceptJson()
                ->get('/connections');

            if ($res->successful()) {
                $count = count($res->json('connections', []));
                return response()->json(['ok' => true, 'message' => "Connected. {$count} integration(s) found."]);
            }

            return response()->json(['ok' => false, 'error' => "HTTP {$res->status()}: " . $res->body()]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Ping a saved server (for the index status check) */
    public function ping(SynchronizerServer $server)
    {
        try {
            $res = Http::withToken($server->api_token)
                ->baseUrl(rtrim($this->resolveUrl($server->url), '/') . '/api')
                ->timeout(5)
                ->acceptJson()
                ->get('/connections');

            if ($res->successful()) {
                $count = count($res->json('connections', []));
                return response()->json(['ok' => true, 'message' => "{$count} integration(s)"]);
            }

            return response()->json(['ok' => false, 'error' => "HTTP {$res->status()}"]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function index()
    {
        $servers = SynchronizerServer::orderBy('name')->get();
        return view('synchronizer.servers.index', compact('servers'));
    }

    public function create()
    {
        return view('synchronizer.servers.form', ['server' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'url'       => 'required|url|max:500',
            'api_token' => 'required|string|max:500',
        ]);

        SynchronizerServer::create($data);

        return redirect()->route('synchronizer.servers.index')
            ->with('success', 'Server added.');
    }

    public function edit(SynchronizerServer $server)
    {
        return view('synchronizer.servers.form', compact('server'));
    }

    public function update(Request $request, SynchronizerServer $server)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'url'       => 'required|url|max:500',
            'api_token' => 'required|string|max:500',
        ]);

        $server->update($data);

        return redirect()->route('synchronizer.servers.index')
            ->with('success', 'Server updated.');
    }

    public function destroy(SynchronizerServer $server)
    {
        $server->delete();

        return redirect()->route('synchronizer.servers.index')
            ->with('success', 'Server deleted.');
    }
}
