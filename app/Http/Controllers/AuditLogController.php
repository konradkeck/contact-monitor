<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index()
    {
        $logs = AuditLog::orderByDesc('created_at')->paginate(50);

        return Inertia::render('AuditLog', [
            'logs' => $logs,
        ]);
    }
}
