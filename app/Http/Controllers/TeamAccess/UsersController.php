<?php

namespace App\Http\Controllers\TeamAccess;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function create(): View
    {
        $groups = Group::orderBy('name')->get();
        return view('configuration.team-access.user-form', ['user' => null, 'groups' => $groups]);
    }

    public function edit(User $user): View
    {
        $groups = Group::orderBy('name')->get();
        return view('configuration.team-access.user-form', compact('user', 'groups'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'group_id' => ['required', 'exists:groups,id'],
        ]);

        User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'group_id' => $data['group_id'],
        ]);

        return redirect()->route('team-access.index', ['tab' => 'users'])
            ->with('success', "User {$data['name']} created.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'group_id' => ['required', 'exists:groups,id'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // Prevent admin from demoting themselves
        if ($user->id === Auth::id() && (int) $data['group_id'] !== $user->group_id) {
            $adminGroup = Group::where('name', 'Admin')->first();
            if ($user->group_id === $adminGroup?->id) {
                return back()->withErrors(['group_id' => 'You cannot change your own group.']);
            }
        }

        $update = [
            'name'     => $data['name'],
            'email'    => $data['email'],
            'group_id' => $data['group_id'],
        ];

        if (! empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        $user->update($update);

        return redirect()->route('team-access.index', ['tab' => 'users'])
            ->with('success', "User {$data['name']} updated.");
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->withErrors(['delete' => 'You cannot delete your own account.']);
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('team-access.index', ['tab' => 'users'])
            ->with('success', "User {$name} deleted.");
    }
}
