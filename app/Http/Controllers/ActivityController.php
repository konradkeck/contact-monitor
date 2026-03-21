<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsConvSubjectMap;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActivityController extends Controller
{
    use BuildsConvSubjectMap;

    public function index(Request $request): View
    {
        $timelinePage = $this->baseQuery()->cursorPaginate(25);

        $convSubjectMap = $this->buildConvSubjectMap($timelinePage->items());
        $this->prepareTimelineDisplay($timelinePage->items(), $convSubjectMap);

        $convSystems = DB::table('activities')
            ->where('type', 'conversation')
            ->whereRaw("meta_json->>'channel_type' IS NOT NULL")
            ->select(
                DB::raw("meta_json->>'channel_type' as channel_type"),
                DB::raw("meta_json->>'system_slug' as system_slug"),
                DB::raw("meta_json->>'system_type' as system_type")
            )
            ->distinct()->get()->sortBy('channel_type')->values();

        $activityTypes = DB::table('activities')
            ->where('type', '!=', 'conversation')
            ->distinct()->pluck('type')
            ->sortBy(fn ($t) => $t === 'note' ? 'zzz' : $t) // 'note' (Other) always last
            ->values();

        // Initial stats (unfiltered)
        $typeCounts = DB::table('activities')
            ->where('type', '!=', 'conversation')
            ->whereNull('deleted_at')
            ->select('type', DB::raw('count(*) as cnt'))
            ->groupBy('type')
            ->orderByDesc('cnt')
            ->get();

        $convCounts = DB::table('activities')
            ->where('type', 'conversation')
            ->whereNull('deleted_at')
            ->selectRaw("
                COALESCE(
                    meta_json->>'channel_type',
                    CASE meta_json->>'system_type'
                        WHEN 'whmcs'       THEN 'ticket'
                        WHEN 'metricscube' THEN 'ticket'
                        WHEN 'discord'     THEN 'discord'
                        WHEN 'slack'       THEN 'slack'
                        WHEN 'imap'        THEN 'email'
                        WHEN 'gmail'       THEN 'email'
                        ELSE 'conversation'
                    END
                ) as channel_type,
                meta_json->>'system_slug' as system_slug,
                count(*) as cnt
            ")
            ->groupByRaw('1, 2')
            ->orderByDesc('cnt')
            ->get();

        $totalConv = $convCounts->sum('cnt');

        $typeColors = [
            'payment'       => 'bg-green-400',
            'renewal'       => 'bg-blue-400',
            'cancellation'  => 'bg-red-500',
            'ticket'        => 'bg-yellow-400',
            'conversation'  => 'bg-purple-400',
            'note'          => 'bg-gray-400',
            'status_change' => 'bg-slate-300',
            'campaign_run'  => 'bg-slate-300',
            'followup'      => 'bg-slate-300',
        ];

        return view('activity.index', compact(
            'timelinePage', 'convSubjectMap', 'convSystems', 'activityTypes',
            'typeCounts', 'convCounts', 'totalConv', 'typeColors'
        ));
    }

    public function timeline(Request $request)
    {
        $query = $this->baseQuery();

        if ($q = trim($request->get('q', ''))) {
            $query->where(function ($qb) use ($q) {
                $qb->whereRaw("meta_json->>'description' ilike ?", ['%' . $q . '%'])
                    ->orWhereHas('company', fn ($c) => $c->whereNull('merged_into_id')->where('name', 'ilike', '%' . $q . '%'))
                    ->orWhereHas('person', fn ($p) => $p->whereNull('merged_into_id')->whereRaw("(first_name || ' ' || COALESCE(last_name,'')) ilike ?", ['%' . $q . '%']));
            });
        }
        if ($types = $request->get('types')) {
            $query->whereIn('type', (array) $types);
        }
        if ($systems = $request->get('systems')) {
            $pairs = array_filter((array) $systems);
            if (! empty($pairs)) {
                $query->where(function ($q) use ($pairs) {
                    foreach ($pairs as $pair) {
                        [$ch, $slug] = array_pad(explode('|', $pair, 2), 2, '');
                        $q->orWhere(function ($sq) use ($ch, $slug) {
                            $sq->whereRaw("meta_json->>'channel_type' = ?", [$ch])
                                ->whereRaw("meta_json->>'system_slug' = ?", [$slug]);
                        });
                    }
                });
            }
        }
        if ($excludeType = $request->get('exclude_type')) {
            $query->where('type', '!=', $excludeType);
        }
        if ($from = $request->get('from')) {
            $query->whereDate('occurred_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('occurred_at', '<=', $to);
        }

        $page = $query->cursorPaginate(25, ['*'], 'cursor', $request->get('cursor'));
        $convSubjectMap = $this->buildConvSubjectMap($page->items());
        $this->prepareTimelineDisplay($page->items(), $convSubjectMap);

        return view('activity.partials.timeline-items', [
            'activities' => $page->items(),
            'nextCursor' => $page->nextCursor()?->encode(),
            'convSubjectMap' => $convSubjectMap,
        ]);
    }

    public function stats(Request $request)
    {
        $query = Activity::query();

        if ($types = $request->get('types')) {
            $query->whereIn('type', (array) $types);
        }
        if ($excludeType = $request->get('exclude_type')) {
            $query->where('type', '!=', $excludeType);
        }
        if ($systems = $request->get('systems')) {
            $pairs = array_filter((array) $systems);
            if (! empty($pairs)) {
                $query->where(function ($q) use ($pairs) {
                    foreach ($pairs as $pair) {
                        [$ch, $slug] = array_pad(explode('|', $pair, 2), 2, '');
                        $q->orWhere(function ($sq) use ($ch, $slug) {
                            $sq->whereRaw("meta_json->>'channel_type' = ?", [$ch])
                                ->whereRaw("meta_json->>'system_slug' = ?", [$slug]);
                        });
                    }
                });
            }
        }
        if ($from = $request->get('from')) {
            $query->whereDate('occurred_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('occurred_at', '<=', $to);
        }

        // Non-conversation type counts
        $typeCounts = (clone $query)
            ->where('type', '!=', 'conversation')
            ->select('type', DB::raw('count(*) as cnt'))
            ->groupBy('type')
            ->orderByDesc('cnt')
            ->get();

        // Conversation counts broken down by effective channel_type + system_slug
        $convCounts = (clone $query)
            ->where('type', 'conversation')
            ->selectRaw("
                COALESCE(
                    meta_json->>'channel_type',
                    CASE meta_json->>'system_type'
                        WHEN 'whmcs'        THEN 'ticket'
                        WHEN 'metricscube'  THEN 'ticket'
                        WHEN 'discord'      THEN 'discord'
                        WHEN 'slack'        THEN 'slack'
                        WHEN 'imap'         THEN 'email'
                        WHEN 'gmail'        THEN 'email'
                        ELSE 'conversation'
                    END
                ) as channel_type,
                meta_json->>'system_slug' as system_slug,
                count(*) as cnt
            ")
            ->groupByRaw('1, 2')
            ->orderByDesc('cnt')
            ->get();

        $totalConv = $convCounts->sum('cnt');

        return view('partials.activity-stats', compact('typeCounts', 'convCounts', 'totalConv'));
    }

    private function baseQuery()
    {
        return Activity::with(['company.mergedInto', 'person.mergedInto'])->orderByDesc('occurred_at');
    }
}
