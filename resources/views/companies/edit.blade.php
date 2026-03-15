@extends('layouts.app')
@section('title', 'Edit ' . $company->name)

@section('content')
<div class="max-w-xl">
    <div class="page-header">
        <div>
            <nav aria-label="Breadcrumb" class="page-breadcrumb">
                <a href="{{ route('companies.index') }}">Companies</a>
                <span class="sep">/</span>
                <a href="{{ route('companies.show', $company) }}">{{ $company->name }}</a>
                <span class="sep">/</span>
                <span class="cur" aria-current="page">Edit</span>
            </nav>
            <h1 class="page-title mt-1">Edit Company</h1>
        </div>
    </div>

    <form action="{{ route('companies.update', $company) }}" method="POST" class="bg-white rounded-lg border border-gray-200 p-5 space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $company->name) }}" required
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Primary Domain <span class="text-xs text-gray-400">(display only)</span></label>
            <input type="text" name="primary_domain" value="{{ old('primary_domain', $company->primary_domain) }}"
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
            <input type="text" name="timezone" value="{{ old('timezone', $company->timezone) }}"
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('companies.show', $company) }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-brand-600 text-white text-sm rounded hover:bg-brand-700 transition">
                Save
            </button>
        </div>
    </form>

    <div class="mt-4 pt-4 border-t border-gray-100">
        <form action="{{ route('companies.destroy', $company) }}" method="POST"
              onsubmit="return confirm('Delete this company? This cannot be undone.')">
            @csrf @method('DELETE')
            <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete this company</button>
        </form>
    </div>
</div>
@endsection
