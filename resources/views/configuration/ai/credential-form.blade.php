@extends('layouts.app')
@section('title', $credential ? 'Edit Credential' : 'Add Credential')

@section('content')
<div class="max-w-xl">
    <div class="page-header">
        <div>
            <nav aria-label="Breadcrumb" class="page-breadcrumb">
                <a href="{{ route('ai-config.index', ['tab' => 'credentials']) }}">Connect AI</a>
                <span class="sep">/</span>
                <span class="cur" aria-current="page">{{ $credential ? 'Edit Credential' : 'Add Credential' }}</span>
            </nav>
            <h1 class="page-title mt-1">{{ $credential ? 'Edit Credential' : 'Add Credential' }}</h1>
        </div>
    </div>

    <div class="card p-6">
        <form method="POST"
              action="{{ $credential ? route('ai-credentials.update', $credential) : route('ai-credentials.store') }}"
              id="cred-form">
            @csrf
            @if($credential) @method('PUT') @endif

            <div class="space-y-4">
                <div>
                    <label class="label">Label</label>
                    <input type="text" name="name" class="input w-full" placeholder="e.g. My Claude Key"
                           value="{{ old('name', $credential->name ?? '') }}" required>
                    @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Provider</label>
                    <select name="provider" id="cred-provider" class="input w-full" required>
                        <option value="">— Select provider —</option>
                        @foreach($providers as $key => $label)
                            <option value="{{ $key }}" {{ old('provider', $credential->provider ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('provider')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">API Key</label>
                    <input type="password" name="api_key" id="cred-api-key" class="input w-full font-mono text-xs"
                           placeholder="{{ $credential ? '(unchanged — enter new key to replace)' : 'sk-...' }}"
                           {{ $credential ? '' : 'required' }} autocomplete="new-password">
                    @error('api_key')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div id="test-result" class="hidden mt-4 text-xs rounded-lg px-3 py-2"></div>

            <div class="flex gap-2 mt-6">
                <button type="submit" class="btn btn-primary">{{ $credential ? 'Save Changes' : 'Add Credential' }}</button>
                <button type="button" onclick="testConnection(this)" class="btn btn-secondary">Test Connection</button>
                <a href="{{ route('ai-config.index', ['tab' => 'credentials']) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
async function testConnection(btn) {
    const provider = document.getElementById('cred-provider').value;
    const apiKey   = document.getElementById('cred-api-key').value;
    const result   = document.getElementById('test-result');

    @if($credential)
    if (!provider) {
        result.className = 'mt-4 text-xs rounded-lg px-3 py-2 text-amber-700 bg-amber-50 border border-amber-200';
        result.textContent = 'Select a provider first.';
        result.classList.remove('hidden');
        return;
    }

    // For existing credentials, test via saved key if no new key entered
    if (!apiKey) {
        const orig = btn.textContent;
        btn.textContent = 'Testing…';
        btn.disabled = true;
        try {
            const res = await fetch('{{ route("ai-credentials.test", $credential) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const data = await res.json();
            if (data.ok) {
                result.className = 'mt-4 text-xs rounded-lg px-3 py-2 text-green-700 bg-green-50 border border-green-200';
                result.textContent = 'Connection successful.';
            } else {
                result.className = 'mt-4 text-xs rounded-lg px-3 py-2 text-red-700 bg-red-50 border border-red-200';
                result.textContent = 'Connection failed: ' + (data.error || 'Unknown error');
            }
        } catch (e) {
            result.className = 'mt-4 text-xs rounded-lg px-3 py-2 text-red-700 bg-red-50 border border-red-200';
            result.textContent = 'Request failed: ' + e.message;
        } finally {
            result.classList.remove('hidden');
            btn.textContent = orig;
            btn.disabled = false;
        }
        return;
    }
    @else
    if (!provider || !apiKey) {
        result.className = 'mt-4 text-xs rounded-lg px-3 py-2 text-amber-700 bg-amber-50 border border-amber-200';
        result.textContent = 'Select a provider and enter an API key first.';
        result.classList.remove('hidden');
        return;
    }
    @endif

    const orig = btn.textContent;
    btn.textContent = 'Testing…';
    btn.disabled = true;

    try {
        const res = await fetch('{{ route("ai-credentials.test-raw") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ provider, api_key: apiKey })
        });
        const data = await res.json();

        if (data.ok) {
            result.className = 'mt-4 text-xs rounded-lg px-3 py-2 text-green-700 bg-green-50 border border-green-200';
            result.textContent = 'Connection successful.';
        } else {
            result.className = 'mt-4 text-xs rounded-lg px-3 py-2 text-red-700 bg-red-50 border border-red-200';
            result.textContent = 'Connection failed: ' + (data.error || 'Unknown error');
        }
    } catch (e) {
        result.className = 'mt-4 text-xs rounded-lg px-3 py-2 text-red-700 bg-red-50 border border-red-200';
        result.textContent = 'Request failed: ' + e.message;
    } finally {
        result.classList.remove('hidden');
        btn.textContent = orig;
        btn.disabled = false;
    }
}
</script>
@endpush

@endsection
