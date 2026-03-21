@extends('layouts.app')
@section('title', 'Connect AI')

@section('content')

<div class="page-header">
    <h1 class="page-title">Connect AI</h1>
    @if($activeTab === 'credentials')
        <a href="{{ route('ai-credentials.create') }}" class="btn btn-primary btn-sm">Add Credential</a>
    @endif
</div>

{{-- Tabs --}}
<div class="flex gap-0 border-b border-gray-200 mb-5">
    @foreach(['credentials' => 'Credentials ('.$credentials->count().')', 'models' => 'Model Assignment'] as $tab => $label)
        <a href="{{ request()->fullUrlWithQuery(['tab' => $tab]) }}"
           class="px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition
                  {{ $activeTab === $tab ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}
                  {{ $tab === 'models' && $credentials->isEmpty() ? 'opacity-40 pointer-events-none' : '' }}"
           @if($tab === 'models' && $credentials->isEmpty()) title="Add an AI credential first to configure model assignments" @endif>
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- ── CREDENTIALS TAB ── --}}
@if($activeTab === 'credentials')

<div class="card overflow-hidden max-w-2xl">
    @if($credentials->isNotEmpty())
        <table class="w-full text-sm">
            <thead class="tbl-header">
                <tr>
                    <th class="px-4 py-2.5 text-left font-medium">Name</th>
                    <th class="col-mobile-hidden px-4 py-2.5 text-left font-medium">Provider</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($credentials as $cred)
                    <tr class="tbl-row">
                        <td class="px-4 py-2.5">
                            <span class="font-medium text-gray-800">{{ $cred->name }}</span>
                        </td>
                        <td class="col-mobile-hidden px-4 py-2.5 text-gray-500 text-xs">{{ $cred->providerLabel() }}</td>
                        <td class="px-4 py-2.5 text-right">
                            {{-- Desktop --}}
                            <div class="row-actions-desktop items-center justify-end gap-1.5">
                                <a href="{{ route('ai-credentials.edit', $cred) }}" class="row-action text-xs">Edit</a>
                                <form method="POST" action="{{ route('ai-credentials.destroy', $cred) }}"
                                      onsubmit="return confirm('Delete credential {{ addslashes($cred->name) }}?')"
                                      class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="row-action-danger text-xs">Delete</button>
                                </form>
                            </div>
                            {{-- Mobile "..." --}}
                            <div class="row-actions-mobile relative" x-data="{ open: false }" @click.outside="open = false" @close-row-dropdowns.window="open = false">
                                <button @click="let o=open; $dispatch('close-row-dropdowns'); open=!o"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="10" cy="16" r="1.5"/></svg>
                                </button>
                                <div x-show="open" x-cloak
                                     class="absolute right-0 top-full mt-1 w-32 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50 text-sm">
                                    <a href="{{ route('ai-credentials.edit', $cred) }}"
                                       class="flex w-full px-3 py-2 text-gray-700 hover:bg-gray-50">Edit</a>
                                    <form method="POST" action="{{ route('ai-credentials.destroy', $cred) }}"
                                          onsubmit="return confirm('Delete credential {{ addslashes($cred->name) }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="flex w-full px-3 py-2 text-red-600 hover:bg-red-50 text-left">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="px-5 py-12 text-center text-sm empty-state italic">
            No credentials configured. <a href="{{ route('ai-credentials.create') }}" class="text-brand-600 hover:underline not-italic">Add one</a> to enable AI features.
        </div>
    @endif
</div>

@endif

{{-- ── MODEL ASSIGNMENT TAB ── --}}
@if($activeTab === 'models' && $credentials->isNotEmpty())

<div class="max-w-2xl">
    <p class="text-xs text-gray-400 mb-4">Assign a credential and model to each AI action. Leave blank to disable.</p>

    <form method="POST" action="{{ route('ai-model-configs.update') }}" id="model-config-form">
        @csrf

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="tbl-header">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-medium w-44">Action</th>
                        <th class="px-4 py-2.5 text-left font-medium">Credential</th>
                        <th class="px-4 py-2.5 text-left font-medium">Model</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($actionTypes as $actionKey => $actionLabel)
                        <tr class="tbl-row">
                            <td class="px-4 py-3 font-medium text-gray-700 truncate" title="{{ $actionLabel }}">
                                {{ $actionLabel }}
                            </td>
                            <td class="px-4 py-3">
                                <input type="hidden" name="configs[{{ $actionKey }}][action_type]" value="{{ $actionKey }}">
                                <select name="configs[{{ $actionKey }}][credential_id]"
                                        class="input text-xs py-1.5 w-full model-credential-select"
                                        data-action="{{ $actionKey }}"
                                        onchange="onCredentialChange(this)">
                                    <option value="">— None —</option>
                                    @foreach($credentials as $cred)
                                        <option value="{{ $cred->id }}"
                                                data-provider="{{ $cred->provider }}"
                                                {{ ($modelConfigs->get($actionKey)?->credential_id === $cred->id) ? 'selected' : '' }}>
                                            {{ $cred->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <select name="configs[{{ $actionKey }}][model_name]"
                                        class="input text-xs py-1.5 w-full model-name-select"
                                        id="model-select-{{ $actionKey }}">
                                    <option value="">— model —</option>
                                    @if($modelConfigs->get($actionKey)?->model_name)
                                        <option value="{{ $modelConfigs->get($actionKey)->model_name }}" selected>{{ $modelConfigs->get($actionKey)->model_name }}</option>
                                    @endif
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button type="submit" class="btn btn-primary btn-sm mt-4">Save Assignments</button>
    </form>
</div>

@endif

@push('scripts')
<script>
const credentialModelsCache = {};

async function onCredentialChange(select) {
    const credId = select.value;
    const action = select.dataset.action;
    const modelSelect = document.getElementById('model-select-' + action);

    modelSelect.innerHTML = '<option value="">Loading…</option>';

    if (!credId) {
        modelSelect.innerHTML = '<option value="">— Select credential first —</option>';
        return;
    }

    if (credentialModelsCache[credId]) {
        populateModels(modelSelect, credentialModelsCache[credId], null);
        return;
    }

    try {
        const res = await fetch('/configuration/ai/credentials/' + credId + '/models');
        const data = await res.json();
        credentialModelsCache[credId] = data.models || [];
        populateModels(modelSelect, credentialModelsCache[credId], null);
    } catch (e) {
        modelSelect.innerHTML = '<option value="">— Failed to load —</option>';
    }
}

function populateModels(select, models, currentValue) {
    select.innerHTML = '<option value="">— Select model —</option>';
    models.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m;
        opt.textContent = m;
        if (m === currentValue) opt.selected = true;
        select.appendChild(opt);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.model-credential-select').forEach(sel => {
        if (sel.value) {
            const action = sel.dataset.action;
            const modelSel = document.getElementById('model-select-' + action);
            const currentModel = modelSel.value;

            fetch('/configuration/ai/credentials/' + sel.value + '/models')
                .then(r => r.json())
                .then(data => {
                    credentialModelsCache[sel.value] = data.models || [];
                    populateModels(modelSel, data.models || [], currentModel);
                })
                .catch(() => {});
        }
    });
});
</script>
@endpush

@endsection
