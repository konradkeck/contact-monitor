@extends('layouts.app')
@section('title', 'Edit ' . $person->full_name)

@section('content')
<div class="max-w-xl">
    <div class="page-header">
        <div>
            <nav aria-label="Breadcrumb" class="page-breadcrumb">
                <a href="{{ route('people.index') }}">People</a>
                <span class="sep">/</span>
                <a href="{{ route('people.show', $person) }}">{{ $person->full_name }}</a>
                <span class="sep">/</span>
                <span class="cur" aria-current="page">Edit</span>
            </nav>
            <h1 class="page-title mt-1">Edit Person</h1>
        </div>
    </div>

    <div class="card p-6">
    <form action="{{ route('people.update', $person) }}" method="POST">
        @csrf @method('PUT')
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name', $person->first_name) }}" required class="input w-full">
                </div>
                <div>
                    <label class="label">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $person->last_name) }}" class="input w-full">
                </div>
            </div>
            <div class="pt-1">
                <label class="flex items-center gap-2.5 cursor-pointer select-none group">
                    <input type="checkbox" name="is_our_org" value="1"
                           {{ old('is_our_org', $person->is_our_org) ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm font-medium text-gray-700">Our Organization</span>
                    <span class="text-xs text-gray-400">(member of our team)</span>
                </label>
            </div>
        </div>
        <div class="flex gap-2 mt-6">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('people.show', $person) }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
    </div>
    <div class="mt-4 pt-4 border-t border-gray-100">
        <form action="{{ route('people.destroy', $person) }}" method="POST" onsubmit="return confirm('Delete this person?')">
            @csrf @method('DELETE')
            <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete this person</button>
        </form>
    </div>
</div>
@endsection
