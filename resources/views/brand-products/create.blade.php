@extends('layouts.app')
@section('title', 'New Segmentation')

@section('content')
<div class="max-w-xl">
    <div class="mb-5">
        <a href="{{ route('brand-products.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Segmentation</a>
        <h1 class="text-xl font-bold text-gray-900 mt-1">New Segmentation</h1>
    </div>

    <form action="{{ route('brand-products.store') }}" method="POST" class="bg-white rounded-lg border border-gray-200 p-5 space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Variant <span class="text-xs text-gray-400">(optional)</span></label>
            <input type="text" name="variant" value="{{ old('variant') }}" placeholder="e.g. Cloud, On-Premise"
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Slug <span class="text-xs text-gray-400">(auto-generated if empty)</span></label>
            <input type="text" name="slug" value="{{ old('slug') }}" placeholder="my-product"
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-brand-500">
            @error('slug') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('brand-products.index') }}" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-brand-600 text-white text-sm rounded hover:bg-brand-700 transition">Create</button>
        </div>
    </form>
</div>
@endsection
