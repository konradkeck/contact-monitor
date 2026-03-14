<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Person;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'companies' => Company::count(),
            'people' => Person::count(),
            'conversations' => Conversation::count(),
            'activities' => Activity::count(),
        ];

        $recentActivities = Activity::with(['company', 'person'])
            ->whereNotNull('company_id')
            ->orderByDesc('occurred_at')
            ->limit(8)
            ->get();

        $recentAuditLogs = AuditLog::orderByDesc('created_at')
            ->limit(6)
            ->get();

        $statItems = [
            ['label' => 'Companies',     'value' => $stats['companies'],     'url' => route('companies.index'),     'color' => 'blue'],
            ['label' => 'People',        'value' => $stats['people'],        'url' => route('people.index'),        'color' => 'purple'],
            ['label' => 'Conversations', 'value' => $stats['conversations'], 'url' => route('conversations.index'), 'color' => 'green'],
            ['label' => 'Activities',    'value' => $stats['activities'],    'url' => route('activity.index'),      'color' => 'yellow'],
        ];

        $statColorMap = [
            'blue'   => 'bg-blue-50 border-blue-100 text-blue-700',
            'purple' => 'bg-purple-50 border-purple-100 text-purple-700',
            'green'  => 'bg-green-50 border-green-100 text-green-700',
            'yellow' => 'bg-yellow-50 border-yellow-100 text-yellow-700',
        ];

        $auditActionColors = [
            'created' => 'green', 'updated' => 'blue', 'deleted' => 'red',
            'added_domain' => 'purple', 'added_alias' => 'purple',
            'added_identity' => 'purple', 'added_note' => 'yellow',
        ];

        return view('dashboard', compact(
            'stats', 'recentActivities', 'recentAuditLogs',
            'statItems', 'statColorMap', 'auditActionColors'
        ));
    }
}
