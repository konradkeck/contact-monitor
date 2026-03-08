@extends('layouts.app')
@section('title', 'Connect to Existing Server')

@section('content')

<div class="page-header">
    <span class="page-title">Connect to Existing Server</span>
    <a href="{{ route('synchronizer.wizard.step1') }}" class="btn btn-secondary btn-sm">Back</a>
</div>

{{-- Step indicator --}}
<div class="flex items-center gap-2 mb-6 text-xs text-gray-400">
    <span id="ind-1" class="font-semibold text-brand-600">① Connection details</span>
    <span>→</span>
    <span id="ind-2">② Verify configuration</span>
</div>

<div class="max-w-lg">

    {{-- Step 1: form --}}
    <div id="step-1">
        <div class="card p-5 space-y-4">
            <div>
                <label class="label">Name</label>
                <input type="text" id="f-name" class="input" placeholder="e.g. Production Synchronizer" value="">
            </div>
            <div>
                <label class="label">URL</label>
                <input type="url" id="f-url" class="input" placeholder="http://localhost:8080">
            </div>
            <div>
                <label class="label">API Token</label>
                <input type="text" id="f-token" class="input font-mono text-xs" placeholder="Bearer token">
            </div>
        </div>

        <div class="mt-4 flex items-center gap-3">
            <button onclick="testAndInspect()" id="test-btn" class="btn btn-primary">
                Test &amp; Continue →
            </button>
            <span id="test-result" class="text-xs"></span>
        </div>
    </div>

    {{-- Step 2: confirm / warning --}}
    <div id="step-2" class="hidden">

        {{-- Points elsewhere warning --}}
        <div id="warning-box" class="hidden card p-4 mb-4 text-sm" style="border-color:#fbbf24; background:#fffbeb">
            <div class="flex gap-2 items-start">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" style="color:#b45309" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <div>
                    <strong style="color:#92400e">This synchronizer is currently sending data elsewhere.</strong>
                    <p class="mt-1 text-xs" style="color:#78350f">
                        Currently connected to: <code id="current-ingest" class="font-mono"></code><br>
                        Connecting it here will redirect all data to <strong>this Contact Monitor</strong> and break the existing connection.<br>
                        All previous run history will be reset.
                    </p>
                </div>
            </div>
            <div class="mt-3 flex gap-2">
                <button onclick="confirmConnect()" class="btn btn-primary btn-sm">Yes, connect here</button>
                <button onclick="goBack()" class="btn btn-secondary btn-sm">Cancel</button>
            </div>
        </div>

        {{-- All good --}}
        <div id="ok-box" class="hidden card p-4 mb-4 text-sm" style="border-color:#86efac; background:#f0fdf4">
            <div class="flex gap-2 items-start">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" style="color:#16a34a" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div style="color:#166534">
                    <strong>Ready to connect.</strong> This synchronizer is already configured to send data to this Contact Monitor instance.
                </div>
            </div>
            <div class="mt-3">
                <button onclick="confirmConnect()" class="btn btn-primary btn-sm">Add server</button>
            </div>
        </div>

        <div id="save-result" class="text-xs text-red-600 mt-2"></div>
    </div>

</div>

@push('scripts')
<script>
let _inspected = null;

async function testAndInspect() {
    const btn    = document.getElementById('test-btn');
    const result = document.getElementById('test-result');
    const url    = document.getElementById('f-url').value.trim();
    const token  = document.getElementById('f-token').value.trim();
    const name   = document.getElementById('f-name').value.trim();

    if (!url || !token || !name) {
        result.style.color = '#dc2626';
        result.textContent = 'Fill in all fields first.';
        return;
    }

    btn.disabled = true;
    result.style.color = '#6b7280';
    result.textContent = 'Testing connection…';

    try {
        const res  = await fetch('{{ route('synchronizer.wizard.inspect') }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({ url, api_token: token, name })
        });
        const data = await res.json();

        if (!data.ok) {
            result.style.color = '#dc2626';
            result.textContent = '✗ ' + data.error;
            btn.disabled = false;
            return;
        }

        _inspected = { url, token, name, ...data };

        result.textContent = '';
        btn.disabled = false;

        // Move to step 2
        document.getElementById('step-1').classList.add('hidden');
        document.getElementById('ind-2').classList.add('font-semibold', 'text-brand-600');
        document.getElementById('ind-2').classList.remove('text-gray-400');
        document.getElementById('step-2').classList.remove('hidden');

        if (data.points_elsewhere) {
            document.getElementById('current-ingest').textContent = data.current_ingest;
            document.getElementById('warning-box').classList.remove('hidden');
        } else {
            document.getElementById('ok-box').classList.remove('hidden');
        }

    } catch (e) {
        result.style.color = '#dc2626';
        result.textContent = '✗ ' + e.message;
        btn.disabled = false;
    }
}

function goBack() {
    document.getElementById('step-2').classList.add('hidden');
    document.getElementById('warning-box').classList.add('hidden');
    document.getElementById('ok-box').classList.add('hidden');
    document.getElementById('step-1').classList.remove('hidden');
}

async function confirmConnect() {
    const result = document.getElementById('save-result');
    result.textContent = 'Saving…';

    try {
        const res  = await fetch('{{ route('synchronizer.wizard.connect-save') }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({ url: _inspected.url, api_token: _inspected.token, name: _inspected.name })
        });
        const data = await res.json();

        if (data.ok) {
            window.location = data.redirect;
        } else {
            result.textContent = '✗ ' + data.error;
        }
    } catch (e) {
        result.textContent = '✗ ' + e.message;
    }
}
</script>
@endpush

@endsection
