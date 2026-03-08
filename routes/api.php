<?php

use App\Http\Controllers\Api\IngestController;
use App\Http\Controllers\SynchronizerWizardController;
use App\Models\PendingSynchronizerRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/ingest/batch', [IngestController::class, 'batch']);

Route::post('/synchronizer/register', function (Request $request) {
    $pending = PendingSynchronizerRegistration::where('token', $request->input('verify_token', ''))
        ->where('expires_at', '>', now())
        ->whereNull('registered_at')
        ->first();

    if (!$pending) {
        return response()->json(['ok' => false], 422);
    }

    $syncUrl   = $request->input('url', '');
    $apiToken  = $request->input('api_token', '');

    $ingestSecret = bin2hex(random_bytes(32));

    $pending->update([
        'registered_at'  => now(),
        'registered_url' => $syncUrl,
    ]);

    // Auto-create the server with a unique per-server ingest secret
    \App\Models\SynchronizerServer::create([
        'name'          => 'Synchronizer',
        'url'           => $syncUrl,
        'api_token'     => $apiToken,
        'ingest_secret' => $ingestSecret,
    ]);

    return response()->json(['ok' => true, 'ingest_secret' => $ingestSecret]);
});
