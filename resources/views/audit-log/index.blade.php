@extends('layouts.app')
@section('title', 'Audit Log')

@section('content')
<div class="mb-5">
    <h1 class="text-xl font-bold text-gray-900">Audit Log</h1>
    <p class="text-sm text-gray-500 mt-1">Immutable record of system actions.</p>
</div>

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">When</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Action</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Entity</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Message</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($logs as $log)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-xs text-gray-400 whitespace-nowrap">
                        {{ $log->created_at->format('Y-m-d H:i:s') }}
                    </td>
                    <td class="px-4 py-2">
                        @php
                            $actionColors = [
                                'created' => 'green', 'updated' => 'blue', 'deleted' => 'red',
                                'added_domain' => 'purple', 'added_alias' => 'purple',
                                'added_identity' => 'purple', 'added_note' => 'yellow',
                            ];
                            $color = $actionColors[$log->action] ?? 'gray';
                        @endphp
                        <x-badge :color="$color">{{ $log->action }}</x-badge>
                    </td>
                    <td class="px-4 py-2 text-xs text-gray-500">
                        {{ class_basename($log->entity_type) }} #{{ $log->entity_id }}
                    </td>
                    <td class="px-4 py-2 text-gray-700">{{ $log->message }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-400 italic">No audit logs yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if($logs->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
