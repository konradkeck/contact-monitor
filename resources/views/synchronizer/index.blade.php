@extends('layouts.app')
@section('title', 'Synchronizer')

@section('content')

<div class="page-header">
    <span class="page-title">Synchronizer — Connections</span>
    <div class="flex items-center gap-2">
        <button onclick="killAll()" class="btn btn-danger btn-sm" id="kill-btn">Kill all runs</button>
        <a href="{{ route('synchronizer.index') }}" class="btn btn-secondary btn-sm">Refresh</a>
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
        'pending'   => ['color' => '#8b949e', 'bg' => 'rgba(139,148,158,.1)','border' => 'rgba(139,148,158,.25)'],
        'failed'    => ['color' => '#f85149', 'bg' => 'rgba(248,81,73,.1)',  'border' => 'rgba(248,81,73,.25)'],
    ];
@endphp

<div class="card overflow-hidden">
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th class="px-4 py-2.5 text-left">Connection</th>
                <th class="px-4 py-2.5 text-left">Type</th>
                <th class="px-4 py-2.5 text-left">Schedule</th>
                <th class="px-4 py-2.5 text-left">Last run</th>
                <th class="px-4 py-2.5 text-left">Status</th>
                <th class="px-4 py-2.5 text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($connections as $conn)
                @php
                    $run     = $conn['latest_run'] ?? null;
                    $status  = $run['status'] ?? null;
                    $tc      = $typeColors[$conn['type']] ?? $typeColors['imap'];
                    $sc      = $statusColors[$status] ?? $statusColors['pending'];
                    $active  = in_array($status, ['pending', 'running']);
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
                        <span class="badge" style="background:{{ $tc['bg'] }}; color:{{ $tc['color'] }}; border-color:{{ $tc['border'] }}">
                            {{ $conn['type'] }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        {{ $conn['schedule_label'] }}
                        @if($conn['next_run_at'])
                            <div class="text-gray-400">next: {{ \Carbon\Carbon::parse($conn['next_run_at'])->diffForHumans() }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        @if($run)
                            <span title="{{ $run['created_at'] }}">
                                {{ \Carbon\Carbon::parse($run['created_at'])->diffForHumans() }}
                            </span>
                            @if($run['duration_seconds'])
                                <span class="text-gray-400"> · {{ $run['duration_seconds'] }}s</span>
                            @endif
                        @else
                            <span class="text-gray-300">Never</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($status)
                            <span class="badge" style="background:{{ $sc['bg'] }}; color:{{ $sc['color'] }}; border-color:{{ $sc['border'] }}">
                                @if($status === 'running')
                                    <span class="inline-block w-1.5 h-1.5 rounded-full mr-0.5 animate-pulse" style="background:{{ $sc['color'] }}"></span>
                                @endif
                                {{ $status }}
                            </span>
                        @else
                            <span class="text-gray-300 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            @if($active)
                                <button onclick="stopRun({{ $conn['id'] }}, this)"
                                        class="btn btn-danger btn-sm">Stop</button>
                            @else
                                <button onclick="triggerRun({{ $conn['id'] }}, 'partial', this)"
                                        class="btn btn-secondary btn-sm">Run</button>
                                <button onclick="triggerRun({{ $conn['id'] }}, 'full', this)"
                                        class="btn btn-muted btn-sm">Full</button>
                            @endif
                            <a href="{{ route('synchronizer.connections.show', $conn['id']) }}"
                               class="btn btn-muted btn-sm">Logs</a>
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
async function triggerRun(id, mode, btn) {
    btn.disabled = true;
    btn.textContent = '…';
    try {
        const res = await fetch(`/synchronizer/connections/${id}/run`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({mode})
        });
        const data = await res.json();
        if (data.run_id) {
            window.location = `/synchronizer/connections/${id}?run_id=${data.run_id}`;
        }
    } catch(e) {
        btn.disabled = false;
        btn.textContent = mode === 'full' ? 'Full' : 'Run';
        alert('Error: ' + e.message);
    }
}

async function stopRun(id, btn) {
    btn.disabled = true;
    btn.textContent = '…';
    try {
        await fetch(`/synchronizer/connections/${id}/stop`, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}
        });
        setTimeout(() => location.reload(), 800);
    } catch(e) {
        btn.disabled = false;
        btn.textContent = 'Stop';
    }
}

async function killAll() {
    if (!confirm('Kill all active runs?')) return;
    const btn = document.getElementById('kill-btn');
    btn.disabled = true;
    btn.textContent = '…';
    try {
        await fetch('/synchronizer/kill-all', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}
        });
        setTimeout(() => location.reload(), 800);
    } catch(e) {
        btn.disabled = false;
        btn.textContent = 'Kill all runs';
    }
}
</script>
@endpush

@endsection
