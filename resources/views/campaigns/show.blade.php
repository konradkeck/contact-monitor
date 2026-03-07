@extends('layouts.app')
@section('title', $campaign->name)

@section('content')
<div class="flex items-start justify-between mb-5">
    <div>
        <a href="{{ route('campaigns.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Campaigns</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $campaign->name }}</h1>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('campaigns.edit', $campaign) }}" class="px-3 py-1.5 border border-gray-300 text-sm rounded hover:bg-gray-50">Edit</a>
        <form action="{{ route('campaigns.run', $campaign) }}" method="POST">
            @csrf
            <button type="submit" class="px-3 py-1.5 bg-brand-600 text-white text-sm rounded hover:bg-brand-700 transition">
                ▶ Run
            </button>
        </form>
    </div>
</div>

<div class="grid grid-cols-3 gap-5">
    <div class="col-span-2 space-y-5">

        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Prompt</h3>
            <pre class="text-sm text-gray-700 whitespace-pre-wrap font-sans leading-relaxed">{{ $campaign->prompt }}</pre>
        </div>

        <div class="bg-white rounded-lg border border-gray-200">
            <div class="px-4 py-3 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800 text-sm">Runs ({{ $campaign->runs->count() }})</h3>
            </div>
            @if($campaign->runs->isEmpty())
                <p class="px-4 py-6 text-sm text-gray-400 italic text-center">No runs yet. Click "Run" to queue one.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">#</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Summary</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Generated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($campaign->runs as $run)
                            <tr>
                                <td class="px-4 py-2 text-gray-400">{{ $run->id }}</td>
                                <td class="px-4 py-2">
                                    @php
                                        $statusColors = ['queued'=>'gray','running'=>'blue','completed'=>'green','failed'=>'red'];
                                        $color = $statusColors[$run->status] ?? 'gray';
                                    @endphp
                                    <x-badge :color="$color">{{ $run->status }}</x-badge>
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $run->result_summary ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ $run->generated_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div>
        <div class="bg-white rounded-lg border border-gray-200 p-4 text-sm space-y-2">
            <div class="flex justify-between">
                <span class="text-gray-500">Created</span>
                <span>{{ $campaign->created_at->format('Y-m-d') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Runs</span>
                <span>{{ $campaign->runs->count() }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
