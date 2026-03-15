@extends('layouts.app')
@section('title', 'Edit ' . $brandProduct->name)

@section('content')
<div class="max-w-xl">
    <div class="page-header">
        <div>
            <div class="page-breadcrumb">
                <a href="{{ route('segmentation.index') }}">Segmentation</a>
                <span class="sep">/</span>
                <span class="cur">{{ $brandProduct->name }}</span>
            </div>
            <h1 class="page-title mt-1">Edit Segmentation</h1>
        </div>
    </div>

    <form action="{{ route('segmentation.update', $brandProduct) }}" method="POST" class="bg-white rounded-lg border border-gray-200 p-5 space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $brandProduct->name) }}" required
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Variant</label>
            <input type="text" name="variant" value="{{ old('variant', $brandProduct->variant) }}"
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
            <input type="text" name="slug" value="{{ old('slug', $brandProduct->slug) }}" required
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-brand-500">
            @error('slug') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('segmentation.index') }}" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-brand-600 text-white text-sm rounded hover:bg-brand-700 transition">Save</button>
        </div>
    </form>
    <div class="mt-4 pt-4 border-t border-gray-100">
        <form action="{{ route('segmentation.destroy', $brandProduct) }}" method="POST" onsubmit="return confirm('Delete?')">
            @csrf @method('DELETE')
            <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete this segmentation</button>
        </form>
    </div>
</div>
@endsection
