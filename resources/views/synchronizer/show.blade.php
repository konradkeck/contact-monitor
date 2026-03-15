@extends('layouts.app')
@section('title', $conn['name'] . ' — Synchronizer')

@section('content')

{{-- Run mode popup --}}
<div id="run-modal" class="fixed inset-0 z-50 hidden" onclick="if(event.target===this)closeRunModal()">
    <div class="absolute inset-0 bg-black/25"></div>
    <div class="absolute modal-center bg-white rounded-xl shadow-xl w-80"
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
                    <div class="text-xs text-gray-400 mt-0.5">Re-imports everything from scratch, resetting the cursor. Slower &mdash; use to fix gaps or after connection changes.</div>
                </div>
            </button>
        </div>
    </div>
</div>


{{-- Outer Alpine scope covers everything so header/info/buttons all react to runStatus --}}
<div x-data="showPage({{ $conn['id'] }}, {{ request()->query('run_id', ($runs[0] ?? [])['id'] ?? 'null') }}, {{ json_encode(($runs[0] ?? [])['status'] ?? null) }})">

{{-- HEADER --}}
<div class="page-header">
    <div class="flex items-center gap-3">
        <a href="{{ route('synchronizer.index') }}" class="text-gray-400 hover:text-gray-600 text-sm">&larr; Connections</a>
        <span class="text-gray-300">/</span>
        <h1 class="page-title">{{ $conn['name'] }}</h1>
        <span class="badge" style="background:{{ ($typeColors[$conn['type']] ?? $typeColors['imap'])['bg'] }}; color:{{ ($typeColors[$conn['type']] ?? $typeColors['imap'])['color'] }}; border-color:{{ ($typeColors[$conn['type']] ?? $typeColors['imap'])['border'] }}">
            {{ $conn['type'] }}
        </span>
    </div>
    <div class="flex items-center gap-2">
        <template x-if="runStatus === 'running' || runStatus === 'pending'">
            <button onclick="stopRun({{ $conn['id'] }}, this)" class="btn btn-danger btn-sm">
                <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="1" stroke-linejoin="round"/></svg>
                Stop
            </button>
        </template>
        <template x-if="runStatus !== 'running' && runStatus !== 'pending'">
            <button onclick="openRunModal({{ $conn['id'] }})" class="btn btn-secondary btn-sm">
                <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3l14 9-14 9V3z"/></svg>
                Run
            </button>
        </template>
        <a href="{{ route('synchronizer.connections.edit', $conn['id']) }}" class="btn btn-muted btn-sm">
            <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Edit
        </a>
    </div>
</div>

{{-- CONNECTION INFO --}}
<div class="card p-4 mb-4 flex items-start gap-6 flex-wrap text-sm">
    <div>
        <div class="text-xs text-gray-400 mb-0.5">System slug</div>
        <div class="font-mono text-gray-700">{{ $conn['system_slug'] }}</div>
    </div>
    <div>
        <div class="text-xs text-gray-400 mb-0.5">Schedule</div>
        <div class="text-gray-700">{{ $conn['schedule_label'] }}</div>
        @if($conn['next_run_at'])
            <div class="text-xs text-gray-400">next: {{ \Carbon\Carbon::parse($conn['next_run_at'])->diffForHumans() }}</div>
        @endif
    </div>
    @if($runs[0] ?? null)
        <div>
            <div class="text-xs text-gray-400 mb-0.5">Last run</div>
            <div class="text-gray-700">{{ \Carbon\Carbon::parse($runs[0]['created_at'])->diffForHumans() }}</div>
            @if($runs[0]['duration_seconds'])
                <div class="text-xs text-gray-400">{{ $runs[0]['duration_seconds'] }}s</div>
            @endif
        </div>
    @endif
    <div>
        <div class="text-xs text-gray-400 mb-0.5">Status</div>
        <div class="flex items-center gap-2">
            <span class="badge"
                  :style="`background:${statusBg(runStatus)}; color:${statusColor(runStatus)}; border-color:${statusBorder(runStatus)}`">
                <span x-show="runStatus === 'running' || runStatus === 'pending'"
                      class="inline-block w-1.5 h-1.5 rounded-full mr-0.5 animate-pulse"
                      :style="`background:${statusColor(runStatus)}`"></span>
                <span x-text="runStatus ?? '—'"></span>
            </span>
            <template x-if="runStatus === 'running' || runStatus === 'pending'">
                <span class="flex items-center gap-1 text-blue-500 text-xs font-medium">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                        <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span x-text="runStatus === 'pending' ? 'queued…' : 'syncing…'"></span>
                </span>
            </template>
        </div>
    </div>
</div>

{{-- LAYOUT: run history + log viewer --}}
<div class="flex gap-4 items-start">

    {{-- RUN HISTORY --}}
    <div class="card overflow-hidden flex-shrink-0" style="width:320px">
        <div class="px-4 py-2.5 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
            Run history
        </div>
        <div class="overflow-y-auto" style="max-height:560px">
            @forelse($runs as $run)
                <button
                    onclick="window.history.pushState({}, '', '?run_id={{ $run['id'] }}')"
                    @click="selectRun({{ $run['id'] }})"
                    :class="activeRunId == {{ $run['id'] }} ? 'bg-brand-50 border-l-2 border-brand-500' : 'border-l-2 border-transparent hover:bg-gray-50'"
                    class="w-full text-left px-4 py-2.5 border-b border-gray-50 transition text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono text-xs text-gray-400">#{{ $run['id'] }}</span>
                        <span class="badge text-xs" style="background:{{ ($statusColors[$run['status']] ?? $statusColors['pending'])['bg'] }}; color:{{ ($statusColors[$run['status']] ?? $statusColors['pending'])['color'] }}; border-color:{{ ($statusColors[$run['status']] ?? $statusColors['pending'])['border'] }}">
                            {{ $run['status'] }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">
                        {{ \Carbon\Carbon::parse($run['created_at'])->format('d M Y, H:i') }}
                        @if($run['duration_seconds'])
                            <span class="text-gray-400"> &middot; {{ $run['duration_seconds'] }}s</span>
                        @endif
                    </div>
                    @if($run['triggered_by'] ?? null)
                        <div class="text-xs text-gray-300 mt-0.5">{{ $run['triggered_by'] }}</div>
                    @endif
                </button>
            @empty
                <div class="px-4 py-8 text-center text-sm text-gray-300">No runs yet.</div>
            @endforelse
        </div>
    </div>

    {{-- LOG VIEWER --}}
    <div class="card flex flex-col flex-1 overflow-hidden" style="min-height:400px">
        <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                Logs
                <template x-if="activeRunId">
                    <span class="font-mono font-normal normal-case text-gray-400 ml-1" x-text="'#' + activeRunId"></span>
                </template>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <span class="text-gray-400" x-text="logs.length + ' lines'"></span>
                <span x-show="runStatus === 'running' || runStatus === 'pending'"
                      class="inline-flex items-center gap-1 text-blue-500">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                    live
                </span>
                <span x-show="runStatus === 'completed'" class="text-green-500">completed</span>
                <span x-show="runStatus === 'failed'" class="text-red-500">failed</span>
            </div>
        </div>

        <div x-ref="logEl"
             class="flex-1 overflow-y-auto font-mono text-xs p-4 space-y-0.5"
             style="background:#f9fafb; min-height:360px">
            <template x-if="loading">
                <div class="text-gray-400">Loading...</div>
            </template>
            <template x-if="!loading && logs.length === 0">
                <div class="text-gray-400">
                    <template x-if="activeRunId">
                        <span>No log output yet.</span>
                    </template>
                    <template x-if="!activeRunId">
                        <span>Select a run to view logs.</span>
                    </template>
                </div>
            </template>
            <template x-for="(line, i) in logs" :key="i">
                <div class="leading-5"
                     :class="{
                         'text-red-600':   line.level === 'error',
                         'text-amber-600': line.level === 'warning',
                         'text-gray-600':  !line.level || line.level === 'info',
                     }">
                    <span class="text-gray-300 select-none mr-2"
                          x-text="new Date(line.t).toTimeString().slice(0,8)"></span>
                    <span x-text="line.msg"></span>
                </div>
            </template>
        </div>
    </div>

</div>{{-- /flex layout --}}
</div>{{-- /x-data --}}

@push('scripts')
<script>
const CONNS_URL = '{{ rtrim(url('/configuration/connections/connections'), '/') }}';
const RUNS_URL  = '{{ rtrim(url('/configuration/connections/runs'), '/') }}';

function showPage(connId, initialRunId, initialStatus) {
    return {
        connId:      connId,
        activeRunId: initialRunId,
        logs:        [],
        runStatus:   initialStatus,
        loading:     false,
        _polling:    false,
        _timer:      null,
        _seenCount:  0,

        init() {
            if (this.activeRunId) {
                this.loadLogs(this.activeRunId, true).then(status => {
                    if (['pending', 'running'].includes(status)) {
                        this.startPolling(this.activeRunId);
                    }
                });
            }
        },

        selectRun(runId) {
            if (this.activeRunId === runId) return;
            this.stopPolling();
            this.activeRunId = runId;
            this.logs        = [];
            this._seenCount  = 0;
            this.runStatus   = null;
            this.loadLogs(runId, true).then(status => {
                if (['pending', 'running'].includes(status)) {
                    this.startPolling(runId);
                }
            });
        },

        async loadLogs(runId, initial = false) {
            if (initial) this.loading = true;
            try {
                const r = await fetch(`${RUNS_URL}/${runId}/logs`);
                const d = await r.json();
                const lines = d.log_lines ?? [];
                if (lines.length > this._seenCount) {
                    this.logs.push(...lines.slice(this._seenCount));
                    this._seenCount = lines.length;
                    this.scrollBottom();
                }
                this.runStatus = d.status;
                return d.status;
            } catch(e) {
                if (initial) this.logs = [{ t: Date.now(), level: 'error', msg: 'Failed to load logs: ' + e.message }];
                return null;
            } finally {
                if (initial) this.loading = false;
            }
        },

        startPolling(runId) {
            if (this._polling) return;
            this._polling = true;
            const tick = async () => {
                if (!this._polling || this.activeRunId !== runId) return;
                const status = await this.loadLogs(runId);
                if (this._polling && ['pending', 'running'].includes(status)) {
                    this._timer = setTimeout(tick, 400);
                } else {
                    this._polling = false;
                }
            };
            this._timer = setTimeout(tick, 400);
        },

        stopPolling() {
            this._polling = false;
            if (this._timer) { clearTimeout(this._timer); this._timer = null; }
        },

        scrollBottom() {
            this.$nextTick(() => {
                const el = this.$refs.logEl;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        // Status badge helpers (replaces PHP $statusColors for Alpine-driven badge)
        statusColor(s) {
            return { completed: '#3fb950', running: '#388bfd', pending: '#8b949e', failed: '#f85149' }[s] ?? '#8b949e';
        },
        statusBg(s) {
            return { completed: 'rgba(63,185,80,.1)', running: 'rgba(88,166,255,.1)', pending: 'rgba(139,148,158,.1)', failed: 'rgba(248,81,73,.1)' }[s] ?? 'rgba(139,148,158,.1)';
        },
        statusBorder(s) {
            return { completed: 'rgba(63,185,80,.25)', running: 'rgba(88,166,255,.25)', pending: 'rgba(139,148,158,.25)', failed: 'rgba(248,81,73,.25)' }[s] ?? 'rgba(139,148,158,.25)';
        },
    };
}

let _runModalConnId = null;

function openRunModal(connId) {
    _runModalConnId = connId;
    document.getElementById('run-modal').classList.remove('hidden');
}

function closeRunModal() {
    document.getElementById('run-modal').classList.add('hidden');
    _runModalConnId = null;
}

async function doRun(mode) {
    const connId = _runModalConnId;
    closeRunModal();
    if (!connId) return;
    try {
        const res = await fetch(`${CONNS_URL}/${connId}/run`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({mode})
        });
        const data = await res.json();
        if (data.run_id) {
            window.location = `${CONNS_URL}/${connId}?run_id=${data.run_id}`;
        }
    } catch(e) {
        alert('Error: ' + e.message);
    }
}

async function stopRun(id, btn) {
    btn.disabled = true;
    btn.textContent = '...';
    try {
        await fetch(`${CONNS_URL}/${id}/stop`, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}
        });
        setTimeout(() => location.reload(), 800);
    } catch(e) {
        btn.disabled = false;
        btn.textContent = 'Stop';
    }
}
</script>
@endpush

@endsection
