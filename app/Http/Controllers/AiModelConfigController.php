<?php

namespace App\Http\Controllers;

use App\Models\AiModelConfig;
use App\Models\AiCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AiModelConfigController extends Controller
{
    public function update(Request $request)
    {
        $validActions = array_keys(AiModelConfig::actionLabels());

        $data = $request->validate([
            'configs'                    => ['required', 'array'],
            'configs.*.credential_id'    => ['nullable', 'integer', 'exists:ai_credentials,id'],
            'configs.*.model_name'       => ['nullable', 'string', 'max:100'],
        ]);

        foreach ($data['configs'] as $actionType => $cfg) {
            if (! in_array($actionType, $validActions, true)) {
                continue;
            }

            if (empty($cfg['credential_id']) || empty($cfg['model_name'])) {
                AiModelConfig::where('action_type', $actionType)->delete();
            } else {
                AiModelConfig::updateOrCreate(
                    ['action_type' => $actionType],
                    [
                        'credential_id' => $cfg['credential_id'],
                        'model_name'    => $cfg['model_name'],
                    ]
                );
            }
        }

        Cache::forget('layout.analyse_enabled');

        return redirect()->route('ai-config.index', ['tab' => 'models'])
            ->with('success', 'Model assignments saved.');
    }
}
