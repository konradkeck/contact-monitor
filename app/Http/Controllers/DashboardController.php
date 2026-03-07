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
            'companies'     => Company::count(),
            'people'        => Person::count(),
            'conversations' => Conversation::count(),
            'activities'    => Activity::count(),
        ];

        $recentActivities = Activity::with(['company', 'person'])
            ->whereNotNull('company_id')
            ->orderByDesc('occurred_at')
            ->limit(8)
            ->get();

        $recentAuditLogs = AuditLog::orderByDesc('created_at')
            ->limit(6)
            ->get();

        return view('dashboard', compact('stats', 'recentActivities', 'recentAuditLogs'));
    }
}
