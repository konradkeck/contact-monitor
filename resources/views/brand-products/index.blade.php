@extends('layouts.app')
@section('title', 'Segmentation')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Segmentation</h1>
        <p class="text-xs text-gray-400 mt-0.5">Define the products and service lines your company offers, then track which pipeline stage each client is in. Use this to segment your customer base and understand adoption across your portfolio.</p>
    </div>
    <a href="{{ route('segmentation.create') }}" class="btn btn-primary">+ New Segmentation</a>
</div>

<div class="card overflow-hidden">
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th class="px-4 py-2.5 text-left">Name</th>
                <th class="px-4 py-2.5 text-left">Variant</th>
                <th class="px-4 py-2.5 text-left">Slug</th>
                <th class="px-4 py-2.5 text-center">Companies</th>
                <th class="px-4 py-2.5"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
                <tr class="tbl-row">
                    <td class="px-4 py-3 font-medium">
                        <a href="{{ route('segmentation.edit', $product) }}" class="link">
                            {{ $product->name }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ $product->variant ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-gray-500 text-xs">{{ $product->slug }}</td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $product->company_statuses_count }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('segmentation.edit', $product) }}" class="btn btn-muted btn-sm">Edit</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-400 italic">No segmentation configured.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
