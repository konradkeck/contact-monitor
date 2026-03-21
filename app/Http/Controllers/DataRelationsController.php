<?php

namespace App\Http\Controllers;

use App\DataRelations\AutoResolver;
use App\Models\Account;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Identity;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DataRelationsController extends Controller
{
    // Identity type → routing system type
    private const IDENTITY_SYSTEM = [
        'email' => 'imap',
        'slack_user' => 'slack',
        'discord_user' => 'discord',
    ];

    // Routing system type → identity type stored in DB
    private const SYSTEM_IDENTITY = [
        'imap' => 'email',
        'slack' => 'slack_user',
        'discord' => 'discord_user',
    ];

    // ─── Overview ────────────────────────────────────────────────────────────

    public function index()
    {
        $stats = [
            'conversations_no_company' => Conversation::whereNull('company_id')->count(),
            'accounts_no_company' => Account::whereNull('company_id')->count(),
            'identities_no_person' => Identity::whereNull('person_id')->count(),
            'total_conversations' => Conversation::count(),
            'total_accounts' => Account::count(),
            'total_identities' => Identity::count(),
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
        if (! empty($accountBasedSlugs)) {
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
                    'total' => $row->total,
                    'unlinked' => $row->unlinked,
                ];
            }
        }

        // Attach contact counts to each account system row
        $accountSystems->each(function ($sys) use ($contactCountsBySlug) {
            $counts = $contactCountsBySlug[$sys->system_slug] ?? ['total' => 0, 'unlinked' => 0];
            $sys->contacts_total = $counts['total'];
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

        $cards = [
            ['label' => 'Conversations without company', 'value' => $stats['conversations_no_company'], 'total' => $stats['total_conversations']],
            ['label' => 'Accounts without company',      'value' => $stats['accounts_no_company'],      'total' => $stats['total_accounts']],
            ['label' => 'Identities without person',     'value' => $stats['identities_no_person'],     'total' => $stats['total_identities']],
        ];

        return Inertia::render('DataRelations/Index', compact('stats', 'accountSystems', 'identitySystems', 'cards'));
    }

    // ─── Mapping overview (configuration/mapping) ────────────────────────────

    public function mappingIndex()
    {
        return $this->index();
    }

    // ─── Per-system mapping ───────────────────────────────────────────────────

    public function mapping(Request $request, string $systemType, string $systemSlug)
    {
        $isAccountSystem = in_array($systemType, ['whmcs', 'metricscube'], true);
        $identitiesByExtId = collect();

        // Guard: if someone tries to access an identity-based mapping for a slug
        // that belongs to an account-based system (WHMCS/MetricsCube), abort.
        // WHMCS contacts are shown inline under account rows — not as a separate identity section.
        if (! $isAccountSystem) {
            $accountBasedSlugs = Account::whereIn('system_type', ['whmcs', 'metricscube'])
                ->distinct()->pluck('system_slug')->toArray();
            if (in_array($systemSlug, $accountBasedSlugs, true)) {
                abort(404);
            }
        }
        $q = trim($request->input('q', ''));
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
            $linked = (clone $base)->whereNotNull('company_id')->with('company')->orderBy('id')
                ->paginate(50, ['*'], 'page')->withQueryString();

            $stats = [
                'unlinked' => Account::whereNull('company_id')->where('system_type', $systemType)->where('system_slug', $systemSlug)->count(),
                'linked' => Account::whereNotNull('company_id')->where('system_type', $systemType)->where('system_slug', $systemSlug)->count(),
                'total' => Account::where('system_type', $systemType)->where('system_slug', $systemSlug)->count(),
            ];

            // Build primary-email → external_id map from ALL accounts (not paginated)
            // so inline contact matching works regardless of pagination state.
            $allAccounts = Account::where('system_type', $systemType)
                ->where('system_slug', $systemSlug)
                ->get(['external_id', 'meta_json']);

            $primaryEmailToExtId = $allAccounts
                ->filter(fn ($a) => ! empty($a->meta_json['email']))
                ->keyBy(fn ($a) => strtolower(trim($a->meta_json['email'])))
                ->map(fn ($a) => (string) $a->external_id);

            // Load ALL email identities for this slug and group by account external_id.
            $allIdentities = Identity::where('system_slug', $systemSlug)
                ->where('type', 'email')
                ->with('person')
                ->get();

            $identitiesByExtId = $allIdentities->groupBy(function ($i) use ($primaryEmailToExtId) {
                // 1. Explicit account_external_id stored during ingest
                $extId = $i->meta_json['account_external_id'] ?? null;
                if ($extId) {
                    return (string) $extId;
                }

                // 2. Fallback: primary email match across all accounts
                return $primaryEmailToExtId->get($i->value_normalized, '');
            })->reject(fn ($group, $key) => $key === ''); // '' = truly unmatched

        } else {
            $identityType = self::SYSTEM_IDENTITY[$systemType] ?? 'email';

            $base = Identity::where('system_slug', $systemSlug)->where('type', $identityType)->where('is_bot', false);

            if ($q !== '') {
                $base->where(function ($query) use ($q) {
                    $query->where('value', 'ilike', "%{$q}%")
                        ->orWhereRaw("meta_json->>'display_name' ilike ?", ["%{$q}%"])
                        ->orWhereRaw("meta_json->>'email_hint' ilike ?", ["%{$q}%"]);
                });
            }

            $unlinked = (clone $base)->whereNull('person_id')->orderBy('id')
                ->paginate(50, ['*'], 'page')->withQueryString();
            $linked = (clone $base)->whereNotNull('person_id')->with('person')->orderBy('id')
                ->paginate(50, ['*'], 'page')->withQueryString();

            $stats = [
                'unlinked' => Identity::whereNull('person_id')->where('system_slug', $systemSlug)->where('type', $identityType)->count(),
                'linked' => Identity::whereNotNull('person_id')->where('system_slug', $systemSlug)->where('type', $identityType)->count(),
                'total' => Identity::where('system_slug', $systemSlug)->where('type', $identityType)->count(),
            ];
        }

        // For Discord/Slack: also load channel→company mapping data
        $conversations = collect();
        $conversationStats = null;
        if (in_array($systemType, ['discord', 'slack'], true)) {
            $conversations = Conversation::where('channel_type', $systemType)
                ->where('system_slug', $systemSlug)
                ->with('company')
                ->orderByRaw('company_id IS NULL DESC')
                ->orderBy('subject')
                ->get();

            $conversationStats = [
                'total' => $conversations->count(),
                'unlinked' => $conversations->whereNull('company_id')->count(),
                'linked' => $conversations->whereNotNull('company_id')->count(),
            ];
        }

        // For non-account systems: no contact-per-account data
        if (! $isAccountSystem) {
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

            $unregisteredUsers = $unreg;

            $unregisteredStats = [
                'total' => $unregisteredUsers->count(),
                'unlinked' => $unregisteredUsers->whereNull('person_id')->count(),
                'linked' => $unregisteredUsers->whereNotNull('person_id')->count(),
            ];
        }

        $hasTabs      = $conversationStats !== null;
        $hasWhmcsTabs = $isAccountSystem && $unregisteredStats !== null;
        $activeTab    = $request->input('tab', $hasWhmcsTabs ? 'clients' : 'people');
        $activeView   = $request->input('view', 'unlinked');

        // Pre-compute filter modal URLs on each account
        if ($isAccountSystem) {
            $filterRoute = route('filtering.identity-filter-modal');
            foreach ([$unlinked, $linked] as $collection) {
                foreach ($collection as $account) {
                    $meta    = $account->meta_json ?? [];
                    $acEmail = strtolower(trim($meta['email'] ?? ''));
                    $acName  = $meta['company_name'] ?? $account->external_id;
                    $account->filter_url = $filterRoute . '?' . http_build_query(array_filter([
                        'email'  => $acEmail,
                        'domain' => $acEmail ? substr(strrchr($acEmail, '@'), 1) : '',
                        'name'   => $acName,
                    ]));
                }
            }
        }

        // Pre-compute filter modal URLs and avatar data on each identity (for non-account systems)
        if (! $isAccountSystem) {
            $filterRoute = route('filtering.identity-filter-modal');
            foreach ([$unlinked, $linked] as $collection) {
                foreach ($collection as $identity) {
                    $gEmail = $identity->type === 'email' ? $identity->value : ($identity->meta_json['email_hint'] ?? null);
                    $identity->gravatar_hash = $gEmail ? md5(strtolower(trim($gEmail))) : null;
                    $identity->sys_avatar = null;
                    if (! empty($identity->meta_json['avatar'])) {
                        if (in_array($identity->type, ['discord_user', 'discord_id'])) {
                            $identity->sys_avatar = 'https://cdn.discordapp.com/avatars/' . $identity->value_normalized . '/' . $identity->meta_json['avatar'] . '.webp?size=40';
                        } elseif ($identity->type === 'slack_user') {
                            $identity->sys_avatar = $identity->meta_json['avatar'];
                        }
                    } elseif (in_array($identity->type, ['discord_user', 'discord_id'])) {
                        $idx = (int) substr($identity->value_normalized ?? '0', -1) % 5;
                        $identity->sys_avatar = 'https://cdn.discordapp.com/embed/avatars/' . $idx . '.png';
                    }
                    $idFmEmail  = $gEmail ?? '';
                    $idFmDomain = $idFmEmail ? substr(strrchr($idFmEmail, '@'), 1) : '';
                    $idFmName   = $identity->meta_json['display_name'] ?? $identity->value;
                    $identity->filter_url = $filterRoute . '?' . http_build_query(array_filter([
                        'email'  => $idFmEmail,
                        'domain' => $idFmDomain,
                        'name'   => $idFmName,
                    ]));
                    $identity->has_filter_data = (bool) ($idFmEmail || $idFmDomain);
                }
            }
        }

        // Pre-compute filter URL and gravatar on unregistered users
        if ($isAccountSystem && $unregisteredUsers->isNotEmpty()) {
            $filterRoute = route('filtering.identity-filter-modal');
            foreach ($unregisteredUsers as $identity) {
                $identity->gravatar_hash = md5(strtolower(trim($identity->value)));
                $identity->filter_url = $filterRoute . '?' . http_build_query(array_filter([
                    'email'  => $identity->value,
                    'domain' => substr(strrchr($identity->value, '@'), 1),
                    'name'   => $identity->meta_json['display_name'] ?? '',
                ]));
            }
        }

        return Inertia::render('DataRelations/Mapping', [
            'systemType' => $systemType,
            'systemSlug' => $systemSlug,
            'isAccountSystem' => $isAccountSystem,
            'stats' => $stats,
            'unlinked' => $unlinked,
            'linked' => $linked,
            'conversations' => $conversations,
            'conversationStats' => $conversationStats,
            'identitiesByExtId' => $identitiesByExtId->toArray(),
            'unregisteredUsers' => $unregisteredUsers,
            'unregisteredStats' => $unregisteredStats,
            'hasTabs' => $hasTabs,
            'hasWhmcsTabs' => $hasWhmcsTabs,
            'activeTab' => $activeTab,
            'activeView' => $activeView,
            'searchQuery' => $q,
            'companySearchUrl' => route('companies.search'),
            'personSearchUrl' => route('people.search'),
        ]);
    }

    // ─── Auto-resolve ─────────────────────────────────────────────────────────

    public function resolveAuto(): RedirectResponse
    {
        $resolver = new AutoResolver;
        $results = $resolver->resolveAll();

        $msg = 'Auto-resolve: '
             ."{$results['accounts_linked']} accounts linked, "
             ."{$results['companies_created']} companies created, "
             ."{$results['identities_linked']} identities linked, "
             ."{$results['people_created']} people created, "
             ."{$results['conversations_filled']} conversations filled.";

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

        $resolver = new AutoResolver;
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

    public function linkIdentityWithCreate(Request $request, Identity $identity): JsonResponse
    {
        $mode = $request->input('mode');

        if ($mode === 'new') {
            $data = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name'  => 'nullable|string|max:255',
                'is_our_org' => 'nullable|boolean',
            ]);
            $person = Person::create([
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'] ?? null,
                'is_our_org' => (bool) ($data['is_our_org'] ?? false),
            ]);
            $identity->update(['person_id' => $person->id]);
            return response()->json(['ok' => true]);
        }

        if ($mode === 'existing') {
            $data = $request->validate(['person_id' => 'required|exists:people,id']);
            $identity->update(['person_id' => $data['person_id']]);
            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => false, 'error' => 'Invalid mode.'], 422);
    }

    // ─── Conversation link / unlink ───────────────────────────────────────────

    public function linkConversation(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate(['company_id' => 'required|exists:companies,id']);
        $conversation->update(['company_id' => $data['company_id']]);
        (new AutoResolver)->fillActivityCompanies();

        return redirect()->back()->with('success', 'Channel linked to company.');
    }

    public function unlinkConversation(Conversation $conversation): RedirectResponse
    {
        $conversation->update(['company_id' => null]);

        return redirect()->back()->with('success', 'Channel unlinked.');
    }

    public function toggleBot(Identity $identity): RedirectResponse
    {
        $identity->update(['is_bot' => ! $identity->is_bot]);

        $label = $identity->is_bot ? 'Marked as bot — hidden from mapping.' : 'Bot mark removed.';

        return redirect()->back()->with('success', $label);
    }

}
