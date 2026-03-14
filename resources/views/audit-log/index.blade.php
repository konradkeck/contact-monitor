@extends('layouts.app')
@section('title', 'Audit Log')

@section('content')
<div class="page-header">
    <div>
        <span class="page-title">Audit Log</span>
        <p class="text-xs text-gray-400 mt-0.5">Immutable record of system actions.</p>
    </div>
</div>

<div class="card overflow-hidden">
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th class="px-4 py-2.5 text-left">When</th>
                <th class="px-4 py-2.5 text-left">Action</th>
                <th class="px-4 py-2.5 text-left">Entity</th>
                <th class="px-4 py-2.5 text-left">Message</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr class="tbl-row">
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
                    <td class="px-4 py-2 text-xs">
                        <span class="text-gray-600 font-medium">{{ class_basename($log->entity_type) }}</span>
                        <span class="text-gray-400 font-mono ml-0.5">#{{ $log->entity_id }}</span>
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
