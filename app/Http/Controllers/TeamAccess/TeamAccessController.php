<?php

namespace App\Http\Controllers\TeamAccess;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\View\View;

class TeamAccessController extends Controller
{
    public function index(): View
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

        return view('configuration.team-access.index', compact('groups', 'users', 'activeTab', 'permLabels'));
    }
}
