<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Identity;
use App\Models\Note;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->get('q');
        $sort   = $request->get('sort', 'updated_at');
        $dir    = $request->get('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        if (!in_array($sort, ['first_name', 'updated_at', 'identities'])) {
            $sort = 'updated_at';
        }

        $query = Person::query()
            ->where('is_our_org', false)
            ->when($search, function ($q) use ($search) {
                $term = '%' . strtolower($search) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->whereRaw("LOWER(first_name || ' ' || COALESCE(last_name, '')) LIKE ?", [$term])
                        ->orWhereHas('identities', fn ($i) => $i->whereRaw('LOWER(value_normalized) LIKE ?', [$term]));
                });
            })
            ->with(['companies', 'identities', 'notes'])
            ->withCount('identities');

        match ($sort) {
            'identities' => $query->orderByRaw("(SELECT COUNT(*) FROM identities WHERE person_id=people.id AND deleted_at IS NULL) {$dir}"),
            default       => $query->orderBy($sort, $dir),
        };

        $people = $query->paginate(25)->withQueryString();

        // Last contact: latest conversation_message sent by any of this person's identities
        $personIds = $people->pluck('id');
        $lastMsgs  = DB::table('conversation_messages as cm')
            ->join('identities as i', 'cm.identity_id', '=', 'i.id')
            ->join('conversations as c', 'cm.conversation_id', '=', 'c.id')
            ->whereIn('i.person_id', $personIds)
            ->where('cm.direction', '!=', 'system')
            ->select('i.person_id', 'cm.occurred_at', 'c.channel_type', 'c.subject as conv_subject', 'c.id as last_conv_id')
            ->orderByDesc('cm.occurred_at')
            ->get()
            ->unique('person_id')
            ->keyBy('person_id');

        foreach ($people as $person) {
            $person->last_conv = $lastMsgs->get($person->id);
        }

        return view('people.index', compact('people', 'search', 'sort', 'dir'));
    }

    public function create(): View
    {
        return view('people.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'nullable|string|max:255',
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

        $notes = $person->notes()->orderByDesc('created_at')->get();

        $allCompanies = Company::orderBy('name')->get()
            ->reject(fn ($c) => $person->companies->pluck('id')->contains($c->id));

        $timelinePage = $this->personActivitiesQuery($person)
            ->cursorPaginate(25);

        // Conversations where this person appeared (sender via messages OR participant)
        $convGroups = collect(DB::select("
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
        ", [$person->id, $person->id]))->sortByDesc('last_message_at');

        $backLink = $this->resolveBackLink($request);

        return view('people.show', compact('person', 'notes', 'allCompanies', 'timelinePage', 'convGroups', 'backLink'));
    }

    public function timeline(Request $request, Person $person)
    {
        $query = $this->personActivitiesQuery($person);

        if ($types = $request->get('types')) {
            $query->whereIn('type', (array) $types);
        }
        if ($from = $request->get('from')) {
            $query->whereDate('occurred_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('occurred_at', '<=', $to);
        }

        $page = $query->cursorPaginate(25, ['*'], 'cursor', $request->get('cursor'));

        return view('people.partials.timeline-items', [
            'activities' => $page->items(),
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
            'last_name'  => 'nullable|string|max:255',
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
            'id'   => $p->id,
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

        if (empty($identityIds)) {
            return \App\Models\Activity::with('company')->whereRaw('false');
        }

        $idCsv = implode(',', array_map('intval', $identityIds));

        return \App\Models\Activity::with('company')
            ->where(function ($q) use ($idCsv) {
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

                // 3. WHMCS ticket activities (MetricsCube): match by ticket ID
                //    Only for type='conversation' to avoid false matches with invoice/service IDs
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
            })
            ->orderByDesc('occurred_at');
    }

    public function unlinkCompany(Person $person, Company $company): RedirectResponse
    {
        $person->companies()->detach($company->id);

        return back()->with('success', 'Company unlinked.');
    }
}
