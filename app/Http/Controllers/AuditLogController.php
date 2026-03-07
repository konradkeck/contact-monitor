<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        $logs = AuditLog::orderByDesc('created_at')->paginate(50);

        return view('audit-log.index', compact('logs'));
    }
}
