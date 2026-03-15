@extends('layouts.app')
@section('title', $user ? 'Edit User' : 'Add User')

@section('content')

<div class="page-header">
    <div>
        <div class="page-breadcrumb">
            <a href="{{ route('team-access.index', ['tab' => 'users']) }}">Team Access</a>
            <span class="sep">/</span>
            <span class="cur">{{ $user ? 'Edit User' : 'New User' }}</span>
        </div>
        <h1 class="page-title mt-1">{{ $user ? 'Edit User' : 'New User' }}</h1>
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
          action="{{ $user ? route('team-access.users.update', $user) : route('team-access.users.store') }}">
        @csrf
        @if($user) @method('PUT') @endif

        <div class="space-y-4">
            <div>
                <label class="label" for="f-name">Name</label>
                <input id="f-name" type="text" name="name" value="{{ old('name', $user?->name) }}"
                       class="input" required placeholder="Full name">
            </div>

            <div>
                <label class="label" for="f-email">Email</label>
                <input id="f-email" type="email" name="email" value="{{ old('email', $user?->email) }}"
                       class="input" required placeholder="user@example.com">
            </div>

            <div>
                <label class="label" for="f-group">Group</label>
                <select id="f-group" name="group_id" class="input" required>
                    <option value="">— select group —</option>
                    @foreach($groups as $group)
                        <option value="{{ $group->id }}" {{ old('group_id', $user?->group_id) == $group->id ? 'selected' : '' }}>
                            {{ $group->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="f-password">
                    Password
                    @if($user) <span class="text-gray-400 font-normal">(leave blank to keep)</span> @endif
                </label>
                <input id="f-password" type="password" name="password"
                       class="input" {{ $user ? '' : 'required' }} placeholder="Min. 8 characters">
            </div>

            <div>
                <label class="label" for="f-password-confirm">Confirm Password</label>
                <input id="f-password-confirm" type="password" name="password_confirmation"
                       class="input" {{ $user ? '' : 'required' }}>
            </div>
        </div>

        <div class="mt-5 pt-4 border-t border-gray-100 flex items-center gap-2">
            <button type="submit" class="btn btn-primary">
                {{ $user ? 'Save changes' : 'Add User' }}
            </button>
            <a href="{{ route('team-access.index', ['tab' => 'users']) }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
