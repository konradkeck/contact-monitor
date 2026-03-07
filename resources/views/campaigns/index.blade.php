@extends('layouts.app')
@section('title', 'Campaigns')

@section('content')
<div class="flex items-center justify-between mb-5">
    <h1 class="text-xl font-bold text-gray-900">Campaigns</h1>
    <a href="{{ route('campaigns.create') }}" class="px-4 py-2 bg-brand-600 text-white text-sm rounded hover:bg-brand-700 transition">
        + New Campaign
    </a>
</div>

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Name</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 text-center">Runs</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Created</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($campaigns as $campaign)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium">
                        <a href="{{ route('campaigns.show', $campaign) }}" class="text-brand-700 hover:underline">
                            {{ $campaign->name }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $campaign->runs_count }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $campaign->created_at->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('campaigns.edit', $campaign) }}" class="text-xs text-gray-400 hover:text-gray-700">Edit</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-400 italic">No campaigns yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if($campaigns->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $campaigns->links() }}</div>
    @endif
</div>
@endsection
