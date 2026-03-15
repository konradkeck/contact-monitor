<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsConvSubjectMap;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Identity;
use App\Models\Person;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PersonController extends Controller
{
    use BuildsConvSubjectMap;

    public function index(Request $request): View
    {
        $search = $request->get('q');
        $sort = $request->get('sort', 'updated_at');
        $dir = $request->get('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $tab = $request->get('tab', 'clients');
        if (! in_array($tab, ['clients', 'our_org'])) {
            $tab = 'clients';
        }

        if (! in_array($sort, ['first_name', 'updated_at', 'identities'])) {
            $sort = 'updated_at';
        }

        $query = Person::query()
            ->where('is_our_org', $tab === 'our_org')
            ->when($search, function ($q) use ($search) {
                $term = '%'.strtolower($search).'%';
                $q->where(function ($sub) use ($term) {
                    $sub->whereRaw("LOWER(first_name || ' ' || COALESCE(last_name, '')) LIKE ?", [$term])
                        ->orWhereHas('identities', fn ($i) => $i->whereRaw('LOWER(value_normalized) LIKE ?', [$term]));
                });
            })
            ->with(['companies', 'identities', 'notes'])
            ->withCount('identities');

        match ($sort) {
            'identities' => $query->orderByRaw("(SELECT COUNT(*) FROM identities WHERE person_id=people.id AND deleted_at IS NULL) {$dir}"),
            default => $query->orderBy($sort, $dir),
        };

        // ── Filtered people ────────────────────────────────────
        $filterDomains = SystemSetting::get('filter_domains', []);
        $filterEmails = SystemSetting::get('filter_emails', []);
        $filterContacts = DB::table('filter_contacts')->pluck('person_id')->all();

        $filteredIds = [];
        $filteredReasons = [];

        // By filter_contacts list
        foreach ($filterContacts as $personId) {
            $filteredIds[] = $personId;
            $filteredReasons[$personId] = 'Added to filter list';
        }

        // By email match: people whose email identity value matches a filter_emails entry
        if (! empty($filterEmails)) {
            $emailMatches = DB::table('identities')
                ->where('type', 'email')
                ->whereIn(DB::raw('LOWER(value)'), array_map('strtolower', $filterEmails))
                ->whereNull('deleted_at')
                ->select('person_id', 'value')
                ->get();
            foreach ($emailMatches as $row) {
                if (! isset($filteredReasons[$row->person_id])) {
                    $filteredIds[] = $row->person_id;
                    $filteredReasons[$row->person_id] = "Email match: {$row->value}";
                }
            }
        }

        // By domain match: people whose email domain matches a filter_domains entry
        if (! empty($filterDomains)) {
            $domainQuery = DB::table('identities')
                ->where('type', 'email')
                ->whereNull('deleted_at')
                ->where(function ($q) use ($filterDomains) {
                    foreach ($filterDomains as $domain) {
                        $q->orWhereRaw('LOWER(value) LIKE ?', ['%@'.strtolower($domain)]);
                    }
                })
                ->select('person_id', 'value')
                ->get();
            foreach ($domainQuery as $row) {
                if (! isset($filteredReasons[$row->person_id])) {
                    $domain = substr(strrchr($row->value, '@'), 1);
                    $filteredIds[] = $row->person_id;
                    $filteredReasons[$row->person_id] = "Domain match: {$domain}";
                }
            }
        }

        $filteredIds = array_unique($filteredIds);
        $filteredCount = count($filteredIds);
        $showFiltered = (bool) $request->get('show_filtered');

        if ($showFiltered) {
            if (empty($filteredIds)) {
                $query->whereRaw('1=0');
            } else {
                $query->whereIn('people.id', $filteredIds);
            }
        } else {
            $query->whereNotIn('people.id', $filteredIds ?: [-1]);
        }

        $people = $query->paginate(25)->withQueryString();

        // Last contact: latest message in any conversation the person participated in
        // (includes outbound messages we sent to them, not just messages from the person)
        $personIds = $people->pluck('id');

        // Step 1: find all conversations where each person has sent at least one message
        $personConvs = DB::table('conversation_messages as cm_p')
            ->join('identities as i_p', 'i_p.id', '=', 'cm_p.identity_id')
            ->whereIn('i_p.person_id', $personIds)
            ->select('cm_p.conversation_id', 'i_p.person_id')
            ->distinct()
            ->get();

        $personConvMap = [];
        foreach ($personConvs as $row) {
            $personConvMap[$row->person_id][] = $row->conversation_id;
        }

        // Fallback: for people with no message-based conversations, look for outbound activities
        // where contact_email matches one of the person's known emails.
        // We use activity data directly (no conv link) to avoid bulk/template threads
        // being shown as if they were individual conversations with this person.
        $lastActivityFallback = collect(); // person_id => synthetic last_conv object (no last_conv_id)

        $allConvIds = collect($personConvMap)->flatten()->unique()->values()->all();

        // Step 2: for those conversations, get latest non-system message per conversation
        $latestPerConv = collect();
        if (! empty($allConvIds)) {
            $latestPerConv = DB::table('conversation_messages as cm')
                ->join('conversations as c', 'c.id', '=', 'cm.conversation_id')
                ->whereIn('cm.conversation_id', $allConvIds)
                ->where('cm.direction', '!=', 'system')
                ->select('cm.conversation_id', 'cm.occurred_at', 'c.channel_type', 'c.subject as conv_subject', 'c.id as last_conv_id')
                ->orderByDesc('cm.occurred_at')
                ->get()
                ->unique('conversation_id')
                ->keyBy('conversation_id');
        }

        // Step 3: for each person pick the latest message among their conversations
        $lastMsgs = collect();
        foreach ($personConvMap as $personId => $convIds) {
            $best = null;
            foreach ($convIds as $convId) {
                $msg = $latestPerConv->get($convId);
                if ($msg && (! $best || $msg->occurred_at > $best->occurred_at)) {
                    $best = (object) (array) $msg;
                    $best->person_id = $personId;
                }
            }
            if ($best) {
                $lastMsgs->put($personId, $best);
            }
        }
        $lastMsgs = $lastMsgs->keyBy('person_id');

        // For people with no message-based last contact, fall back to outbound activity data.
        // Find people on this page who still have no last_msg entry.
        $missingIds = $personIds->filter(fn ($id) => ! $lastMsgs->has($id))->values()->all();
        if (! empty($missingIds)) {
            $personEmailMap = DB::table('identities')
                ->whereIn('person_id', $missingIds)
                ->where('type', 'email')
                ->whereNull('deleted_at')
                ->select('person_id', 'value')
                ->get()
                ->groupBy('person_id')
                ->map(fn ($rows) => $rows->pluck('value')->map('strtolower')->toArray());

            // Latest outbound activity per person where contact_email matches their known email.
            // Join conversation to get conv ID and subject for the clickable modal link.
            $fallbackRows = DB::table('activities as a')
                ->leftJoin('conversations as c', function ($join) {
                    $join->whereRaw("c.external_thread_id = a.meta_json->>'conversation_external_id'")
                        ->whereRaw("c.system_slug = a.meta_json->>'system_slug'");
                })
                ->whereIn('a.person_id', $missingIds)
                ->whereRaw("a.meta_json->>'contact_email' IS NOT NULL")
                ->whereRaw("a.meta_json->>'channel_type' IS NOT NULL")
                ->orderByDesc('a.occurred_at')
                ->select(
                    'a.person_id', 'a.occurred_at',
                    DB::raw("a.meta_json->>'channel_type' as channel_type"),
                    DB::raw("COALESCE(c.subject, a.meta_json->>'description') as conv_subject"),
                    DB::raw("LOWER(a.meta_json->>'contact_email') as contact_email"),
                    'c.id as last_conv_id'
                )
                ->get()
                ->filter(function ($row) use ($personEmailMap) {
                    $emails = $personEmailMap->get($row->person_id, []);

                    return in_array($row->contact_email, $emails, true);
                })
                ->unique('person_id'); // keep only latest per person (already ordered desc)

            foreach ($fallbackRows as $row) {
                $lastMsgs->put($row->person_id, (object) [
                    'person_id' => $row->person_id,
                    'occurred_at' => $row->occurred_at,
                    'channel_type' => $row->channel_type,
                    'conv_subject' => $row->conv_subject,
                    'last_conv_id' => $row->last_conv_id,
                    'activity_date' => substr($row->occurred_at, 0, 10), // YYYY-MM-DD for modal date filter
                ]);
            }
        }

        foreach ($people as $person) {
            $person->last_conv = $lastMsgs->get($person->id);
        }

        $contactBadge = [
            'ticket'       => 'bg-yellow-100 text-yellow-800',
            'conversation' => 'bg-purple-100 text-purple-800',
            'followup'     => 'bg-slate-100 text-slate-700',
        ];

        $sortLink = fn (string $col) =>
            route('people.index', array_merge($request->query(), [
                'sort' => $col,
                'dir'  => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
            ]));
        $sortIcon = fn (string $col) =>
            $sort === $col ? ($dir === 'asc' ? "\u{2191}" : "\u{2193}") : "\u{2195}";

        $channelBadge = [
            'email'   => 'bg-sky-100 text-sky-700',
            'ticket'  => 'bg-amber-100 text-amber-700',
            'slack'   => 'bg-purple-100 text-purple-700',
            'discord' => 'bg-indigo-100 text-indigo-700',
        ];

        $tabCounts = [
            'clients' => Person::where('is_our_org', false)->count(),
            'our_org' => Person::where('is_our_org', true)->count(),
        ];

        return view('people.index', compact(
            'people', 'search', 'sort', 'dir', 'filteredCount', 'filteredReasons', 'showFiltered',
            'contactBadge', 'sortLink', 'sortIcon', 'channelBadge', 'tab', 'tabCounts',
        ));
    }

    public function create(): View
    {
        return view('people.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'is_our_org' => 'nullable|boolean',
        ]);
        $data['is_our_org'] = (bool) ($data['is_our_org'] ?? false);

        $person = Person::create($data);
        AuditLog::record('created', $person, "Created person: {$person->full_name}", $data);

        return redirect()->route('people.show', $person)->with('success', 'Person created.');
    }

    public function show(Request $request, Person $person): View
    {
        $person->load(['identities', 'companies']);

        $notes = $person->notes()->with('user')->orderByDesc('created_at')->get();

        $allCompanies = Company::orderBy('name')->get()
            ->reject(fn ($c) => $person->companies->pluck('id')->contains($c->id));

        $filterDomains  = SystemSetting::get('filter_domains', []);
        $filterEmails   = SystemSetting::get('filter_emails', []);
        $filterSubjects = SystemSetting::get('filter_subjects', []);
        $filteredExtIds = $this->filteredConversationExtIds($filterDomains, $filterEmails, $filterSubjects);

        $timelineQuery = $this->personActivitiesQuery($person);
        $this->excludeFilteredActivities($timelineQuery, $filterDomains, $filterEmails, $filterSubjects);
        $timelinePage = $timelineQuery->cursorPaginate(25);

        $convSubjectMap = $this->buildConvSubjectMap($timelinePage->items());
        $this->prepareTimelineDisplay($timelinePage->items(), $convSubjectMap);

        // Conversations where this person appeared (sender via messages OR participant)
        $convGroups = collect(DB::select('
            SELECT DISTINCT ON (channel_type, system_slug)
                channel_type, system_slug, id AS last_conv_id,
                subject AS last_subject, last_message_at,
                COUNT(*) OVER (PARTITION BY channel_type, system_slug) AS conv_count
            FROM (
                SELECT DISTINCT c.id, c.channel_type, c.system_slug, c.subject, c.last_message_at
                FROM conversations c
                WHERE
                    EXISTS (
                        SELECT 1 FROM conversation_messages cm
                        JOIN identities i ON i.id = cm.identity_id
                        WHERE cm.conversation_id = c.id AND i.person_id = ?
                    )
                    OR EXISTS (
                        SELECT 1 FROM conversation_participants cp
                        WHERE cp.conversation_id = c.id AND cp.person_id = ?
                    )
            ) sub
            ORDER BY channel_type, system_slug, last_message_at DESC
        ', [$person->id, $person->id]))->sortByDesc('last_message_at');

        // Conversation systems for filter dropdown (derived from convGroups)
        $convSystems = $convGroups->map(fn ($g) => (object) [
            'channel_type' => $g->channel_type,
            'system_slug' => $g->system_slug,
        ])->unique(fn ($g) => $g->channel_type.'|'.$g->system_slug)->values();

        $filteredConvCount = empty($filteredExtIds) ? 0 : DB::table('conversations as c')
            ->whereIn('c.external_thread_id', $filteredExtIds)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('conversation_messages as cm')
                ->join('identities as i', 'i.id', '=', 'cm.identity_id')
                ->whereRaw('cm.conversation_id = c.id')
                ->where('i.person_id', $person->id))
            ->count();

        $activityTypes = $this->personActivitiesQuery($person)
            ->where('type', '!=', 'conversation')
            ->reorder()->distinct()->pluck('type')->sort()->values();

        $backLink = $this->resolveBackLink($request);

        $initials = strtoupper(mb_substr($person->first_name, 0, 1) . mb_substr($person->last_name ?? '', 0, 1));

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

        return view('people.show', compact(
            'person', 'notes', 'allCompanies', 'timelinePage', 'convSubjectMap',
            'convGroups', 'convSystems', 'filteredConvCount', 'activityTypes', 'backLink',
            'initials', 'typeColors',
        ));
    }

    public function timeline(Request $request, Person $person)
    {
        $filterDomains  = SystemSetting::get('filter_domains', []);
        $filterEmails   = SystemSetting::get('filter_emails', []);
        $filterSubjects = SystemSetting::get('filter_subjects', []);
        $filteredExtIds = $this->filteredConversationExtIds($filterDomains, $filterEmails, $filterSubjects);

        $query = $this->personActivitiesQuery($person);

        if ($request->boolean('is_filtered')) {
            $this->includeOnlyFilteredActivities($query, $filterDomains, $filterEmails, $filterSubjects);
        } else {
            $this->excludeFilteredActivities($query, $filterDomains, $filterEmails, $filterSubjects);
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
        if ($from = $request->get('from')) {
            $query->whereDate('occurred_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('occurred_at', '<=', $to);
        }

        $page = $query->cursorPaginate(25, ['*'], 'cursor', $request->get('cursor'));
        $convSubjectMap = $this->buildConvSubjectMap($page->items());
        $this->prepareTimelineDisplay($page->items(), $convSubjectMap);

        return view('people.partials.timeline-items', [
            'activities' => $page->items(),
            'convSubjectMap' => $convSubjectMap,
            'nextCursor' => $page->nextCursor()?->encode(),
        ]);
    }

    public function edit(Person $person): View
    {
        return view('people.edit', compact('person'));
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'is_our_org' => 'nullable|boolean',
        ]);
        $data['is_our_org'] = (bool) ($data['is_our_org'] ?? false);

        $oldIsOurOrg = $person->is_our_org;
        $person->update($data);

        // Sync identities.is_team_member when is_our_org changes
        if ((bool) $data['is_our_org'] !== (bool) $oldIsOurOrg) {
            $person->identities()->update(['is_team_member' => $data['is_our_org']]);
        }

        AuditLog::record('updated', $person, "Updated person: {$person->full_name}", $data);

        return redirect()->route('people.show', $person)->with('success', 'Person updated.');
    }

    public function destroy(Person $person): RedirectResponse
    {
        AuditLog::record('deleted', $person, "Deleted person: {$person->full_name}");
        $person->delete();

        return redirect()->route('people.index')->with('success', 'Person deleted.');
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $people = Person::where(function ($query) use ($q) {
            $query->where('first_name', 'ilike', "%{$q}%")
                ->orWhere('last_name', 'ilike', "%{$q}%");
        })
            ->orderBy('first_name')
            ->limit(20)
            ->get(['id', 'first_name', 'last_name']);

        return response()->json($people->map(fn ($p) => [
            'id' => $p->id,
            'name' => trim("{$p->first_name} {$p->last_name}"),
        ]));
    }

    // --- Identities ---

    public function storeIdentity(Request $request, Person $person): RedirectResponse
    {
        $data = $request->validate([
            'type' => 'required|string|max:100',
            'value' => 'required|string|max:500',
            'system_slug' => 'nullable|string|max:100',
        ]);

        $data['system_slug'] ??= 'default';
        $person->identities()->create($data);
        AuditLog::record('added_identity', $person, "Added identity [{$data['type']}] to {$person->full_name}", $data);

        return back()->with('success', 'Identity added.');
    }

    public function destroyIdentity(Person $person, Identity $identity): RedirectResponse
    {
        $identity->delete();

        return back()->with('success', 'Identity removed.');
    }

    // --- Company links ---

    public function linkCompany(Request $request, Person $person): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'role' => 'nullable|string|max:255',
            'started_at' => 'nullable|date',
        ]);

        $person->companies()->attach($data['company_id'], [
            'role' => $data['role'] ?? null,
            'started_at' => $data['started_at'] ?? null,
        ]);

        return back()->with('success', 'Company linked.');
    }

    private function personActivitiesQuery(Person $person): \Illuminate\Database\Eloquent\Builder
    {
        $person->loadMissing('identities');

        $identityIds = $person->identities->pluck('id')->toArray();

        $query = \App\Models\Activity::with('company');

        if (empty($identityIds)) {
            // No identities — only show activities linked directly to this person
            return $query->where('person_id', $person->id)->orderByDesc('occurred_at');
        }

        $idCsv = implode(',', array_map('intval', $identityIds));

        return $query
            ->where(function ($q) use ($idCsv, $person) {
                // 1. Activities tied to conversations (email, discord, slack)
                //    where any of person's identities sent messages
                //    (outgoing team emails where only team sent are intentionally excluded)
                $q->whereRaw("
                    meta_json->>'conversation_external_id' IS NOT NULL
                    AND EXISTS (
                        SELECT 1
                        FROM conversations c
                        JOIN conversation_messages cm ON cm.conversation_id = c.id
                        WHERE cm.identity_id IN ({$idCsv})
                          AND c.external_thread_id = activities.meta_json->>'conversation_external_id'
                          AND c.system_slug = activities.meta_json->>'system_slug'
                    )
                ");

                // 2. MetricsCube ticket activities (legacy format): match by ticket ID
                //    WHMCS tickets are already handled by mechanism 1 via conversation_external_id.
                //    Only for type='conversation' to avoid false matches with invoice/service IDs.
                //    Ticket conversations: external_thread_id = 'ticket_{id}'
                //    MC activities: relation_id = '{id}' (raw number, no prefix)
                $q->orWhereRaw("
                    type = 'conversation'
                    AND meta_json->>'relation_id' IS NOT NULL
                    AND EXISTS (
                        SELECT 1
                        FROM conversations c
                        JOIN conversation_messages cm ON cm.conversation_id = c.id
                        WHERE cm.identity_id IN ({$idCsv})
                          AND c.channel_type = 'ticket'
                          AND c.external_thread_id = 'ticket_' || (activities.meta_json->>'relation_id')
                    )
                ");

                // 3. Activities directly linked to this person (e.g. MetricsCube financial events)
                $q->orWhere('person_id', $person->id);
            })
            ->orderByDesc('occurred_at');
    }

    public function hourlyActivity(Request $request, Person $person): JsonResponse
    {
        $from = $request->filled('from')
            ? \Carbon\Carbon::parse($request->input('from'))->startOfDay()
            : now()->subDays(6)->startOfDay();
        $to = $request->filled('to')
            ? \Carbon\Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        $identityIds = $person->identities()->pluck('id');

        // Count messages by hour (0-23) for this person's identities as sender
        $rows = DB::table('conversation_messages')
            ->whereIn('identity_id', $identityIds)
            ->whereBetween('occurred_at', [$from, $to])
            ->whereNull('deleted_at')
            ->selectRaw("EXTRACT(HOUR FROM occurred_at)::int as hour, COUNT(*) as count")
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');

        $hours = [];
        for ($h = 0; $h < 24; $h++) {
            $hours[$h] = (int) ($rows[$h]->count ?? 0);
        }

        return response()->json(['hours' => $hours, 'total' => array_sum($hours)]);
    }

    public function activityAvailability(Request $request, Person $person): JsonResponse
    {
        $from = $request->filled('from')
            ? \Carbon\Carbon::parse($request->input('from'))->startOfDay()
            : now()->subDays(6)->startOfDay();
        $to = $request->filled('to')
            ? \Carbon\Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        $identityIds = $person->identities()->pluck('id');

        // Min/max active hour per (ISO week, day-of-week)
        $rows = DB::table('conversation_messages')
            ->whereIn('identity_id', $identityIds)
            ->whereBetween('occurred_at', [$from, $to])
            ->whereNull('deleted_at')
            ->selectRaw("
                EXTRACT(ISODOW FROM occurred_at)::int          AS dow,
                DATE_TRUNC('week', occurred_at)::date::text    AS week_start,
                MIN(EXTRACT(HOUR FROM occurred_at)::int)       AS min_hour,
                MAX(EXTRACT(HOUR FROM occurred_at)::int)       AS max_hour
            ")
            ->groupBy(DB::raw("EXTRACT(ISODOW FROM occurred_at), DATE_TRUNC('week', occurred_at)"))
            ->get();

        // For each dow (1=Mon … 7=Sun): count how many weeks had activity at each hour
        $days = [];
        for ($d = 1; $d <= 7; $d++) {
            $days[$d] = array_fill(0, 24, 0);
        }
        foreach ($rows as $row) {
            $dow = (int) $row->dow;
            for ($h = (int) $row->min_hour; $h <= (int) $row->max_hour; $h++) {
                $days[$dow][$h]++;
            }
        }

        return response()->json(['days' => $days]);
    }

    public function unlinkCompany(Person $person, Company $company): RedirectResponse
    {
        $person->companies()->detach($company->id);

        return back()->with('success', 'Company unlinked.');
    }

    // ── Mark as Our Org ──────────────────────────────────────────

    public function markOurOrg(Person $person): JsonResponse
    {
        $person->update(['is_our_org' => true]);

        return response()->json(['ok' => true]);
    }

    public function bulkMarkOurOrg(Request $request): JsonResponse
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        if (empty($ids)) {
            return response()->json(['ok' => false, 'error' => 'No IDs']);
        }
        Person::whereIn('id', $ids)->update(['is_our_org' => true]);

        return response()->json(['ok' => true, 'count' => count($ids)]);
    }

    // ── Assign Company ───────────────────────────────────────────

    public function assignCompanyModal(Request $request): View
    {
        $ids = array_filter(array_map('intval', (array) $request->get('ids', [])));

        return view('people.assign-company-modal', ['ids' => $ids]);
    }

    public function assignCompany(Person $person, Request $request): JsonResponse
    {
        return $this->doAssignCompany([$person->id], $request);
    }

    public function bulkAssignCompany(Request $request): JsonResponse
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));

        return $this->doAssignCompany($ids, $request);
    }

    private function doAssignCompany(array $personIds, Request $request): JsonResponse
    {
        if (empty($personIds)) {
            return response()->json(['ok' => false, 'error' => 'No people selected']);
        }

        $mode = $request->input('mode'); // 'new' | 'existing'

        if ($mode === 'new') {
            $name = trim($request->input('name', ''));
            if (! $name) {
                return response()->json(['ok' => false, 'error' => 'Company name required']);
            }
            $company = Company::create(['name' => $name]);
        } else {
            $company = Company::find((int) $request->input('company_id'));
            if (! $company) {
                return response()->json(['ok' => false, 'error' => 'Company not found']);
            }
        }

        foreach ($personIds as $id) {
            Person::find($id)?->companies()->syncWithoutDetaching([$company->id]);
        }

        return response()->json(['ok' => true, 'company_id' => $company->id, 'company_name' => $company->name]);
    }

}
