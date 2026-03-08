@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

{{-- Header --}}
<div class="mb-8">
    <h1 class="text-2xl font-bold text-gray-900">Welcome to Contact Monitor</h1>
    <p class="text-gray-500 mt-1 text-sm">Your CRM and data interpretation layer. Work in progress.</p>
</div>

{{-- Stats --}}
<div class="grid grid-cols-4 gap-4 mb-8">
    @php
        $statItems = [
            ['label' => 'Companies',     'value' => $stats['companies'],     'url' => route('companies.index'),     'color' => 'blue'],
            ['label' => 'People',        'value' => $stats['people'],        'url' => route('people.index'),        'color' => 'purple'],
            ['label' => 'Conversations', 'value' => $stats['conversations'], 'url' => route('conversations.index'), 'color' => 'green'],
            ['label' => 'Activities',    'value' => $stats['activities'],    'url' => route('activities.index'),    'color' => 'yellow'],
        ];
        $colorMap = [
            'blue'   => 'bg-blue-50 border-blue-100 text-blue-700',
            'purple' => 'bg-purple-50 border-purple-100 text-purple-700',
            'green'  => 'bg-green-50 border-green-100 text-green-700',
            'yellow' => 'bg-yellow-50 border-yellow-100 text-yellow-700',
        ];
    @endphp

    @foreach($statItems as $item)
        <a href="{{ $item['url'] }}"
           class="block border rounded-lg p-5 hover:shadow-sm transition {{ $colorMap[$item['color']] }}">
            <p class="text-3xl font-bold">{{ $item['value'] }}</p>
            <p class="text-sm font-medium mt-1 opacity-80">{{ $item['label'] }}</p>
        </a>
    @endforeach
</div>

{{-- Two columns --}}
<div class="grid grid-cols-2 gap-6">

    {{-- Recent activity --}}
    <div class="bg-white rounded-lg border border-gray-200">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-sm">Recent Activity</h3>
            <a href="{{ route('activities.index') }}" class="text-xs text-brand-600 hover:underline">View all</a>
        </div>
        @if($recentActivities->isEmpty())
            <p class="px-4 py-6 text-sm text-gray-400 italic text-center">No activity yet.</p>
        @else
            <ul class="divide-y divide-gray-50">
                @foreach($recentActivities as $activity)
                    <li class="px-4 py-2.5 flex items-center gap-3">
                        <x-badge color="gray">{{ $activity->type }}</x-badge>
                        <a href="{{ route('companies.show', $activity->company) }}"
                           class="text-sm text-gray-700 hover:text-brand-700 hover:underline truncate">
                            {{ $activity->company->name }}
                        </a>
                        <span class="ml-auto text-xs text-gray-400 shrink-0">
                            {{ $activity->occurred_at->diffForHumans(short: true) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Audit log --}}
    <div class="bg-white rounded-lg border border-gray-200">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-sm">Audit Log</h3>
            <a href="{{ route('audit-log.index') }}" class="text-xs text-brand-600 hover:underline">View all</a>
        </div>
        @if($recentAuditLogs->isEmpty())
            <p class="px-4 py-6 text-sm text-gray-400 italic text-center">No actions recorded yet.</p>
        @else
            <ul class="divide-y divide-gray-50">
                @foreach($recentAuditLogs as $log)
                    <li class="px-4 py-2.5 flex items-start gap-3">
                        @php
                            $actionColors = ['created'=>'green','updated'=>'blue','deleted'=>'red','added_domain'=>'purple','added_alias'=>'purple','added_identity'=>'purple','added_note'=>'yellow'];
                            $color = $actionColors[$log->action] ?? 'gray';
                        @endphp
                        <x-badge :color="$color">{{ $log->action }}</x-badge>
                        <span class="text-sm text-gray-600 truncate">{{ $log->message }}</span>
                        <span class="ml-auto text-xs text-gray-400 shrink-0">
                            {{ $log->created_at->diffForHumans(short: true) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

</div>

{{-- Quick links --}}
<div class="mt-6 flex gap-3">
    <a href="{{ route('companies.create') }}"
       class="px-4 py-2 bg-brand-600 text-white text-sm rounded hover:bg-brand-700 transition">
        + New Company
    </a>
    <a href="{{ route('people.create') }}"
       class="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded hover:bg-gray-50 transition">
        + New Person
    </a>
</div>

@endsection
