@extends('layouts.app')
@section('title', $brandProduct->name)

@section('content')
<div class="flex items-start justify-between mb-5">
    <div>
        <a href="{{ route('brand-products.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Segmentation</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $brandProduct->name }}</h1>
        @if($brandProduct->variant)
            <p class="text-sm text-gray-500">Variant: {{ $brandProduct->variant }}</p>
        @endif
        <p class="text-xs text-gray-400 font-mono mt-0.5">{{ $brandProduct->slug }}</p>
    </div>
    <a href="{{ route('brand-products.edit', $brandProduct) }}" class="px-3 py-1.5 border border-gray-300 text-sm rounded hover:bg-gray-50">Edit</a>
</div>

<div class="max-w-2xl">
    <div class="bg-white rounded-lg border border-gray-200">
        <div class="px-4 py-3 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800 text-sm">Companies ({{ $brandProduct->companyStatuses->count() }})</h3>
        </div>
        @if($brandProduct->companyStatuses->isEmpty())
            <p class="px-4 py-6 text-sm text-gray-400 italic text-center">No companies associated yet.</p>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Company</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Stage</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Score</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Last Evaluated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($brandProduct->companyStatuses as $status)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2">
                                <a href="{{ route('companies.show', $status->company) }}" class="text-brand-700 hover:underline font-medium">
                                    {{ $status->company->name }}
                                </a>
                            </td>
                            <td class="px-4 py-2"><x-stage-badge :stage="$status->stage" /></td>
                            <td class="px-4 py-2 text-gray-500">{{ $status->evaluation_score ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $status->last_evaluated_at?->format('Y-m-d') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
