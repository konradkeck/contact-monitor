@extends('layouts.app')
@section('title', $group ? 'Edit Group' : 'Create Group')

@section('content')

<div class="page-header">
    <div>
        <div class="page-breadcrumb">
            <a href="{{ route('team-access.index', ['tab' => 'groups']) }}">Team Access</a>
            <span class="sep">/</span>
            <span class="cur">{{ $group ? 'Edit Group' : 'New Group' }}</span>
        </div>
        <h1 class="page-title mt-1">{{ $group ? 'Edit Group' : 'New Group' }}</h1>
    </div>
</div>

@if($errors->any())
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
        <ul class="space-y-1">
            @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card p-5 max-w-lg">
    <form method="POST"
          action="{{ $group ? route('team-access.groups.update', $group) : route('team-access.groups.store') }}">
        @csrf
        @if($group) @method('PUT') @endif

        <div class="space-y-4">
            <div>
                <label class="label" for="f-name">Group name</label>
                <input id="f-name" type="text" name="name" value="{{ old('name', $group?->name) }}"
                       class="input" required placeholder="e.g. Support">
            </div>

            <div class="space-y-2">
                <p class="label">Permissions (ACL)</p>
                @foreach($permLabels as $key => $def)
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="permissions[{{ $key }}]" value="1"
                               {{ old("permissions.{$key}", $group?->hasPermission($key) ?? false) ? 'checked' : '' }}
                               class="mt-0.5 rounded border-gray-300 cursor-pointer">
                        <span>
                            <span class="text-sm font-medium text-gray-800">{{ $def['label'] }}</span>
                            <span class="block text-xs text-gray-400">{{ $def['desc'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="mt-5 pt-4 border-t border-gray-100 flex items-center gap-2">
            <button type="submit" class="btn btn-primary">
                {{ $group ? 'Save changes' : 'Create Group' }}
            </button>
            <a href="{{ route('team-access.index', ['tab' => 'groups']) }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
