@extends('layouts.app')
@section('title', 'Synchronizer Servers')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Synchronizer Servers</h1>
        <p class="text-xs text-gray-400 mt-0.5">Register the external Synchronizer services that pull data from your integrations and push it here. Each server runs independently and can manage multiple connections.</p>
    </div>
    <a href="{{ route('synchronizer.wizard.step1') }}" class="btn btn-primary">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        New Server
    </a>
</div>

@if($servers->isEmpty())
    <div class="card p-12 text-center max-w-lg mx-auto mt-8">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-5" style="background:#f1f5f9">
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
            </svg>
        </div>
        <h2 class="font-semibold text-gray-800 text-base mb-2">No synchronizer servers yet</h2>
        <p class="text-sm text-gray-500 mb-6 leading-relaxed">
            To start importing data, you need to connect at least one Synchronizer server.<br>
            The synchronizer pulls data from your integrations and pushes it here.
        </p>
        <a href="{{ route('synchronizer.wizard.step1') }}" class="btn btn-primary">
            <svg class="w-4 h-4 mr-1.5 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add your first server
        </a>
    </div>
@else

<div class="card overflow-hidden">
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th class="px-4 py-2.5 text-left">Name</th>
                <th class="px-4 py-2.5 text-left">URL</th>
                <th class="px-4 py-2.5 text-left">Status</th>
                <th class="px-4 py-2.5 text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($servers as $server)
                <tr class="tbl-row">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $server->name }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs font-mono">{{ $server->url }}</td>
                    <td class="px-4 py-3">
                        <span id="status-{{ $server->id }}" class="text-xs text-gray-400">
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-gray-300 mr-1"></span>
                            Checking…
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <a href="{{ route('synchronizer.index', ['server' => $server->id]) }}" class="btn btn-muted btn-sm">
                                Connections
                            </a>
                            <a href="{{ route('synchronizer.servers.edit', $server) }}" class="btn btn-muted btn-sm">
                                <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Edit
                            </a>
                            <button type="button" class="btn btn-danger btn-sm"
                                onclick="openDeleteModal('{{ addslashes($server->name) }}', '{{ route('synchronizer.servers.destroy', $server) }}')">
                                <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-400">
                        No servers configured yet. Add one to connect to a Synchronizer instance.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Delete confirmation modal --}}
<div id="delete-modal" class="fixed inset-0 z-50 hidden items-center justify-center modal-overlay">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
        <div class="flex items-start gap-3 mb-4">
            <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 bg-red-100">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 mb-1">Remove server <span id="delete-server-name" class="text-red-600"></span>?</h3>
                <p class="text-sm text-gray-600 leading-relaxed">
                    This only removes the connection from Contact Monitor. <strong>The synchronizer process itself will keep running</strong> on the remote server.
                </p>
            </div>
        </div>

        <div class="rounded-lg p-3 mb-5 text-sm alert-warning">
            <p class="font-medium text-amber-800 mb-1">To fully uninstall the synchronizer, run on the remote server:</p>
            <code class="text-xs text-amber-900 font-mono block">cd contact-monitor-synchronizer &amp;&amp; docker compose down -v</code>
        </div>

        <p class="text-sm text-gray-500 mb-5">
            Once removed, this server's ingest credentials will be revoked — it will no longer be able to push data even if it's still running.
        </p>

        <div class="flex justify-end gap-2">
            <button type="button" onclick="closeDeleteModal()" class="btn btn-muted btn-sm">Cancel</button>
            <form id="delete-form" method="POST">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
const pingUrls = @json($servers->map(fn($s) => ['id' => $s->id, 'url' => route('synchronizer.servers.ping', $s)])->values());

async function pingServer({ id, url }) {
    const el = document.getElementById('status-' + id);
    try {
        const res  = await fetch(url);
        const data = await res.json();
        if (data.ok) {
            el.innerHTML = '<span class="inline-block w-1.5 h-1.5 rounded-full mr-1" style="background:#3fb950"></span>'
                         + '<span style="color:#15803d">Online</span>'
                         + '<span class="text-gray-400 ml-1.5">' + data.connections + ' integration(s)</span>';
        } else {
            el.innerHTML = '<span class="inline-block w-1.5 h-1.5 rounded-full mr-1" style="background:#f85149"></span>'
                         + '<span style="color:#dc2626" title="' + escHtml(data.error) + '">'
                         + truncate(data.error, 50) + '</span>';
        }
    } catch (e) {
        el.innerHTML = '<span class="inline-block w-1.5 h-1.5 rounded-full mr-1" style="background:#f85149"></span>'
                     + '<span style="color:#dc2626">' + escHtml(e.message) + '</span>';
    }
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function truncate(s, n) {
    s = String(s);
    return s.length > n ? s.slice(0, n) + '…' : s;
}

function openDeleteModal(name, action) {
    document.getElementById('delete-server-name').textContent = name;
    document.getElementById('delete-form').action = action;
    const m = document.getElementById('delete-modal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function closeDeleteModal() {
    const m = document.getElementById('delete-modal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

document.getElementById('delete-modal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

// Fire all pings in parallel after page load
document.addEventListener('DOMContentLoaded', () => {
    pingUrls.forEach(pingServer);
});
</script>
@endpush

@endif

@endsection
