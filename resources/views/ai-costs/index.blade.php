@extends('layouts.app')
@section('title', 'AI Costs')

@section('content')

<div class="page-header">
    <div class="flex items-center justify-between w-full">
        <h1 class="page-title">AI Costs</h1>
        <a href="{{ route('ai-costs.pricing') }}" class="btn btn-secondary btn-sm">Pricing Overrides</a>
    </div>
</div>

{{-- Totals --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
    <div class="card p-4 text-center">
        <p class="text-xs text-gray-500 mb-1">Total Input Tokens</p>
        <p class="text-lg font-semibold text-gray-800">{{ number_format($totals->total_input ?? 0) }}</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-xs text-gray-500 mb-1">Total Output Tokens</p>
        <p class="text-lg font-semibold text-gray-800">{{ number_format($totals->total_output ?? 0) }}</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-xs text-gray-500 mb-1">Estimated Total Cost</p>
        <p class="text-lg font-semibold text-gray-800">${{ number_format($totals->total_cost ?? 0, 4) }}</p>
    </div>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('ai-costs.index') }}" class="card p-4 mb-4">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label class="label">Action Type</label>
            <select name="action_type" class="input w-full">
                <option value="">All</option>
                @foreach($actionTypes as $key => $label)
                    <option value="{{ $key }}" {{ request('action_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">From</label>
            <input type="date" name="from" class="input w-full" value="{{ request('from') }}">
        </div>
        <div>
            <label class="label">To</label>
            <input type="date" name="to" class="input w-full" value="{{ request('to') }}">
        </div>
    </div>
    <div class="mt-3 flex justify-end gap-2">
        <a href="{{ route('ai-costs.index') }}" class="btn btn-secondary btn-sm">Clear</a>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    </div>
</form>

{{-- Log table --}}
<div class="card-xl-overflow">
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th class="px-4 py-2.5 text-left">Date</th>
                <th class="px-4 py-2.5 text-left">Action</th>
                <th class="px-4 py-2.5 text-left">Model</th>
                <th class="px-4 py-2.5 text-right">Input</th>
                <th class="px-4 py-2.5 text-right">Output</th>
                <th class="px-4 py-2.5 text-right">Cost</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr class="tbl-row">
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3">
                        <span class="badge badge-gray">{{ $actionTypes[$log->action_type] ?? $log->action_type }}</span>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $log->model_name }}</td>
                    <td class="px-4 py-3 text-right text-gray-600">{{ number_format($log->input_tokens) }}</td>
                    <td class="px-4 py-3 text-right text-gray-600">{{ number_format($log->output_tokens) }}</td>
                    <td class="px-4 py-3 text-right font-medium">${{ number_format(($log->cost_input_usd ?? 0) + ($log->cost_output_usd ?? 0), 6) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center empty-state italic">No usage logged yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if($logs->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $logs->links() }}</div>
    @endif
</div>

@endsection
