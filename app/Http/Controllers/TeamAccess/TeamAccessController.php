<?php

namespace App\Http\Controllers\TeamAccess;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Inertia\Inertia;

class TeamAccessController extends Controller
{
    public function index()
    {
        $groups = Group::withCount('users')->orderBy('name')->get();
        $users  = User::with('group')->orderBy('name')->get();

        $activeTab  = request('tab', 'users');
        $permLabels = [
            'browse_data'   => 'Browse Data',
            'data_write'    => 'Data Write',
            'notes_write'   => 'Notes Write',
            'analyse'       => 'Analyse',
            'configuration' => 'Configuration',
        ];

        return Inertia::render('TeamAccess/Index', [
            'groups'     => $groups,
            'users'      => $users,
            'activeTab'  => $activeTab,
            'permLabels' => $permLabels,
        ]);
    }
}
