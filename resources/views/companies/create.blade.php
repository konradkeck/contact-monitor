@extends('layouts.app')
@section('title', 'New Company')

@section('content')
<div class="max-w-xl">
    <div class="mb-5">
        <a href="{{ route('companies.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Companies</a>
        <h1 class="text-xl font-bold text-gray-900 mt-1">New Company</h1>
    </div>

    <form action="{{ route('companies.store') }}" method="POST" class="bg-white rounded-lg border border-gray-200 p-5 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Primary Domain</label>
            <input type="text" name="primary_domain" value="{{ old('primary_domain') }}" placeholder="example.com"
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
            <input type="text" name="timezone" value="{{ old('timezone', 'Europe/Warsaw') }}" placeholder="Europe/Warsaw"
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('companies.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-brand-600 text-white text-sm rounded hover:bg-brand-700 transition">
                Create Company
            </button>
        </div>
    </form>
</div>
@endsection
