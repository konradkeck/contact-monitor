<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $types        = Activity::distinct()->orderBy('type')->pluck('type');
        $channelTypes = Activity::where('type', 'conversation')
            ->selectRaw("DISTINCT meta_json->>'channel_type' as ct")
            ->pluck('ct')
            ->filter()
            ->sort()
            ->values();

        $fType    = $request->get('f_type');
        $fChannel = $request->get('f_channel');
        $fFrom    = $request->get('f_from');
        $fTo      = $request->get('f_to');

        $activities = Activity::with(['company', 'person'])
            ->when($fType,    fn ($q) => $q->where('type', $fType))
            ->when($fChannel, fn ($q) => $q->where('type', 'conversation')
                ->whereRaw("meta_json->>'channel_type' = ?", [$fChannel]))
            ->when($fFrom,    fn ($q) => $q->whereDate('occurred_at', '>=', $fFrom))
            ->when($fTo,      fn ($q) => $q->whereDate('occurred_at', '<=', $fTo))
            ->orderByDesc('occurred_at')
            ->paginate(50)
            ->withQueryString();

        return view('activities.index', compact(
            'activities', 'types', 'channelTypes', 'fType', 'fChannel', 'fFrom', 'fTo'
        ));
    }
}
