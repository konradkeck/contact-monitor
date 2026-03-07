@extends('layouts.app')
@section('title', 'Edit Campaign')

@section('content')
<div class="max-w-2xl">
    <div class="mb-5">
        <a href="{{ route('campaigns.show', $campaign) }}" class="text-sm text-gray-500 hover:text-gray-700">← {{ $campaign->name }}</a>
        <h1 class="text-xl font-bold text-gray-900 mt-1">Edit Campaign</h1>
    </div>

    <form action="{{ route('campaigns.update', $campaign) }}" method="POST" class="bg-white rounded-lg border border-gray-200 p-5 space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input type="text" name="name" value="{{ old('name', $campaign->name) }}" required
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Prompt</label>
            <textarea name="prompt" rows="6" required
                      class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 resize-y">{{ old('prompt', $campaign->prompt) }}</textarea>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('campaigns.show', $campaign) }}" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-brand-600 text-white text-sm rounded hover:bg-brand-700 transition">Save</button>
        </div>
    </form>
</div>
@endsection
