<?php

namespace App\Http\Controllers;

use App\DataRelations\AutoResolver;
use App\Models\Account;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Identity;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DataRelationsController extends Controller
{
    // Identity type → routing system type
    private const IDENTITY_SYSTEM = [
        'email'        => 'imap',
        'slack_user'   => 'slack',
        'discord_user' => 'discord',
    ];

    // Routing system type → identity type stored in DB
    private const SYSTEM_IDENTITY = [
        'imap'    => 'email',
        'slack'   => 'slack_user',
        'discord' => 'discord_user',
    ];

    // ─── Overview ────────────────────────────────────────────────────────────

    public function index(): View
    {
        $stats = [
            'conversations_no_company' => Conversation::whereNull('company_id')->count(),
            'accounts_no_company'      => Account::whereNull('company_id')->count(),
            'identities_no_person'     => Identity::whereNull('person_id')->count(),
            'total_conversations'      => Conversation::count(),
            'total_accounts'           => Account::count(),
            'total_identities'         => Identity::count(),
        ];

        // Per-system breakdown for account-based systems
        // Also count unlinked email identities per slug (contacts imported alongside WHMCS clients)
        $accountSystems = Account::select(
                'system_type', 'system_slug',
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(CASE WHEN company_id IS NULL THEN 1 END) as unlinked')
            )
            ->groupBy('system_type', 'system_slug')
            ->orderBy('system_type')
            ->orderBy('system_slug')
            ->get();

        // Count unlinked email contacts per account-based slug
        $accountBasedSlugs = $accountSystems->pluck('system_slug')->unique()->toArray();

        $contactCountsBySlug = [];
        if (!empty($accountBasedSlugs)) {
            $rows = Identity::select('system_slug',
                        DB::raw('COUNT(*) as total'),
                        DB::raw('COUNT(CASE WHEN person_id IS NULL THEN 1 END) as unlinked')
                    )
                    ->where('type', 'email')
                    ->whereIn('system_slug', $accountBasedSlugs)
                    ->groupBy('system_slug')
                    ->get();
            foreach ($rows as $row) {
                $contactCountsBySlug[$row->system_slug] = [
                    'total'    => $row->total,
                    'unlinked' => $row->unlinked,
                ];
            }
        }

        // Attach contact counts to each account system row
        $accountSystems->each(function ($sys) use ($contactCountsBySlug) {
            $counts = $contactCountsBySlug[$sys->system_slug] ?? ['total' => 0, 'unlinked' => 0];
            $sys->contacts_total    = $counts['total'];
            $sys->contacts_unlinked = $counts['unlinked'];
        });

        // Per-system breakdown for identity-based systems.
        // Exclude slugs used by WHMCS/MetricsCube — their email contacts are managed
        // within the account mapping flow, not as a separate identity section.
        $whmcsSlugs = Account::whereIn('system_type', ['whmcs', 'metricscube'])
            ->distinct()->pluck('system_slug')->toArray();

        $identitySystems = Identity::select(
                'system_slug', 'type',
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(CASE WHEN person_id IS NULL THEN 1 END) as unlinked')
            )
            // Never show email identities that belong to WHMCS/MetricsCube systems.
            // Two guards: by slug list AND by system_type stored in meta_json — so new slugs
            // are automatically excluded without any code changes.
            ->where(function ($q) use ($whmcsSlugs) {
                $q->where('type', '!=', 'email')
                  ->orWhere(function ($q2) use ($whmcsSlugs) {
                      $q2->whereNotIn('system_slug', $whmcsSlugs)
                         ->whereRaw("COALESCE(meta_json->>'system_type', '') NOT IN ('whmcs', 'metricscube')");
                  });
            })
            ->groupBy('system_slug', 'type')
            ->orderBy('type')
            ->orderBy('system_slug')
            ->get()
            ->map(function ($row) {
                $row->system_type = self::IDENTITY_SYSTEM[$row->type] ?? $row->type;
                return $row;
            });

        return view('data-relations.index', compact('stats', 'accountSystems', 'identitySystems'));
    }

    // ─── Per-system mapping ───────────────────────────────────────────────────

    public function mapping(Request $request, string $systemType, string $systemSlug): View
    {
        $isAccountSystem = in_array($systemType, ['whmcs', 'metricscube'], true);

        // Guard: if someone tries to access an identity-based mapping for a slug
        // that belongs to an account-based system (WHMCS/MetricsCube), abort.
        // WHMCS contacts are shown inline under account rows — not as a separate identity section.
        if (!$isAccountSystem) {
            $accountBasedSlugs = Account::whereIn('system_type', ['whmcs', 'metricscube'])
                ->distinct()->pluck('system_slug')->toArray();
            if (in_array($systemSlug, $accountBasedSlugs, true)) {
                abort(404);
            }
        }
        $q    = trim($request->input('q', ''));
        $view = $request->input('view', 'unlinked'); // 'unlinked' or 'linked'

        if ($isAccountSystem) {
            $base = Account::where('system_type', $systemType)->where('system_slug', $systemSlug);

            if ($q !== '') {
                $base->where(function ($query) use ($q) {
                    $query->where('external_id', 'ilike', "%{$q}%")
                          ->orWhereRaw("meta_json->>'company_name' ilike ?", ["%{$q}%"])
                          ->orWhereRaw("meta_json->>'email' ilike ?", ["%{$q}%"]);
                });
            }

            $unlinked = (clone $base)->whereNull('company_id')->orderBy('id')
                ->paginate(50, ['*'], 'page')->withQueryString();
            $linked   = (clone $base)->whereNotNull('company_id')->with('company')->orderBy('id')
                ->paginate(50, ['*'], 'page')->withQueryString();

            $stats = [
                'unlinked' => Account::whereNull('company_id')->where('system_type', $systemType)->where('system_slug', $systemSlug)->count(),
                'linked'   => Account::whereNotNull('company_id')->where('system_type', $systemType)->where('system_slug', $systemSlug)->count(),
                'total'    => Account::where('system_type', $systemType)->where('system_slug', $systemSlug)->count(),
            ];

            // Build primary-email → external_id map from ALL accounts (not paginated)
            // so inline contact matching works regardless of pagination state.
            $allAccounts = Account::where('system_type', $systemType)
                ->where('system_slug', $systemSlug)
                ->get(['external_id', 'meta_json']);

            $primaryEmailToExtId = $allAccounts
                ->filter(fn($a) => !empty($a->meta_json['email']))
                ->keyBy(fn($a) => strtolower(trim($a->meta_json['email'])))
                ->map(fn($a) => (string) $a->external_id);

            // Load ALL email identities for this slug and group by account external_id.
            $allIdentities = Identity::where('system_slug', $systemSlug)
                ->where('type', 'email')
                ->with('person')
                ->get();

            $identitiesByExtId = $allIdentities->groupBy(function ($i) use ($primaryEmailToExtId) {
                // 1. Explicit account_external_id stored during ingest
                $extId = $i->meta_json['account_external_id'] ?? null;
                if ($extId) return (string) $extId;
                // 2. Fallback: primary email match across all accounts
                return $primaryEmailToExtId->get($i->value_normalized, '');
            })->reject(fn($group, $key) => $key === ''); // '' = truly unmatched

        } else {
            $identityType = self::SYSTEM_IDENTITY[$systemType] ?? 'email';

            $base = Identity::where('system_slug', $systemSlug)->where('type', $identityType);

            if ($q !== '') {
                $base->where(function ($query) use ($q) {
                    $query->where('value', 'ilike', "%{$q}%")
                          ->orWhereRaw("meta_json->>'display_name' ilike ?", ["%{$q}%"])
                          ->orWhereRaw("meta_json->>'email_hint' ilike ?", ["%{$q}%"]);
                });
            }

            $unlinked = (clone $base)->whereNull('person_id')->orderBy('id')
                ->paginate(50, ['*'], 'page')->withQueryString();
            $linked   = (clone $base)->whereNotNull('person_id')->with('person')->orderBy('id')
                ->paginate(50, ['*'], 'page')->withQueryString();

            $stats = [
                'unlinked' => Identity::whereNull('person_id')->where('system_slug', $systemSlug)->where('type', $identityType)->count(),
                'linked'   => Identity::whereNotNull('person_id')->where('system_slug', $systemSlug)->where('type', $identityType)->count(),
                'total'    => Identity::where('system_slug', $systemSlug)->where('type', $identityType)->count(),
            ];
        }

        // For Discord/Slack: also load channel→company mapping data
        $conversations      = collect();
        $conversationStats  = null;
        if (in_array($systemType, ['discord', 'slack'], true)) {
            $conversations = Conversation::where('channel_type', $systemType)
                ->where('system_slug', $systemSlug)
                ->with('company')
                ->orderByRaw('company_id IS NULL DESC')
                ->orderBy('subject')
                ->get();

            $conversationStats = [
                'total'    => $conversations->count(),
                'unlinked' => $conversations->whereNull('company_id')->count(),
                'linked'   => $conversations->whereNotNull('company_id')->count(),
            ];
        }

        // For non-account systems: no contact-per-account data
        if (!$isAccountSystem) {
            $identitiesByExtId = collect();
        }

        // For WHMCS/MetricsCube: collect email identities NOT matched to any account.
        // These are ticket senders and other contacts without a WHMCS client record.
        $unregisteredUsers = collect();
        $unregisteredStats = null;
        if ($isAccountSystem) {
            $unreg = Identity::where('system_slug', $systemSlug)
                ->where('type', 'email')
                ->where(function ($q) {
                    $q->whereNull(DB::raw("meta_json->>'account_external_id'"))
                      ->orWhereRaw("meta_json->>'account_external_id' = ''");
                })
                ->with('person')
                ->orderBy('value')
                ->get();

            // Also exclude those matched via primary-email fallback (they DO belong to an account)
            $allExtIds = $identitiesByExtId->keys()->all();
            $unregisteredUsers = $unreg->filter(function ($i) use ($allExtIds) {
                // If this identity ended up in $identitiesByExtId via fallback, exclude it
                return true; // Already filtered by missing account_external_id above
            });

            $unregisteredStats = [
                'total'    => $unregisteredUsers->count(),
                'unlinked' => $unregisteredUsers->whereNull('person_id')->count(),
                'linked'   => $unregisteredUsers->whereNotNull('person_id')->count(),
            ];
        }

        return view('data-relations.mapping', compact(
            'systemType', 'systemSlug', 'isAccountSystem', 'stats', 'unlinked', 'linked',
            'conversations', 'conversationStats',
            'identitiesByExtId', 'unregisteredUsers', 'unregisteredStats'
        ));
    }

    // ─── Auto-resolve ─────────────────────────────────────────────────────────

    public function resolveAuto(): RedirectResponse
    {
        $resolver = new AutoResolver();
        $results  = $resolver->resolveAll();

        $msg = "Auto-resolve: "
             . "{$results['accounts_linked']} accounts linked, "
             . "{$results['companies_created']} companies created, "
             . "{$results['identities_linked']} identities linked, "
             . "{$results['people_created']} people created, "
             . "{$results['conversations_filled']} conversations filled.";

        return redirect()->back()->with('success', $msg);
    }

    // ─── Account link / unlink ────────────────────────────────────────────────

    public function linkAccount(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate(['company_id' => 'required|exists:companies,id']);
        $account->update(['company_id' => $data['company_id']]);

        Conversation::whereNull('company_id')
            ->where('system_type', $account->system_type)
            ->where('system_slug', $account->system_slug)
            ->update(['company_id' => $data['company_id']]);

        $resolver = new AutoResolver();
        $resolver->fillActivityCompanies();
        $resolver->linkWhmcsPersonsToCompanies();

        return redirect()->back()->with('success', 'Account linked to company.');
    }

    public function unlinkAccount(Account $account): RedirectResponse
    {
        $account->update(['company_id' => null]);
        return redirect()->back()->with('success', 'Account unlinked.');
    }

    // ─── Identity link / unlink ───────────────────────────────────────────────

    public function linkIdentity(Request $request, Identity $identity): RedirectResponse
    {
        $data = $request->validate(['person_id' => 'required|exists:people,id']);
        $identity->update(['person_id' => $data['person_id']]);

        return redirect()->back()->with('success', 'Identity linked to person.');
    }

    public function unlinkIdentity(Identity $identity): RedirectResponse
    {
        $identity->update(['person_id' => null]);
        return redirect()->back()->with('success', 'Identity unlinked.');
    }

    // ─── Conversation link / unlink ───────────────────────────────────────────

    public function linkConversation(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate(['company_id' => 'required|exists:companies,id']);
        $conversation->update(['company_id' => $data['company_id']]);
        (new AutoResolver())->fillActivityCompanies();
        return redirect()->back()->with('success', 'Channel linked to company.');
    }

    public function unlinkConversation(Conversation $conversation): RedirectResponse
    {
        $conversation->update(['company_id' => null]);
        return redirect()->back()->with('success', 'Channel unlinked.');
    }

    public function toggleTeamMember(Identity $identity): RedirectResponse
    {
        $newVal = !$identity->is_team_member;
        $identity->update(['is_team_member' => $newVal]);

        // Sync person.is_our_org
        if ($identity->person_id) {
            $person = $identity->person;
            if ($newVal) {
                $person->update(['is_our_org' => true]);
            } else {
                $stillTeam = $person->identities()
                    ->where('id', '!=', $identity->id)
                    ->where('is_team_member', true)
                    ->exists();
                if (!$stillTeam) {
                    $person->update(['is_our_org' => false]);
                }
            }
        }

        $label = $newVal ? 'Marked as team member.' : 'Removed from team.';
        return redirect()->back()->with('success', $label);
    }
}
