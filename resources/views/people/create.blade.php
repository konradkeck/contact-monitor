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

    <div class="card p-6">
    <form action="{{ route('people.store') }}" method="POST">
        @csrf
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required class="input w-full">
                </div>
                <div>
                    <label class="label">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" class="input w-full">
                </div>
            </div>
            <div class="pt-1">
                <label class="flex items-center gap-2.5 cursor-pointer select-none group">
                    <input type="checkbox" name="is_our_org" value="1"
                           class="w-4 h-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm font-medium text-gray-700">Our Organization</span>
                    <span class="text-xs text-gray-400">(member of our team)</span>
                </label>
            </div>
        </div>
        <div class="flex gap-2 mt-6">
            <button type="submit" class="btn btn-primary">Create</button>
            <a href="{{ route('people.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
    </div>
</div>
@endsection
