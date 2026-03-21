@extends('layouts.app')
@section('title', 'AI Pricing Overrides')

@section('content')

<div class="page-header">
    <div>
        <a href="{{ route('ai-costs.index') }}" class="page-breadcrumb-back">← AI Costs</a>
        <h1 class="page-title">Pricing Overrides</h1>
    </div>
</div>

<div class="card p-5 mb-5 max-w-2xl">
    <p class="text-xs text-gray-500 mb-1">
        Set custom prices per 1M tokens. Leave the table empty to use built-in defaults.<br>
        Defaults are sourced from provider pricing pages.
    </p>
</div>

<form method="POST" action="{{ route('ai-costs.pricing.update') }}" id="pricing-form">
    @csrf

    <div class="flex items-center justify-between mb-4">
        <h2 class="section-header-title">Overrides</h2>
        <button type="button" onclick="addOverrideRow()" class="btn btn-sm btn-secondary">+ Add Override</button>
    </div>

    <div class="card-xl-overflow mb-5">
        <table class="w-full text-sm" id="overrides-table">
            <thead class="tbl-header">
                <tr>
                    <th class="px-4 py-2.5 text-left">Model</th>
                    <th class="px-4 py-2.5 text-right w-40">Input ($/1M)</th>
                    <th class="px-4 py-2.5 text-right w-40">Output ($/1M)</th>
                    <th class="px-4 py-2.5 w-10"></th>
                </tr>
            </thead>
            <tbody id="overrides-body">
                @foreach($overrides as $model => $prices)
                    <tr class="tbl-row override-row">
                        <td class="px-4 py-2">
                            <input type="text" name="overrides[][model]" value="{{ $model }}"
                                   class="input w-full font-mono text-xs" placeholder="model-id" required>
                        </td>
                        <td class="px-4 py-2">
                            <input type="number" name="overrides[][input_price]" value="{{ $prices['input'] }}"
                                   class="input w-full text-right" step="0.0001" min="0" required>
                        </td>
                        <td class="px-4 py-2">
                            <input type="number" name="overrides[][output_price]" value="{{ $prices['output'] }}"
                                   class="input w-full text-right" step="0.0001" min="0" required>
                        </td>
                        <td class="px-4 py-2 text-center">
                            <button type="button" onclick="this.closest('tr').remove()" class="text-gray-400 hover:text-red-500 text-xs">✕</button>
                        </td>
                    </tr>
                @endforeach
                @if(empty($overrides))
                    <tr id="empty-row">
                        <td colspan="4" class="px-4 py-6 text-center empty-state italic">No overrides. Add one above or save to use defaults.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm">Save Overrides</button>
        <a href="{{ route('ai-costs.index') }}" class="btn btn-secondary btn-sm">Cancel</a>
    </div>
</form>

{{-- Defaults reference --}}
<div class="card-xl-overflow mt-6">
    <div class="card-header">
        <span class="section-header-title">Default Prices (read-only reference)</span>
    </div>
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th class="px-4 py-2.5 text-left">Model</th>
                <th class="px-4 py-2.5 text-right">Input ($/1M)</th>
                <th class="px-4 py-2.5 text-right">Output ($/1M)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($defaults as $model => $prices)
                <tr class="tbl-row">
                    <td class="px-4 py-2 font-mono text-xs">{{ $model }}</td>
                    <td class="px-4 py-2 text-right text-gray-600">${{ $prices['input'] }}</td>
                    <td class="px-4 py-2 text-right text-gray-600">${{ $prices['output'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@push('scripts')
<script>
let rowIdx = {{ count($overrides) }};

function addOverrideRow() {
    const emptyRow = document.getElementById('empty-row');
    if (emptyRow) emptyRow.remove();

    const tr = document.createElement('tr');
    tr.className = 'tbl-row override-row';
    tr.innerHTML = `
        <td class="px-4 py-2">
            <input type="text" name="overrides[][model]" value=""
                   class="input w-full font-mono text-xs" placeholder="model-id" required>
        </td>
        <td class="px-4 py-2">
            <input type="number" name="overrides[][input_price]" value="0"
                   class="input w-full text-right" step="0.0001" min="0" required>
        </td>
        <td class="px-4 py-2">
            <input type="number" name="overrides[][output_price]" value="0"
                   class="input w-full text-right" step="0.0001" min="0" required>
        </td>
        <td class="px-4 py-2 text-center">
            <button type="button" onclick="this.closest('tr').remove()" class="text-gray-400 hover:text-red-500 text-xs">✕</button>
        </td>
    `;
    document.getElementById('overrides-body').appendChild(tr);
}
</script>
@endpush

@endsection
