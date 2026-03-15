@extends('layouts.app')
@section('title', 'Team Access')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Team Access</h1>
        <p class="text-xs text-gray-400 mt-0.5">Manage who can access this system and what they can do.</p>
    </div>
    @if($activeTab === 'users')
        <a href="{{ route('team-access.users.create') }}" class="btn btn-primary">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            New User
        </a>
    @else
        <a href="{{ route('team-access.groups.create') }}" class="btn btn-primary">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            New Group
        </a>
    @endif
</div>

{{-- Tabs --}}
<div class="flex gap-0 border-b border-gray-200 mb-5">
    @foreach(['users' => 'Users ('.$users->count().')', 'groups' => 'Groups ('.$groups->count().')'] as $tab => $label)
        <a href="{{ request()->fullUrlWithQuery(['tab' => $tab]) }}"
           class="px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition
                  {{ $activeTab === $tab ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- ── USERS TAB ── --}}
@if($activeTab === 'users')

<div class="card overflow-hidden max-w-2xl">
    @if($users->isNotEmpty())
        <table class="w-full text-sm">
            <thead class="tbl-header">
                <tr>
                    <th class="px-4 py-2.5 text-left font-medium">Name</th>
                    <th class="px-4 py-2.5 text-left font-medium">Email</th>
                    <th class="px-4 py-2.5 text-left font-medium">Group</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($users as $user)
                    <tr class="tbl-row">
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <img src="{{ 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user->email ?? ''))) . '?s=28&d=mp' }}"
                                     class="w-7 h-7 rounded-full shrink-0" alt="">
                                <span class="font-medium text-gray-800">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-gray-500 text-xs font-mono">{{ $user->email }}</td>
                        <td class="px-4 py-2.5">
                            <span class="badge {{ match($user->group?->name) { 'Admin' => 'badge-blue', 'Analyst' => 'badge-green', default => 'badge-gray' } }}">
                                {{ $user->group?->name ?? '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('team-access.users.edit', $user) }}" class="row-action">Edit</a>
                                @if($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('team-access.users.destroy', $user) }}"
                                          onsubmit="return confirm('Delete user {{ addslashes($user->name) }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="px-5 py-12 text-center text-sm text-gray-400 italic">No users yet.</div>
    @endif
</div>

@endif

{{-- ── GROUPS TAB ── --}}
@if($activeTab === 'groups')

<div class="card overflow-hidden max-w-3xl">
    @if($groups->isNotEmpty())
        <table class="w-full text-sm">
            <thead class="tbl-header">
                <tr>
                    <th class="px-4 py-2.5 text-left font-medium w-40">Group</th>
                    <th class="px-4 py-2.5 text-left font-medium">Permissions</th>
                    <th class="px-4 py-2.5 text-left font-medium w-36">Users</th>
                    <th class="px-4 py-2.5 w-24"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($groups as $group)
                    <tr class="tbl-row">
                        <td class="px-4 py-3 font-semibold text-gray-800">{{ $group->name }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach($permLabels as $key => $label)
                                    @if($group->hasPermission($key))
                                        <span class="badge badge-blue text-xs">{{ $label }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if($group->users_count > 0)
                                <div class="flex flex-wrap gap-1">
                                    @foreach($users->where('group_id', $group->id)->take(3) as $u)
                                        <span class="text-xs text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">{{ $u->name }}</span>
                                    @endforeach
                                    @if($group->users_count > 3)
                                        <span class="text-xs text-gray-400">+{{ $group->users_count - 3 }} more</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-400 text-xs italic">No users</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('team-access.groups.edit', $group) }}" class="row-action">Edit</a>
                                @if($group->users_count === 0)
                                    <form method="POST" action="{{ route('team-access.groups.destroy', $group) }}"
                                          onsubmit="return confirm('Delete group {{ addslashes($group->name) }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-300" title="Cannot delete: users assigned">Delete</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="px-5 py-12 text-center text-sm text-gray-400 italic">No groups yet.</div>
    @endif
</div>

@endif

@endsection
