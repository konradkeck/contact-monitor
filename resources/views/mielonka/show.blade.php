@extends('layouts.app')
@section('title', $conn['name'] . ' — Mielonka')

@section('content')

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
        'pending'   => ['color' => '#8b949e', 'bg' => 'rgba(139,148,158,.1)','border' => 'rgba(139,148,158,.25)'],
        'failed'    => ['color' => '#f85149', 'bg' => 'rgba(248,81,73,.1)',  'border' => 'rgba(248,81,73,.25)'],
    ];
    $tc = $typeColors[$conn['type']] ?? $typeColors['imap'];
    $latestRun = $runs[0] ?? null;
    $latestStatus = $latestRun['status'] ?? null;
    $active = in_array($latestStatus, ['pending', 'running']);
    $selectedRunId = request()->query('run_id', $latestRun['id'] ?? null);
@endphp

{{-- ─── HEADER ─── --}}
<div class="page-header">
    <div class="flex items-center gap-3">
        <a href="{{ route('mielonka.index') }}" class="text-gray-400 hover:text-gray-600 text-sm">← Connections</a>
        <span class="text-gray-300">/</span>
        <span class="page-title">{{ $conn['name'] }}</span>
        <span class="badge" style="background:{{ $tc['bg'] }}; color:{{ $tc['color'] }}; border-color:{{ $tc['border'] }}">
            {{ $conn['type'] }}
        </span>
    </div>
    <div class="flex items-center gap-2">
        @if($active)
            <button onclick="stopRun({{ $conn['id'] }}, this)" class="btn btn-danger btn-sm">Stop</button>
        @else
            <button onclick="triggerRun({{ $conn['id'] }}, 'partial', this)" class="btn btn-secondary btn-sm">Run</button>
            <button onclick="triggerRun({{ $conn['id'] }}, 'full', this)" class="btn btn-muted btn-sm">Full sync</button>
        @endif
    </div>
</div>

{{-- ─── CONNECTION INFO ─── --}}
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
    @if($latestRun)
        <div>
            <div class="text-xs text-gray-400 mb-0.5">Last run</div>
            <div class="text-gray-700">{{ \Carbon\Carbon::parse($latestRun['created_at'])->diffForHumans() }}</div>
            @if($latestRun['duration_seconds'])
                <div class="text-xs text-gray-400">{{ $latestRun['duration_seconds'] }}s</div>
            @endif
        </div>
        <div>
            <div class="text-xs text-gray-400 mb-0.5">Status</div>
            @php $sc = $statusColors[$latestStatus] ?? $statusColors['pending']; @endphp
            <span class="badge" style="background:{{ $sc['bg'] }}; color:{{ $sc['color'] }}; border-color:{{ $sc['border'] }}">
                @if($latestStatus === 'running')
                    <span class="inline-block w-1.5 h-1.5 rounded-full mr-0.5 animate-pulse" style="background:{{ $sc['color'] }}"></span>
                @endif
                {{ $latestStatus }}
            </span>
        </div>
    @endif
</div>

{{-- ─── LAYOUT: runs table + log viewer side by side ─── --}}
<div class="flex gap-4 items-start" x-data="showPage({{ $conn['id'] }}, {{ $selectedRunId ?? 'null' }})">

    {{-- ─── RUN HISTORY ─── --}}
    <div class="card overflow-hidden flex-shrink-0" style="width:320px">
        <div class="px-4 py-2.5 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
            Run history
        </div>
        <div class="overflow-y-auto" style="max-height:560px">
            @forelse($runs as $run)
                @php $sc = $statusColors[$run['status']] ?? $statusColors['pending']; @endphp
                <button
                    onclick="window.history.pushState({}, '', '?run_id={{ $run['id'] }}')"
                    @click="selectRun({{ $run['id'] }})"
                    :class="activeRunId == {{ $run['id'] }} ? 'bg-brand-50 border-l-2 border-brand-500' : 'border-l-2 border-transparent hover:bg-gray-50'"
                    class="w-full text-left px-4 py-2.5 border-b border-gray-50 transition text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono text-xs text-gray-400">#{{ $run['id'] }}</span>
                        <span class="badge text-xs" style="background:{{ $sc['bg'] }}; color:{{ $sc['color'] }}; border-color:{{ $sc['border'] }}">
                            {{ $run['status'] }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">
                        {{ \Carbon\Carbon::parse($run['created_at'])->format('d M Y, H:i') }}
                        @if($run['duration_seconds'])
                            <span class="text-gray-400"> · {{ $run['duration_seconds'] }}s</span>
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

    {{-- ─── LOG VIEWER ─── --}}
    <div class="card flex flex-col flex-1 overflow-hidden" style="min-height:400px">
        {{-- Log header --}}
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

        {{-- Log output --}}
        <div x-ref="logEl"
             class="flex-1 overflow-y-auto font-mono text-xs p-4 space-y-0.5"
             style="background:#f9fafb; min-height:360px">
            <template x-if="loading">
                <div class="text-gray-400">Loading…</div>
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
                         'text-red-600':    line.level === 'error',
                         'text-amber-600':  line.level === 'warning',
                         'text-gray-600':   !line.level || line.level === 'info',
                     }">
                    <span class="text-gray-300 select-none mr-2"
                          x-text="new Date(line.t).toTimeString().slice(0,8)"></span>
                    <span x-text="line.msg"></span>
                </div>
            </template>
        </div>
    </div>
</div>

@push('scripts')
<script>
function showPage(connId, initialRunId) {
    return {
        connId:    connId,
        activeRunId: initialRunId,
        logs:      [],
        runStatus: null,
        loading:   false,
        _interval: null,

        init() {
            if (this.activeRunId) {
                this.loadLogs(this.activeRunId);
            }
        },

        selectRun(runId) {
            if (this.activeRunId === runId) return;
            this.stopPolling();
            this.activeRunId = runId;
            this.logs = [];
            this.runStatus = null;
            this.loadLogs(runId);
        },

        async loadLogs(runId) {
            this.loading = true;
            try {
                const r = await fetch(`/mielonka/runs/${runId}/logs`);
                const d = await r.json();
                this.logs      = d.log_lines ?? [];
                this.runStatus = d.status;
                this.scrollBottom();
                if (['pending', 'running'].includes(d.status)) {
                    this.startPolling(runId);
                }
            } catch(e) {
                this.logs = [{ t: Date.now(), level: 'error', msg: 'Failed to load logs: ' + e.message }];
            } finally {
                this.loading = false;
            }
        },

        startPolling(runId) {
            this.stopPolling();
            this._interval = setInterval(async () => {
                if (this.activeRunId !== runId) { this.stopPolling(); return; }
                try {
                    const r = await fetch(`/mielonka/runs/${runId}/logs`);
                    const d = await r.json();
                    this.logs      = d.log_lines ?? [];
                    this.runStatus = d.status;
                    this.scrollBottom();
                    if (['completed', 'failed'].includes(d.status)) {
                        this.stopPolling();
                    }
                } catch(_) {}
            }, 2000);
        },

        stopPolling() {
            if (this._interval) { clearInterval(this._interval); this._interval = null; }
        },

        scrollBottom() {
            this.$nextTick(() => {
                const el = this.$refs.logEl;
                if (el) el.scrollTop = el.scrollHeight;
            });
        }
    };
}

async function triggerRun(id, mode, btn) {
    btn.disabled = true;
    btn.textContent = '…';
    try {
        const res = await fetch(`/mielonka/connections/${id}/run`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({mode})
        });
        const data = await res.json();
        if (data.run_id) {
            window.location = `/mielonka/connections/${id}?run_id=${data.run_id}`;
        }
    } catch(e) {
        btn.disabled = false;
        btn.textContent = mode === 'full' ? 'Full sync' : 'Run';
        alert('Error: ' + e.message);
    }
}

async function stopRun(id, btn) {
    btn.disabled = true;
    btn.textContent = '…';
    try {
        await fetch(`/mielonka/connections/${id}/stop`, {
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
