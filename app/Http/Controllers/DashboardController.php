<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Note;
use App\Models\Person;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->subDays(30)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        // ── Stats ────────────────────────────────────────────────────────────
        $conversationsCount = Conversation::whereBetween('created_at', [$from, $to])->count();
        $newCompaniesCount  = Company::whereBetween('created_at', [$from, $to])->count();
        $newPeopleCount     = Person::whereBetween('created_at', [$from, $to])->count();

        // ── Filtered person IDs (exclude from active people) ─────────────────
        $filteredIds = DB::table('filter_contacts')->pluck('person_id')->all();

        $filterEmails  = SystemSetting::get('filter_emails', []);
        $filterDomains = SystemSetting::get('filter_domains', []);

        if (!empty($filterEmails)) {
            $ids = DB::table('identities')
                ->where('type', 'email')
                ->whereNotNull('person_id')
                ->whereIn(DB::raw('LOWER(value)'), array_map('strtolower', $filterEmails))
                ->pluck('person_id')
                ->all();
            $filteredIds = array_merge($filteredIds, $ids);
        }

        if (!empty($filterDomains)) {
            $ids = DB::table('identities')
                ->where('type', 'email')
                ->whereNotNull('person_id')
                ->where(function ($q) use ($filterDomains) {
                    foreach ($filterDomains as $domain) {
                        $q->orWhere(DB::raw('LOWER(value)'), 'like', '%@' . strtolower($domain));
                    }
                })
                ->pluck('person_id')
                ->all();
            $filteredIds = array_merge($filteredIds, $ids);
        }

        $filteredIds = array_unique($filteredIds);

        // ── Most Active People (external contacts) ───────────────────────────
        // Conversations link to people via activities (type='conversation', person_id)
        $activePeople = Person::where('is_our_org', false)
            ->when(!empty($filteredIds), fn ($q) => $q->whereNotIn('id', $filteredIds))
            ->selectRaw('people.*, (
                SELECT COUNT(*)
                FROM activities a
                WHERE a.person_id = people.id
                AND a.type = ?
                AND a.deleted_at IS NULL
                AND a.occurred_at BETWEEN ? AND ?
            ) as conv_count', ['conversation', $from, $to])
            ->orderByDesc('conv_count')
            ->limit(8)
            ->get()
            ->filter(fn ($p) => $p->conv_count > 0);

        // ── Most Active Team Members ─────────────────────────────────────────
        $activeTeam = Person::where('is_our_org', true)
            ->selectRaw('people.*, (
                SELECT COUNT(*)
                FROM activities a
                WHERE a.person_id = people.id
                AND a.type = ?
                AND a.deleted_at IS NULL
                AND a.occurred_at BETWEEN ? AND ?
            ) as conv_count', ['conversation', $from, $to])
            ->orderByDesc('conv_count')
            ->limit(8)
            ->get()
            ->filter(fn ($p) => $p->conv_count > 0);

        // ── Recent Notes ─────────────────────────────────────────────────────
        $recentNotes = Note::with(['user', 'links' => fn ($q) => $q->with('linkable')->limit(1)])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (Note $note) {
                $link   = $note->links->first();
                $entity = $link?->linkable;
                $url    = null;
                $name   = null;
                if ($entity instanceof Company) {
                    $url  = route('companies.show', $entity);
                    $name = $entity->name;
                } elseif ($entity instanceof Person) {
                    $url  = route('people.show', $entity);
                    $name = $entity->full_name;
                }
                $note->entity_url  = $url;
                $note->entity_name = $name;
                return $note;
            });

        return view('dashboard', compact(
            'from', 'to',
            'conversationsCount', 'newCompaniesCount', 'newPeopleCount',
            'activePeople', 'activeTeam',
            'recentNotes',
        ));
    }
}
