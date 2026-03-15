@extends('layouts.app')
@section('title', $server ? 'Edit Server' : 'Add Server')

@section('content')

<div class="page-header">
    <div>
        <nav aria-label="Breadcrumb" class="page-breadcrumb">
            <a href="{{ route('synchronizer.servers.index') }}">Servers</a>
            <span class="sep">/</span>
            <span class="cur" aria-current="page">{{ $server ? 'Edit Server' : 'New Server' }}</span>
        </nav>
        <h1 class="page-title mt-1">{{ $server ? 'Edit Server' : 'New Server' }}</h1>
    </div>
</div>

@if($errors->any())
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
        <ul class="space-y-1">
            @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card p-5 max-w-lg">
    <form method="POST"
          action="{{ $server ? route('synchronizer.servers.update', $server) : route('synchronizer.servers.store') }}"
          id="server-form">
        @csrf
        @if($server) @method('PUT') @endif

        <div class="space-y-4">
            <div>
                <label class="label">Name</label>
                <input type="text" name="name" id="f-name" value="{{ old('name', $server?->name) }}"
                       class="input" placeholder="e.g. Production Synchronizer" required>
            </div>

            <div>
                <label class="label">URL</label>
                <input type="url" name="url" id="f-url" value="{{ old('url', $server?->url) }}"
                       class="input" placeholder="http://contact-monitor-synchronizer:8000" required>
            </div>

            <div>
                <label class="label">API Token</label>
                <input type="text" name="api_token" id="f-token" value="{{ old('api_token', $server?->api_token) }}"
                       class="input font-mono text-xs" placeholder="Bearer token" required>
            </div>
        </div>

        {{-- Test connection --}}
        <div class="mt-4 flex items-center gap-3">
            <button type="button" onclick="testConnection()" id="test-btn" class="btn btn-secondary btn-sm">
                <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Test connection
            </button>
            <span id="test-result" class="text-xs"></span>
        </div>

        <div class="mt-5 pt-4 border-t border-gray-100 flex items-center gap-2">
            <button type="submit" class="btn btn-primary">
                {{ $server ? 'Save changes' : 'Add server' }}
            </button>
            <a href="{{ route('synchronizer.servers.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
async function testConnection() {
    const btn    = document.getElementById('test-btn');
    const result = document.getElementById('test-result');
    const url    = document.getElementById('f-url').value.trim();
    const token  = document.getElementById('f-token').value.trim();

    if (!url || !token) {
        result.style.color = '#dc2626';
        result.textContent = 'Fill in URL and API Token first.';
        return;
    }

    btn.disabled = true;
    result.style.color = '#6b7280';
    result.textContent = 'Testing…';

    try {
        const res  = await fetch('{{ route('synchronizer.servers.test') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ url, api_token: token })
        });
        const data = await res.json();

        if (data.ok) {
            result.style.color = '#15803d';
            result.textContent = '✓ ' + data.message;
        } else {
            result.style.color = '#dc2626';
            result.textContent = '✗ ' + data.error;
        }
    } catch (e) {
        result.style.color = '#dc2626';
        result.textContent = '✗ ' + e.message;
    } finally {
        btn.disabled = false;
    }
}
</script>
@endpush

@endsection
