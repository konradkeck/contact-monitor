@extends('layouts.app')
@section('title', 'Segmentation')

@section('content')
<div class="flex items-center justify-between mb-5">
    <h1 class="text-xl font-bold text-gray-900">Segmentation</h1>
    <a href="{{ route('brand-products.create') }}" class="px-4 py-2 bg-brand-600 text-white text-sm rounded hover:bg-brand-700 transition">
        + New Segmentation
    </a>
</div>

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Name</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Variant</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Slug</th>
                <th class="px-4 py-3 text-center font-semibold text-gray-600">Companies</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($products as $product)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium">
                        <a href="{{ route('brand-products.show', $product) }}" class="text-brand-700 hover:underline">
                            {{ $product->name }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ $product->variant ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-gray-500 text-xs">{{ $product->slug }}</td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $product->company_statuses_count }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('brand-products.edit', $product) }}" class="text-xs text-gray-400 hover:text-gray-700">Edit</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-400 italic">No brand products.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
