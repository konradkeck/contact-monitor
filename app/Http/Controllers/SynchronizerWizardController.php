<?php

namespace App\Http\Controllers;

use App\Models\PendingSynchronizerRegistration;
use App\Models\SynchronizerServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SynchronizerWizardController extends Controller
{
    /** Step 1 – choose mode */
    public function step1()
    {
        return Inertia::render('Synchronizer/Wizard/Step1');
    }

    /** Step 2a – Configure New Server */
    public function configureNew(Request $request)
    {
        // Create or reuse a pending registration
        $pending = PendingSynchronizerRegistration::where('expires_at', '>', now())
            ->whereNull('registered_at')
            ->latest()
            ->first();

        if (! $pending) {
            $pending = PendingSynchronizerRegistration::create([
                'token' => Str::random(48),
                'api_token' => bin2hex(random_bytes(32)),
                'expires_at' => now()->addHours(2),
            ]);
        }

        return Inertia::render('Synchronizer/Wizard/ConfigureNew', [
            'installCmd' => route('synchronizer.wizard.install-script', $pending->token),
            'pollUrl'    => route('synchronizer.wizard.poll', $pending->token),
        ]);
    }

    /** Install script (bash) – downloaded as part of the one-liner */
    public function installScript(string $token)
    {
        $pending = PendingSynchronizerRegistration::where('token', $token)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $cmUrl = rtrim(config('app.url'), '/');
        $ingestUrl = str_replace(['http://localhost', 'http://127.0.0.1'], 'http://host.docker.internal', $cmUrl);
        $regUrl = $ingestUrl.'/api/synchronizer/register';
        $secret = config('ingest.secret', '');

        $script = view('synchronizer.wizard.install-script', [
            'pending' => $pending,
            'cmUrl' => $ingestUrl,
            'regUrl' => $regUrl,
            'secret' => $secret,
        ])->render();

        return response($script, 200, ['Content-Type' => 'text/plain']);
    }

    /** Poll endpoint – returns whether synchronizer has registered */
    public function pollRegistration(string $token)
    {
        $pending = PendingSynchronizerRegistration::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (! $pending) {
            return response()->json(['status' => 'expired']);
        }

        if ($pending->isRegistered()) {
            return response()->json(['status' => 'registered', 'url' => $pending->registered_url]);
        }

        return response()->json(['status' => 'pending']);
    }

    /** Step 2b – Connect to Existing Server */
    public function connectExisting()
    {
        return Inertia::render('Synchronizer/Wizard/ConnectExisting');
    }

    /** AJAX: test + inspect existing server */
    public function inspectExisting(Request $request)
    {
        $url = rtrim($this->normalizeUrl($request->input('url', '')), '/');
        $token = $request->input('api_token', '');
        $name = $request->input('name', '');

        if (! $url || ! $token) {
            return response()->json(['ok' => false, 'error' => 'URL and API token are required.']);
        }

        // 1. Test connection
        try {
            $res = Http::withToken($token)->baseUrl($url.'/api')->timeout(5)->acceptJson()
                ->get('/connections');
            if (! $res->successful()) {
                return response()->json(['ok' => false, 'error' => "HTTP {$res->status()}: cannot connect."]);
            }
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }

        // 2. Get current settings
        try {
            $settings = Http::withToken($token)->baseUrl($url.'/api')->timeout(5)->acceptJson()
                ->get('/settings')->json();
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => 'Connected but could not read settings: '.$e->getMessage()]);
        }

        $currentIngestUrl = $settings['ingest_url'] ?? '';
        $ourUrl = rtrim(config('app.url'), '/');

        // Normalize for comparison
        $currentNorm = rtrim(str_replace(['host.docker.internal', '127.0.0.1'], 'localhost', $currentIngestUrl), '/');
        $ourNorm = rtrim(str_replace(['host.docker.internal', '127.0.0.1'], 'localhost', $ourUrl), '/');

        $pointsElsewhere = $currentIngestUrl && $currentNorm !== $ourNorm;

        return response()->json([
            'ok' => true,
            'current_ingest' => $currentIngestUrl,
            'points_elsewhere' => $pointsElsewhere,
            'our_url' => $ourUrl,
        ]);
    }

    /** AJAX: apply settings + save server + reset runs */
    public function connectSave(Request $request)
    {
        $url = rtrim($this->normalizeUrl($request->input('url', '')), '/');
        $token = $request->input('api_token', '');
        $name = $request->input('name', 'Synchronizer');

        $newIngestUrl = str_replace(
            ['http://localhost', 'http://127.0.0.1'],
            'http://host.docker.internal',
            rtrim(config('app.url'), '/')
        );
        $ingestSecret = config('ingest.secret', '');

        // Update synchronizer settings
        try {
            $res = Http::withToken($token)->baseUrl($url.'/api')->timeout(10)->acceptJson()
                ->put('/settings', ['ingest_url' => $newIngestUrl, 'ingest_secret' => $ingestSecret]);
            if (! $res->successful()) {
                return response()->json(['ok' => false, 'error' => 'Could not update synchronizer settings.']);
            }
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }

        // Reset runs
        try {
            Http::withToken($token)->baseUrl($url.'/api')->timeout(10)->acceptJson()->post('/reset-runs');
        } catch (\Exception) {
        }

        // Save server
        $server = SynchronizerServer::create([
            'name' => $name,
            'url' => $request->input('url', ''),  // store user-facing URL
            'api_token' => $token,
        ]);

        return response()->json(['ok' => true, 'redirect' => route('synchronizer.index')]);
    }

    private function normalizeUrl(string $url): string
    {
        return preg_replace('#^(https?://)(?:localhost|127\.0\.0\.1)#', '$1host.docker.internal', $url);
    }
}
