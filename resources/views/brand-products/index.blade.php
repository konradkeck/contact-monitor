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
                <th class="col-mobile-hidden px-4 py-2.5 text-left">Variant</th>
                <th class="col-mobile-hidden px-4 py-2.5 text-left">Slug</th>
                <th class="col-mobile-hidden px-4 py-2.5 text-center">Companies</th>
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
                        @if($product->variant)
                            <span class="md:hidden text-xs text-gray-400 ml-1">{{ $product->variant }}</span>
                        @endif
                    </td>
                    <td class="col-mobile-hidden px-4 py-3 text-gray-500">{{ $product->variant ?? '—' }}</td>
                    <td class="col-mobile-hidden px-4 py-3 font-mono text-gray-500 text-xs">{{ $product->slug }}</td>
                    <td class="col-mobile-hidden px-4 py-3 text-center text-gray-500">{{ $product->company_statuses_count }}</td>
                    <td class="px-4 py-3 text-right">
                        {{-- Desktop --}}
                        <div class="row-actions-desktop">
                            <a href="{{ route('segmentation.edit', $product) }}" class="btn btn-muted btn-sm">Edit</a>
                        </div>
                        {{-- Mobile "..." --}}
                        <div class="row-actions-mobile relative" x-data="{ open: false }" @click.outside="open = false" @close-row-dropdowns.window="open = false">
                            <button @click="let o=open; $dispatch('close-row-dropdowns'); open=!o"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="10" cy="16" r="1.5"/></svg>
                            </button>
                            <div x-show="open" x-cloak
                                 class="absolute right-0 top-full mt-1 w-32 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50 text-sm">
                                <a href="{{ route('segmentation.edit', $product) }}"
                                   class="flex w-full px-3 py-2 text-gray-700 hover:bg-gray-50">Edit</a>
                            </div>
                        </div>
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
