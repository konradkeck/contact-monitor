@extends('layouts.app')
@section('title', 'Edit ' . $person->full_name)

@section('content')
<div class="max-w-xl">
    <div class="mb-5">
        <a href="{{ route('people.show', $person) }}" class="text-sm text-gray-500 hover:text-gray-700">← {{ $person->full_name }}</a>
        <h1 class="text-xl font-bold text-gray-900 mt-1">Edit Person</h1>
    </div>

    <form action="{{ route('people.update', $person) }}" method="POST" class="bg-white rounded-lg border border-gray-200 p-5 space-y-4">
        @csrf @method('PUT')
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                <input type="text" name="first_name" value="{{ old('first_name', $person->first_name) }}" required
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                <input type="text" name="last_name" value="{{ old('last_name', $person->last_name) }}"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
        </div>
        <div class="pt-1">
            <label class="flex items-center gap-2.5 cursor-pointer select-none group">
                <input type="checkbox" name="is_our_org" value="1"
                       {{ old('is_our_org', $person->is_our_org) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm font-medium text-gray-700">Our Organization</span>
                <span class="text-xs text-gray-400">(member of our team)</span>
            </label>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('people.show', $person) }}" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-brand-600 text-white text-sm rounded hover:bg-brand-700 transition">Save</button>
        </div>
    </form>
    <div class="mt-4 pt-4 border-t border-gray-100">
        <form action="{{ route('people.destroy', $person) }}" method="POST" onsubmit="return confirm('Delete this person?')">
            @csrf @method('DELETE')
            <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete this person</button>
        </form>
    </div>
</div>
@endsection
