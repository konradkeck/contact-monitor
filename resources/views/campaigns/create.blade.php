@extends('layouts.app')
@section('title', 'New Campaign')

@section('content')
<div class="max-w-2xl">
    <div class="mb-5">
        <a href="{{ route('campaigns.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Campaigns</a>
        <h1 class="text-xl font-bold text-gray-900 mt-1">New Campaign</h1>
    </div>

    <form action="{{ route('campaigns.store') }}" method="POST" class="bg-white rounded-lg border border-gray-200 p-5 space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Prompt <span class="text-red-500">*</span></label>
            <textarea name="prompt" rows="6" required placeholder="Describe what this campaign should do…"
                      class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 resize-y">{{ old('prompt') }}</textarea>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('campaigns.index') }}" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-brand-600 text-white text-sm rounded hover:bg-brand-700 transition">Create</button>
        </div>
    </form>
</div>
@endsection
