<?php

namespace App\Http\Controllers;

use App\Ai\Providers\AiProviderFactory;
use App\Models\AiCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AiCredentialController extends Controller
{
    public function create()
    {
        $providers = AiProviderFactory::providers();

        return view('configuration.ai.credential-form', [
            'credential' => null,
            'providers'  => $providers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'in:' . implode(',', array_keys(AiProviderFactory::providers()))],
            'name'     => ['required', 'string', 'max:100'],
            'api_key'  => ['required', 'string'],
        ]);

        $credential = AiCredential::create($data);

        Cache::forget('layout.has_ai_credentials');
        Cache::forget('layout.analyse_enabled');

        return redirect()->route('ai-config.index', ['tab' => 'credentials'])
            ->with('success', "Credential \"{$credential->name}\" added.");
    }

    public function edit(AiCredential $aiCredential)
    {
        $providers = AiProviderFactory::providers();

        return view('configuration.ai.credential-form', [
            'credential' => $aiCredential,
            'providers'  => $providers,
        ]);
    }

    public function update(Request $request, AiCredential $aiCredential)
    {
        $rules = [
            'provider' => ['required', 'string', 'in:' . implode(',', array_keys(AiProviderFactory::providers()))],
            'name'     => ['required', 'string', 'max:100'],
            'api_key'  => ['nullable', 'string'],
        ];

        $data = $request->validate($rules);

        $aiCredential->name     = $data['name'];
        $aiCredential->provider = $data['provider'];

        if (!empty($data['api_key'])) {
            $aiCredential->api_key = $data['api_key'];
        }

        $aiCredential->save();

        return redirect()->route('ai-config.index', ['tab' => 'credentials'])
            ->with('success', "Credential \"{$aiCredential->name}\" updated.");
    }

    public function destroy(AiCredential $aiCredential)
    {
        $name = $aiCredential->name;
        $aiCredential->modelConfigs()->delete();
        $aiCredential->delete();

        Cache::forget('layout.has_ai_credentials');
        Cache::forget('layout.analyse_enabled');

        return redirect()->route('ai-config.index', ['tab' => 'credentials'])
            ->with('success', "Credential \"{$name}\" deleted.");
    }

    public function testRaw(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'in:' . implode(',', array_keys(AiProviderFactory::providers()))],
            'api_key'  => ['required', 'string'],
        ]);

        try {
            $cred = new AiCredential($data);
            $provider = AiProviderFactory::make($cred);
            $provider->testConnection();
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function test(AiCredential $aiCredential)
    {
        try {
            $provider = AiProviderFactory::make($aiCredential);
            $provider->testConnection();
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function models(AiCredential $aiCredential)
    {
        try {
            $provider = AiProviderFactory::make($aiCredential);
            $models   = $provider->fetchModels();
            return response()->json(['models' => $models]);
        } catch (\Throwable $e) {
            return response()->json(['models' => [], 'error' => $e->getMessage()]);
        }
    }
}
