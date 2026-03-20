@extends('layouts.app')
@section('title', 'Configure New Server')

@section('content')

<div class="page-header">
    <h1 class="page-title">Configure New Server</h1>
    <a href="{{ route('synchronizer.wizard.step1') }}" class="btn btn-secondary btn-sm">Back</a>
</div>

<div class="max-w-2xl space-y-5">

    <div class="card p-5">
        <h2 class="font-semibold text-gray-800 mb-1">1. Run this command on your server</h2>
        <p class="text-xs text-gray-500 mb-3">This downloads and sets up the synchronizer, then connects it to this Contact Monitor instance automatically.</p>

        <div class="relative">
            <pre id="install-cmd" class="code-block rounded-lg text-xs p-4 overflow-x-auto leading-relaxed select-all">SYNC_APP_PORT=8080 SYNC_DB_PORT=5433 bash &lt;(curl -sSL {{ route('synchronizer.wizard.install-script', $pending->token) }})</pre>
            <button onclick="copyCmd()" class="absolute top-2 right-2 btn btn-secondary btn-sm" id="copy-btn">Copy</button>
        </div>
        <p class="text-xs text-gray-400 mt-2">Change <code class="font-mono">SYNC_APP_PORT</code> and <code class="font-mono">SYNC_DB_PORT</code> if the defaults are already in use.</p>
    </div>

    <div class="card p-5">
        <h2 class="font-semibold text-gray-800 mb-1">2. Waiting for connection</h2>
        <p class="text-xs text-gray-500 mb-4">After running the command, the synchronizer will automatically connect here. This page will update when it's detected.</p>

        <div id="status-area" class="flex items-center gap-3">
            <span id="status-dot" class="inline-block w-2.5 h-2.5 rounded-full animate-pulse" style="background:#f59e0b"></span>
            <span id="status-text" class="text-sm text-gray-600">Waiting for synchronizer to connect…</span>
        </div>
    </div>

</div>

@push('scripts')
<script>
function copyCmd() {
    const text = document.getElementById('install-cmd').textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('copy-btn');
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 2000);
    });
}

async function poll() {
    try {
        const res  = await fetch('{{ route('synchronizer.wizard.poll', $pending->token) }}');
        const data = await res.json();

        if (data.status === 'registered') {
            document.getElementById('status-dot').style.background  = '#22c55e';
            document.getElementById('status-dot').classList.remove('animate-pulse');
            document.getElementById('status-text').innerHTML =
                '<strong class="text-green-700">Connected!</strong> Redirecting…';
            setTimeout(() => window.location = '{{ route('synchronizer.servers.index') }}', 1500);
            return;
        }

        if (data.status === 'expired') {
            document.getElementById('status-dot').style.background = '#ef4444';
            document.getElementById('status-dot').classList.remove('animate-pulse');
            document.getElementById('status-text').textContent = 'Registration token expired. Please go back and try again.';
            return;
        }
    } catch {}

    setTimeout(poll, 3000);
}

setTimeout(poll, 3000);
</script>
@endpush

@endsection
