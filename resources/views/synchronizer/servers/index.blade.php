@extends('layouts.app')
@section('title', 'Synchronizer Servers')

@section('content')

<div class="page-header">
    <span class="page-title">Synchronizer Servers</span>
    <a href="{{ route('synchronizer.wizard.step1') }}" class="btn btn-primary btn-sm">
        <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Add server
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
                            <a href="{{ route('synchronizer.servers.edit', $server) }}" class="btn btn-muted btn-sm">
                                <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Edit
                            </a>
                            <form method="POST" action="{{ route('synchronizer.servers.destroy', $server) }}" class="inline"
                                  onsubmit="return confirm('Delete {{ addslashes($server->name) }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
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
                         + '<span style="color:#15803d">' + data.message + '</span>';
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

// Fire all pings in parallel after page load
document.addEventListener('DOMContentLoaded', () => {
    pingUrls.forEach(pingServer);
});
</script>
@endpush

@endif

@endsection
