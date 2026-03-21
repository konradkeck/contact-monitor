<?php

namespace App\Http\Controllers\TeamAccess;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class GroupsController extends Controller
{
    public function index()
    {
        $groups = Group::withCount('users')->orderBy('name')->get();
        $users  = \App\Models\User::with('group')->orderBy('name')->get();
        return Inertia::render('TeamAccess/Index', compact('groups', 'users'));
    }

    public function create()
    {
        return Inertia::render('TeamAccess/GroupForm', ['group' => null, 'permLabels' => self::permLabels()]);
    }

    public function edit(Group $group)
    {
        return Inertia::render('TeamAccess/GroupForm', ['group' => $group, 'permLabels' => self::permLabels()]);
    }

    private static function permLabels(): array
    {
        return [
            'browse_data'   => ['label' => 'Browse Data',   'desc' => 'View dashboard, companies, people, conversations, activity'],
            'data_write'    => ['label' => 'Data Write',    'desc' => 'Create, edit, delete companies, people, and other data'],
            'notes_write'   => ['label' => 'Notes Write',   'desc' => 'Add and edit notes on companies, people, conversations'],
            'analyse'       => ['label' => 'Analyse',        'desc' => 'Access AI analysis features'],
            'configuration' => ['label' => 'Configuration', 'desc' => 'Access all system configuration (connections, mapping, team access, etc.)'],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:groups,name'],
            'permissions' => ['required', 'array'],
        ]);

        $perms = $this->normalizePermissions($data['permissions']);
        Group::create(['name' => $data['name'], 'permissions' => $perms]);

        return redirect()->route('team-access.index', ['tab' => 'groups'])
            ->with('success', "Group {$data['name']} created.");
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', Rule::unique('groups')->ignore($group->id)],
            'permissions' => ['required', 'array'],
        ]);

        $perms = $this->normalizePermissions($data['permissions']);
        $group->update(['name' => $data['name'], 'permissions' => $perms]);

        return redirect()->route('team-access.index', ['tab' => 'groups'])
            ->with('success', "Group {$data['name']} updated.");
    }

    public function destroy(Group $group): RedirectResponse
    {
        if ($group->users()->exists()) {
            return back()->withErrors(['delete' => "Cannot delete group \"{$group->name}\": users are assigned to it."]);
        }

        $name = $group->name;
        $group->delete();

        return redirect()->route('team-access.index', ['tab' => 'groups'])
            ->with('success', "Group {$name} deleted.");
    }

    private function normalizePermissions(array $input): array
    {
        $keys = ['browse_data', 'data_write', 'notes_write', 'analyse', 'configuration'];
        $out  = [];
        foreach ($keys as $key) {
            $out[$key] = (bool) ($input[$key] ?? false);
        }
        return $out;
    }
}
