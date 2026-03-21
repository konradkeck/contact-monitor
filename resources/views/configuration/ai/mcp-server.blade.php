@extends('layouts.app')
@section('title', 'MCP Server')

@section('content')

<div class="page-header">
    <h1 class="page-title">MCP Server</h1>
</div>

@if(session('api_key_plain'))
    <div class="alert-warning mb-4">
        <p class="font-semibold text-sm mb-1">New API Key (copy now — will not be shown again):</p>
        <code class="font-mono text-sm break-all select-all">{{ session('api_key_plain') }}</code>
    </div>
@endif

{{-- Tabs --}}
<div class="flex gap-0 border-b border-gray-200 mb-5">
    <a href="{{ route('mcp-server.index') }}"
       class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
           {{ $tab === 'settings' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
        Settings
    </a>
    <a href="{{ route('mcp-log.index') }}"
       class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
           {{ $tab === 'log' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
        Log
    </a>
</div>

@if($tab === 'settings')

    {{-- Enable / Disable --}}
    <div class="card p-5 mb-5 max-w-2xl">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-medium text-sm text-gray-800">MCP Server</p>
                <p class="text-xs text-gray-500 mt-0.5">Enable the Model Context Protocol server for AI integration (JSON-RPC 2.0).</p>
            </div>
            <form method="POST" action="{{ route('ai-config.settings') }}">
                @csrf
                <input type="hidden" name="mcp_enabled" value="{{ $enabled ? '0' : '1' }}">
                <input type="hidden" name="mcp_external_enabled" value="{{ $externalEnabled ? '1' : '0' }}">
                <button type="submit" class="btn {{ $enabled ? 'btn-danger' : 'btn-primary' }} btn-sm">
                    {{ $enabled ? 'Disable' : 'Enable' }}
                </button>
            </form>
        </div>
        @if($enabled)
            <div class="mt-3 flex items-center gap-2 text-xs text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                MCP Server is active. Localhost access is always allowed without authentication.
            </div>
        @endif
    </div>

    @if($enabled)

    {{-- Endpoint URL --}}
    <div class="card p-5 mb-5 max-w-2xl">
        <p class="font-medium text-sm text-gray-800 mb-2">Endpoint URL</p>
        <div class="flex items-center gap-2">
            <input type="text" readonly value="{{ $endpointUrl }}"
                   class="input w-full font-mono text-xs bg-gray-50 cursor-text select-all"
                   onclick="this.select()">
            <button type="button" onclick="copyEndpoint(this)"
                    class="btn btn-secondary btn-sm shrink-0">Copy</button>
        </div>
        <p class="text-xs text-gray-400 mt-2">Send JSON-RPC 2.0 requests via <code class="font-mono">POST</code> to this URL.</p>
    </div>

    {{-- External access --}}
    <div class="card p-5 mb-5 max-w-2xl">
        <div class="flex items-center justify-between gap-4 mb-3">
            <div>
                <p class="font-medium text-sm text-gray-800">External Access</p>
                <p class="text-xs text-gray-500 mt-0.5">Allow connections from outside localhost. Requires an API key.</p>
            </div>
            <form method="POST" action="{{ route('ai-config.settings') }}">
                @csrf
                <input type="hidden" name="mcp_enabled" value="1">
                <input type="hidden" name="mcp_external_enabled" value="{{ $externalEnabled ? '0' : '1' }}">
                <button type="submit" class="btn {{ $externalEnabled ? 'btn-danger' : 'btn-secondary' }} btn-sm">
                    {{ $externalEnabled ? 'Disable External' : 'Enable External' }}
                </button>
            </form>
        </div>

        @if($externalEnabled)
            <div class="border-t border-gray-100 pt-3 mt-1">
                <p class="text-xs font-medium text-gray-700 mb-2">API Key</p>
                @if($hasApiKey)
                    <p class="text-xs text-gray-500 mb-2">An API key is configured. To rotate it, generate a new one below.</p>
                @else
                    <p class="text-xs text-amber-600 mb-2">No API key configured. External connections will be rejected until you generate one.</p>
                @endif
                <form method="POST" action="{{ route('ai-config.regenerate-key') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm"
                            onclick="return confirm('This will invalidate the current API key. Continue?')">
                        {{ $hasApiKey ? 'Regenerate Key' : 'Generate Key' }}
                    </button>
                </form>
                <p class="text-xs text-gray-400 mt-2">Use <code class="font-mono">Authorization: Bearer &lt;key&gt;</code> header in requests.</p>
            </div>
        @endif
    </div>

    @endif

    @push('scripts')
    <script>
    function copyEndpoint(btn) {
        navigator.clipboard.writeText('{{ $endpointUrl }}').then(() => {
            btn.textContent = 'Copied!';
            setTimeout(() => btn.textContent = 'Copy', 2000);
        });
    }
    </script>
    @endpush

@else

    {{-- Log tab --}}
    <div class="card-xl-overflow">
        <table class="w-full text-sm">
            <thead class="tbl-header">
                <tr>
                    <th class="px-4 py-2.5 text-left">Tool</th>
                    <th class="px-4 py-2.5 text-left">Context</th>
                    <th class="px-4 py-2.5 text-left">Entity</th>
                    <th class="px-4 py-2.5 text-left">User</th>
                    <th class="px-4 py-2.5 text-left">IP</th>
                    <th class="px-4 py-2.5 text-left">Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr class="tbl-row">
                    <td class="px-4 py-3 font-mono text-xs font-medium text-gray-700">{{ $log->tool_name }}</td>
                    <td class="px-4 py-3">
                        <span class="badge {{ $log->context === 'chat' ? 'badge-blue' : ($log->context === 'automated' ? 'badge-green' : 'badge-gray') }}">
                            {{ $log->context }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        @if($log->entity_type && $log->entity_id)
                            {{ class_basename($log->entity_type) }} #{{ $log->entity_id }}
                        @else
                            <span class="text-gray-300">&mdash;</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600">{{ $log->user?->name ?? 'API' }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ $log->ip_address }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $log->created_at?->diffForHumans() }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center empty-state italic">No MCP actions recorded yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($logs->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $logs->links() }}</div>
        @endif
    </div>

@endif

@endsection
