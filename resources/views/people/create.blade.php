@extends('layouts.app')
@section('title', 'New Person')

@section('content')
<div class="max-w-xl">
    <div class="page-header">
        <div>
            <nav aria-label="Breadcrumb" class="page-breadcrumb">
                <a href="{{ route('people.index') }}">People</a>
                <span class="sep">/</span>
                <span class="cur" aria-current="page">New Person</span>
            </nav>
            <h1 class="page-title mt-1">New Person</h1>
        </div>
    </div>

    <form action="{{ route('people.store') }}" method="POST" class="bg-white rounded-lg border border-gray-200 p-5 space-y-4">
        @csrf
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                <input type="text" name="first_name" value="{{ old('first_name') }}" required
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                <input type="text" name="last_name" value="{{ old('last_name') }}"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
        </div>
        <div class="pt-1">
            <label class="flex items-center gap-2.5 cursor-pointer select-none group">
                <input type="checkbox" name="is_our_org" value="1"
                       class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm font-medium text-gray-700">Our Organization</span>
                <span class="text-xs text-gray-400">(member of our team)</span>
            </label>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('people.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-brand-600 text-white text-sm rounded hover:bg-brand-700 transition">Create</button>
        </div>
    </form>
</div>
@endsection
