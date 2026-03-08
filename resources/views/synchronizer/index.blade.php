@extends('layouts.app')
@section('title', 'Connections')

@section('content')

{{-- Run All modal --}}
<div id="run-all-modal" class="fixed inset-0 z-50 hidden" onclick="if(event.target===this)closeRunAllModal()">
    <div class="absolute inset-0 bg-black/25"></div>
    <div class="absolute bg-white rounded-xl shadow-xl w-80"
         style="top:50%;left:50%;transform:translate(-50%,-50%)"
         onclick="event.stopPropagation()">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <span class="font-semibold text-gray-800 text-sm">Run all connections</span>
            <button onclick="closeRunAllModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div class="p-4 flex flex-col gap-3">
            <button onclick="doRunAll('partial')"
                    class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-brand-400 hover:bg-brand-50 text-left transition group">
                <span class="mt-0.5 text-brand-600 group-hover:text-brand-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </span>
                <div>
                    <div class="font-semibold text-gray-800 text-sm">Partial sync</div>
                    <div class="text-xs text-gray-400 mt-0.5">Fetches only new data since the last run.</div>
                </div>
            </button>
            <button onclick="doRunAll('full')"
                    class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-gray-400 hover:bg-gray-50 text-left transition group">
                <span class="mt-0.5 text-gray-500 group-hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0l-4-4m4 4l-4 4"/>
                    </svg>
                </span>
                <div>
                    <div class="font-semibold text-gray-800 text-sm">Full sync</div>
                    <div class="text-xs text-gray-400 mt-0.5">Re-imports everything from scratch. Slower.</div>
                </div>
            </button>
        </div>
    </div>
</div>

{{-- Run mode popup --}}
<div id="run-modal" class="fixed inset-0 z-50 hidden" onclick="if(event.target===this)closeRunModal()">
    <div class="absolute inset-0 bg-black/25"></div>
    <div class="absolute bg-white rounded-xl shadow-xl w-80"
         style="top:50%;left:50%;transform:translate(-50%,-50%)"
         onclick="event.stopPropagation()">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <span class="font-semibold text-gray-800 text-sm">Start sync</span>
            <button onclick="closeRunModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div class="p-4 flex flex-col gap-3">
            <button onclick="doRun('partial')"
                    class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-brand-400 hover:bg-brand-50 text-left transition group">
                <span class="mt-0.5 text-brand-600 group-hover:text-brand-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </span>
                <div>
                    <div class="font-semibold text-gray-800 text-sm">Partial sync</div>
                    <div class="text-xs text-gray-400 mt-0.5">Fetches only new data since the last run. Fast, used for regular updates.</div>
                </div>
            </button>
            <button onclick="doRun('full')"
                    class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-gray-400 hover:bg-gray-50 text-left transition group">
                <span class="mt-0.5 text-gray-500 group-hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0l-4-4m4 4l-4 4"/>
                    </svg>
                </span>
                <div>
                    <div class="font-semibold text-gray-800 text-sm">Full sync</div>
                    <div class="text-xs text-gray-400 mt-0.5">Re-imports everything from scratch, resetting the cursor. Slower — use to fix gaps or after connection changes.</div>
                </div>
            </button>
        </div>
    </div>
</div>

<div class="page-header">
    <div class="flex items-center gap-4">
        <span class="page-title">Connections</span>
        @if($servers->count() > 1)
            <form method="GET" action="{{ route('synchronizer.index') }}" id="server-form">
                <select name="server" class="input" style="width:auto;padding:.3rem .6rem;font-size:.8rem"
                        onchange="document.getElementById('server-form').submit()">
                    @foreach($servers as $srv)
                        <option value="{{ $srv->id }}" {{ $activeServer?->id === $srv->id ? 'selected' : '' }}>
                            {{ $srv->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        @elseif($activeServer)
            <span class="text-xs text-gray-400">{{ $activeServer->name }}</span>
        @endif
    </div>
    <div class="flex items-center gap-2">
        <button onclick="openRunAllModal()" class="btn btn-secondary btn-sm" id="run-all-btn">
            <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3l14 9-14 9V3z"/></svg>
            Run All
        </button>
        <button onclick="killAll()" class="btn btn-danger btn-sm" id="kill-btn">
            <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            Kill all runs
        </button>
        <a href="{{ route('synchronizer.index', $activeServer ? ['server' => $activeServer->id] : []) }}" class="btn btn-secondary btn-sm">
            <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Refresh
        </a>
        <a href="{{ route('synchronizer.connections.create', $activeServer ? ['server' => $activeServer->id] : []) }}" class="btn btn-primary btn-sm">
            <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            New connection
        </a>
    </div>
</div>

@if($error)
    <div class="card p-4 mb-4 text-sm" style="border-color:#fca5a5; background:#fff0f0; color:#991b1b">
        <strong>Connection error:</strong> {{ $error }}
    </div>
@endif

@php
    $typeColors = [
        'whmcs'       => ['bg' => 'rgba(88,166,255,.1)',  'color' => '#388bfd', 'border' => 'rgba(88,166,255,.25)'],
        'gmail'       => ['bg' => 'rgba(248,81,73,.1)',   'color' => '#f85149', 'border' => 'rgba(248,81,73,.25)'],
        'imap'        => ['bg' => 'rgba(63,185,80,.1)',   'color' => '#3fb950', 'border' => 'rgba(63,185,80,.25)'],
        'metricscube' => ['bg' => 'rgba(210,153,34,.1)',  'color' => '#d29922', 'border' => 'rgba(210,153,34,.25)'],
        'discord'     => ['bg' => 'rgba(88,101,242,.12)', 'color' => '#5865f2', 'border' => 'rgba(88,101,242,.3)'],
        'slack'       => ['bg' => 'rgba(74,21,75,.1)',    'color' => '#e01e5a', 'border' => 'rgba(224,30,90,.3)'],
    ];
    $statusColors = [
        'completed' => ['color' => '#3fb950', 'bg' => 'rgba(63,185,80,.1)',  'border' => 'rgba(63,185,80,.25)'],
        'running'   => ['color' => '#388bfd', 'bg' => 'rgba(88,166,255,.1)', 'border' => 'rgba(88,166,255,.25)'],
        'pending'   => ['color' => '#b45309', 'bg' => 'rgba(251,191,36,.12)', 'border' => 'rgba(251,191,36,.4)'],
        'failed'    => ['color' => '#f85149', 'bg' => 'rgba(248,81,73,.1)',  'border' => 'rgba(248,81,73,.25)'],
    ];
@endphp

<div class="card overflow-hidden">
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th class="px-4 py-2.5 text-left">Connection</th>
                <th class="px-4 py-2.5 text-left">Integration</th>
                <th class="px-4 py-2.5 text-left">Schedule</th>
                <th class="px-4 py-2.5 text-left">Last run</th>
                <th class="px-4 py-2.5 text-left">Status</th>
                <th class="px-4 py-2.5 text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($connections as $conn)
                @php
                    $run    = $conn['latest_run'] ?? null;
                    $status = $run['status'] ?? null;
                    $tc     = $typeColors[$conn['type']] ?? $typeColors['imap'];
                    $sc     = $statusColors[$status] ?? $statusColors['pending'];
                    $active = in_array($status, ['pending', 'running']);
                @endphp
                <tr class="tbl-row" id="conn-row-{{ $conn['id'] }}">
                    <td class="px-4 py-3">
                        <a href="{{ route('synchronizer.connections.show', $conn['id']) }}"
                           class="font-medium text-gray-900 hover:text-brand-700 transition">
                            {{ $conn['name'] }}
                        </a>
                        <div class="text-xs text-gray-400 mt-0.5">{{ $conn['system_slug'] }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1.5">
                            @include('synchronizer._type_icon', ['type' => $conn['type'], 'class' => 'w-4 h-4'])
                            <span class="badge" style="background:{{ $tc['bg'] }}; color:{{ $tc['color'] }}; border-color:{{ $tc['border'] }}">
                                {{ $conn['type'] }}
                            </span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        @if($conn['type'] === 'metricscube')
                            @php $whmcsId = (int)($conn['settings']['whmcs_connection_id'] ?? 0); @endphp
                            @if($whmcsId)
                                <span class="text-gray-400">Runs with WHMCS</span>
                            @else
                                <span style="color:#cf222e">⚠ Missing linked WHMCS</span>
                            @endif
                        @else
                            {{ $conn['schedule_label'] }}
                            @if($conn['next_run_at'])
                                <div class="text-gray-400">next: {{ \Carbon\Carbon::parse($conn['next_run_at'])->diffForHumans() }}</div>
                            @endif
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        @if($run)
                            <span title="{{ $run['created_at'] }}">
                                {{ \Carbon\Carbon::parse($run['created_at'])->diffForHumans() }}
                            </span>
                            @if($run['duration_seconds'])
                                <span class="text-gray-400"> &middot; {{ $run['duration_seconds'] }}s</span>
                            @endif
                        @else
                            <span class="text-gray-300">Never</span>
                        @endif
                    </td>
                    <td class="px-4 py-3" id="status-{{ $conn['id'] }}">
                        @if($status)
                            <span class="badge" style="background:{{ $sc['bg'] }}; color:{{ $sc['color'] }}; border-color:{{ $sc['border'] }}">
                                @if($status === 'running')
                                    <span class="inline-block w-1.5 h-1.5 rounded-full mr-0.5 animate-pulse" style="background:{{ $sc['color'] }}"></span>
                                @elseif($status === 'pending')
                                    <svg class="inline w-3 h-3 mr-0.5 -mt-px" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                                @endif
                                {{ $status }}
                            </span>
                        @else
                            <span class="text-gray-300 text-xs">&mdash;</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <span id="run-btn-{{ $conn['id'] }}">
                            @if($conn['type'] === 'metricscube')
                                <span class="text-xs text-gray-400">auto</span>
                            @elseif($active)
                                <button onclick="stopRun({{ $conn['id'] }}, this)" class="btn btn-danger btn-sm">
                                    <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="1" stroke-linejoin="round"/></svg>
                                    Stop
                                </button>
                            @else
                                <button onclick="openRunModal({{ $conn['id'] }})" class="btn btn-secondary btn-sm">
                                    <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3l14 9-14 9V3z"/></svg>
                                    Run
                                </button>
                            @endif
                            </span>
                            <a href="{{ route('synchronizer.connections.show', $conn['id']) }}" class="btn btn-muted btn-sm">
                                <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Logs
                            </a>
                            <a href="{{ route('synchronizer.connections.edit', $conn['id']) }}" class="btn btn-muted btn-sm">
                                <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Edit
                            </a>
                            <form method="POST" action="{{ route('synchronizer.connections.duplicate', $conn['id']) }}" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-muted btn-sm">
                                    <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    Copy
                                </button>
                            </form>
                            <form method="POST" action="{{ route('synchronizer.connections.destroy', $conn['id']) }}" class="inline"
                                  onsubmit="return confirm('Delete {{ addslashes($conn['name']) }}?')">
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
                    <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">
                        No connections found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@push('scripts')
<script>
const CSRF   = '{{ csrf_token() }}';
const SERVER = {{ $activeServer?->id ?? 'null' }};
const serverParam = SERVER ? `?server=${SERVER}` : '';

// ── Status badge rendering ────────────────────────────────────────────────────
const STATUS_STYLES = {
    completed: { color:'#3fb950', bg:'rgba(63,185,80,.1)',   border:'rgba(63,185,80,.25)' },
    running:   { color:'#388bfd', bg:'rgba(88,166,255,.1)',  border:'rgba(88,166,255,.25)' },
    pending:   { color:'#b45309', bg:'rgba(251,191,36,.12)', border:'rgba(251,191,36,.4)' },
    failed:    { color:'#f85149', bg:'rgba(248,81,73,.1)',   border:'rgba(248,81,73,.25)' },
};

function renderBadge(status) {
    if (!status) return '<span class="text-gray-300 text-xs">&mdash;</span>';
    const s = STATUS_STYLES[status] || STATUS_STYLES.completed;
    let icon = '';
    if (status === 'running') {
        icon = `<span class="inline-block w-1.5 h-1.5 rounded-full mr-0.5 animate-pulse" style="background:${s.color}"></span>`;
    } else if (status === 'pending') {
        icon = `<svg class="inline w-3 h-3 mr-0.5 -mt-px" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>`;
    }
    return `<span class="badge" style="background:${s.bg};color:${s.color};border-color:${s.border}">${icon}${status}</span>`;
}

function renderActions(connId, status) {
    const active = status === 'pending' || status === 'running';
    const stopBtn = `<button onclick="stopRun(${connId},this)" class="btn btn-danger btn-sm"><svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="1" stroke-linejoin="round"/></svg>Stop</button>`;
    const runBtn  = `<button onclick="openRunModal(${connId})" class="btn btn-secondary btn-sm"><svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3l14 9-14 9V3z"/></svg>Run</button>`;
    return active ? stopBtn : runBtn;
}

// ── Live status polling ───────────────────────────────────────────────────────
let _pollTimer = null;

async function pollStatuses() {
    try {
        const res  = await fetch(`/synchronization/connections/statuses${serverParam}`);
        const data = await res.json();
        if (!data.statuses) return;

        let hasActive = false;
        for (const [connId, info] of Object.entries(data.statuses)) {
            const statusCell = document.getElementById(`status-${connId}`);
            const runBtn     = document.getElementById(`run-btn-${connId}`);
            if (statusCell) statusCell.innerHTML = renderBadge(info.status);
            if (runBtn)     runBtn.innerHTML     = renderActions(connId, info.status);
            if (info.status === 'pending' || info.status === 'running') hasActive = true;
        }

        _pollTimer = hasActive
            ? setTimeout(pollStatuses, 3000)
            : null;
    } catch(e) {
        // silently retry later
        _pollTimer = setTimeout(pollStatuses, 5000);
    }
}

function startPolling() {
    if (_pollTimer) return;
    _pollTimer = setTimeout(pollStatuses, 1500);
}

// ── Modals ────────────────────────────────────────────────────────────────────
function openRunAllModal() { document.getElementById('run-all-modal').classList.remove('hidden'); }
function closeRunAllModal() { document.getElementById('run-all-modal').classList.add('hidden'); }

let _runModalConnId = null;
function openRunModal(connId) {
    _runModalConnId = connId;
    document.getElementById('run-modal').classList.remove('hidden');
}
function closeRunModal() {
    document.getElementById('run-modal').classList.add('hidden');
    _runModalConnId = null;
}

// ── Actions ───────────────────────────────────────────────────────────────────
async function doRunAll(mode) {
    closeRunAllModal();
    const btn = document.getElementById('run-all-btn');
    btn.disabled = true;
    btn.innerHTML = '…';
    try {
        await fetch(`/synchronization/run-all${serverParam}`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN': CSRF},
            body: JSON.stringify({mode})
        });
        startPolling();
    } catch(e) {
        alert('Error: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '▶ Run All';
    }
}

async function doRun(mode) {
    const connId = _runModalConnId;
    closeRunModal();
    if (!connId) return;
    try {
        const res  = await fetch(`/synchronization/connections/${connId}/run`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN': CSRF},
            body: JSON.stringify({mode})
        });
        const data = await res.json();
        if (data.run_id) {
            window.location = `/synchronization/connections/${connId}?run_id=${data.run_id}`;
        } else {
            alert('Run failed: ' + (data.error || data.message || JSON.stringify(data)));
        }
    } catch(e) {
        alert('Error: ' + e.message);
    }
}

async function stopRun(connId, btn) {
    btn.disabled = true;
    btn.innerHTML = '...';
    try {
        await fetch(`/synchronization/connections/${connId}/stop`, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': CSRF}
        });
        startPolling();
    } catch(e) {
        btn.disabled = false;
        btn.textContent = 'Stop';
    }
}

async function killAll() {
    if (!confirm('Kill all active runs?')) return;
    const btn = document.getElementById('kill-btn');
    btn.disabled = true;
    btn.innerHTML = '...';
    try {
        await fetch('/synchronization/kill-all', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': CSRF}
        });
        startPolling();
    } catch(e) {
        btn.disabled = false;
        btn.textContent = 'Kill all runs';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>Kill all runs';
    }
}

// Start polling immediately if any active runs on page load
document.addEventListener('DOMContentLoaded', () => {
    const hasActive = document.querySelector('.animate-pulse') ||
        [...document.querySelectorAll('.badge')].some(b => b.textContent.trim().startsWith('pending'));
    if (hasActive) startPolling();
});
</script>
@endpush

@endsection
