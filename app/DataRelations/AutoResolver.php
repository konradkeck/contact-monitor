<?php

namespace App\DataRelations;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyAlias;
use App\Models\CompanyDomain;
use App\Models\Conversation;
use App\Models\Identity;
use App\Models\Person;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

class AutoResolver
{
    public array $log = [];

    private const GENERIC_DOMAINS = [
        'gmail.com', 'yahoo.com', 'yahoo.co.uk', 'hotmail.com', 'hotmail.co.uk',
        'outlook.com', 'live.com', 'live.co.uk', 'icloud.com', 'me.com', 'mac.com',
        'aol.com', 'protonmail.com', 'proton.me', 'mail.com', 'gmx.com',
        'zoho.com', 'yandex.com', 'yandex.ru',
        'wp.pl', 'onet.pl', 'interia.pl', 'o2.pl', 'gazeta.pl', 'poczta.fm',
    ];

    // -------------------------------------------------------------------------
    // Team member auto-marking
    // -------------------------------------------------------------------------

    public function markTeamMembers(): void
    {
        $teamDomains = SystemSetting::get('team_domains', []);
        if (empty($teamDomains)) return;

        foreach ($teamDomains as $domain) {
            Identity::where('type', 'email')
                ->whereRaw("value_normalized LIKE ?", ['%@' . strtolower($domain)])
                ->where('is_team_member', false)
                ->update(['is_team_member' => true]);
        }
    }

    /**
     * Run all auto-resolvers and auto-creators. Returns stats array.
     */
    public function resolveAll(): array
    {
        // 0. Mark team member identities by configured domains
        $this->markTeamMembers();

        // 1. Match existing companies/people first
        $accountsLinked     = $this->resolveAccounts();
        $identitiesLinked   = $this->resolveIdentities();

        // 2. Create companies/people for anything still unmatched
        $companiesCreated   = $this->autoCreateCompanies();
        $peopleCreated      = $this->autoCreatePeople();

        // 3. Link people to companies via email ↔ account matching
        $this->linkPeopleToCompanies();

        // 4. Mark team-member activities as internal
        $this->markTeamMemberActivities();

        // 5. Backfill conversations now that more accounts/identities are linked
        $convsFilled        = $this->fillConversationCompanies();

        // 6. Backfill activity.company_id from linked conversation (after conversations get companies)
        $activitiesFilled   = $this->fillActivityCompanies();

        // 7. Backfill target_url on ticket activities
        $ticketsLinked      = $this->linkTicketActivities();

        // 8. Backfill activity.person_id for email activities (sender/recipient resolution)
        $personsFilled      = $this->fillActivityPersons();

        // 9. Backfill activity.person_id for MetricsCube activities via customer name
        $mcPersonsFilled    = $this->fillMcActivityPersons();

        // 9. Remove people that lost all their identities (orphans created by UI deletions)
        $orphansRemoved     = $this->cleanOrphanPeople();

        return [
            'accounts_linked'      => $accountsLinked,
            'identities_linked'    => $identitiesLinked,
            'companies_created'    => $companiesCreated,
            'people_created'       => $peopleCreated,
            'conversations_filled' => $convsFilled,
            'activities_filled'    => $activitiesFilled,
            'tickets_linked'       => $ticketsLinked,
            'persons_filled'       => $personsFilled,
            'mc_persons_filled'    => $mcPersonsFilled,
            'orphans_removed'      => $orphansRemoved,
        ];
    }

    // -------------------------------------------------------------------------
    // Accounts → Companies (match existing)
    // -------------------------------------------------------------------------

    public function resolveAccounts(): int
    {
        $linked = 0;

        Account::whereNull('company_id')
            ->whereNotNull('meta_json')
            ->cursor()
            ->each(function (Account $account) use (&$linked) {
                $meta      = $account->meta_json ?? [];
                $companyId = null;

                // 1. Match by email domain
                if (!empty($meta['email'])) {
                    $parts  = explode('@', $meta['email']);
                    $domain = strtolower(trim($parts[1] ?? ''));
                    if ($domain) {
                        $companyId = CompanyDomain::where('domain', $domain)->value('company_id');
                        if ($companyId) {
                            $this->log[] = "Account #{$account->id}: matched by domain {$domain}";
                        }
                    }
                }

                // 2. Match by company name alias
                if (!$companyId && !empty($meta['company_name'])) {
                    $normalized = strtolower(trim($meta['company_name']));
                    $companyId  = CompanyAlias::where('alias_normalized', $normalized)->value('company_id');
                    if ($companyId) {
                        $this->log[] = "Account #{$account->id}: matched by name '{$meta['company_name']}'";
                    }
                }

                if ($companyId) {
                    $account->update(['company_id' => $companyId]);
                    $linked++;
                }
            });

        return $linked;
    }

    // -------------------------------------------------------------------------
    // Accounts → Companies (auto-create missing)
    // -------------------------------------------------------------------------

    public function autoCreateCompanies(): int
    {
        $created = 0;

        // Collect all unlinked accounts that have a company name
        $unlinked = Account::whereNull('company_id')
            ->whereNotNull('meta_json')
            ->get();

        // Group by normalized company_name to deduplicate
        $byName = [];
        foreach ($unlinked as $account) {
            $name = trim($account->meta_json['company_name'] ?? '');
            if ($name === '') continue;
            $normalized          = strtolower($name);
            $byName[$normalized][] = $account;
        }

        foreach ($byName as $normalized => $accounts) {
            // Check again — a previous iteration in this run may have created the alias
            $companyId = CompanyAlias::where('alias_normalized', $normalized)->value('company_id');

            if (!$companyId) {
                $canonicalName = $accounts[0]->meta_json['company_name'];

                $company = Company::create(['name' => $canonicalName]);

                CompanyAlias::create([
                    'company_id'       => $company->id,
                    'alias'            => $canonicalName,
                    'alias_normalized' => $normalized,
                    'is_primary'       => true,
                ]);

                // Try to add domain from the first email we find in this group
                foreach ($accounts as $acc) {
                    $email = $acc->meta_json['email'] ?? '';
                    if ($email && str_contains($email, '@')) {
                        $domain = strtolower(trim(explode('@', $email)[1] ?? ''));
                        if ($domain && !in_array($domain, self::GENERIC_DOMAINS, true)) {
                            $alreadyUsed = CompanyDomain::where('domain', $domain)->exists();
                            if (!$alreadyUsed) {
                                CompanyDomain::create([
                                    'company_id' => $company->id,
                                    'domain'     => $domain,
                                    'is_primary' => true,
                                ]);
                            }
                        }
                        break;
                    }
                }

                $companyId = $company->id;
                $created++;
                $this->log[] = "Created company '{$canonicalName}' (#{$companyId})";
            }

            // Link all accounts in this group to the company
            foreach ($accounts as $acc) {
                $acc->update(['company_id' => $companyId]);

                // Backfill conversations for this account
                Conversation::whereNull('company_id')
                    ->where('system_type', $acc->system_type)
                    ->where('system_slug', $acc->system_slug)
                    ->update(['company_id' => $companyId]);
            }
        }

        return $created;
    }

    // -------------------------------------------------------------------------
    // Identities → People (match existing)
    // -------------------------------------------------------------------------

    public function resolveIdentities(): int
    {
        $linked = 0;

        Identity::whereNull('person_id')
            ->cursor()
            ->each(function (Identity $identity) use (&$linked) {
                // Strategy 1: same value_normalized in another system already has a person
                $personId = Identity::where('type', $identity->type)
                    ->where('value_normalized', $identity->value_normalized)
                    ->whereNotNull('person_id')
                    ->value('person_id');

                if ($personId) {
                    $identity->update(['person_id' => $personId]);
                    $this->log[] = "Identity #{$identity->id} ({$identity->value}): linked to person #{$personId} via value match";
                    $linked++;
                    return;
                }

                // Strategy 2: email identity display_name matches a person's full name
                if ($identity->type === 'email' && !empty($identity->meta_json['display_name'])) {
                    $name  = trim($identity->meta_json['display_name']);
                    $parts = explode(' ', $name, 2);
                    if (count($parts) === 2) {
                        $person = Person::where('first_name', $parts[0])
                            ->where('last_name', $parts[1])
                            ->first();
                        if ($person) {
                            $identity->update(['person_id' => $person->id]);
                            $this->log[] = "Identity #{$identity->id}: linked to person #{$person->id} via name '{$name}'";
                            $linked++;
                        }
                    }
                }
            });

        return $linked;
    }

    // -------------------------------------------------------------------------
    // Identities → People (auto-create missing)
    // -------------------------------------------------------------------------

    public function autoCreatePeople(): int
    {
        $created = 0;

        // Only use email identities — display_name is a real name (Firstname Lastname from WHMCS etc.)
        // Slack/Discord display names are often usernames, not real names
        $unlinked = Identity::whereNull('person_id')
            ->where('type', 'email')
            ->whereNotNull('meta_json')
            ->get();

        // Group by normalised display_name to deduplicate
        $byName = [];
        foreach ($unlinked as $identity) {
            $name = trim($identity->meta_json['display_name'] ?? '');
            if ($name === '') continue;
            $normalized         = strtolower($name);
            $byName[$normalized][] = $identity;
        }

        foreach ($byName as $normalized => $identities) {
            $rawName   = $identities[0]->meta_json['display_name'];
            $parts     = explode(' ', $rawName, 2);
            $firstName = trim($parts[0]);
            $lastName  = trim($parts[1] ?? '');

            // Check again for an existing person (could have been created in this run)
            $person = Person::where('first_name', $firstName)
                ->where('last_name', $lastName)
                ->first();

            if (!$person) {
                $person = Person::create([
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                ]);
                $created++;
                $this->log[] = "Created person '{$rawName}' (#{$person->id})";
            }

            foreach ($identities as $identity) {
                $identity->update(['person_id' => $person->id]);
            }
        }

        return $created;
    }

    // -------------------------------------------------------------------------
    // Team member activity direction detection
    // -------------------------------------------------------------------------

    public function markTeamMemberActivities(): int
    {
        $teamPeople = Person::where(fn($q) =>
            $q->where('is_our_org', true)
              ->orWhereHas('identities', fn($i) => $i->where('is_team_member', true))
        )->get();

        $teamNames = $teamPeople
            ->map(fn($p) => trim(strtolower($p->full_name)))
            ->filter()
            ->values()
            ->toArray();

        if (empty($teamNames)) {
            return 0;
        }

        $updated = 0;

        \App\Models\Activity::whereRaw("meta_json->>'system_type' = 'metricscube'")
            ->cursor()
            ->each(function (\App\Models\Activity $activity) use ($teamNames, &$updated) {
                $meta     = $activity->meta_json;
                $mcType   = $meta['mc_type'] ?? '';
                $customer = strtolower(trim($meta['customer'] ?? ''));

                // For "Ticket Replied" the replier's name is in description, not customer.
                // For ALL other types, only check customer — descriptions contain product/app names
                // that can false-match team member names (e.g. "ModulesGarden" in service descriptions).
                if ($mcType === 'Ticket Replied') {
                    $description = strtolower(trim($meta['description'] ?? ''));
                    $haystack    = $customer . ' ' . $description;
                } else {
                    $haystack = $customer;
                }

                if (trim($haystack) === '') return;

                $isTeam = false;
                foreach ($teamNames as $name) {
                    if ($name && str_contains($haystack, $name)) {
                        $isTeam = true;
                        break;
                    }
                }

                $currentDirection = $meta['direction'] ?? null;
                $newDirection     = $isTeam ? 'internal' : null;

                if ($currentDirection !== $newDirection) {
                    if ($newDirection) {
                        $meta['direction'] = $newDirection;
                    } else {
                        unset($meta['direction']);
                    }
                    $activity->update(['meta_json' => $meta]);
                    $updated++;
                }
            });

        return $updated;
    }

    // -------------------------------------------------------------------------
    // People → Companies (via email ↔ account matching)
    // -------------------------------------------------------------------------

    public function linkPeopleToCompanies(): int
    {
        $linked = 0;

        // Load all email identities that have a person_id
        $identities = Identity::where('type', 'email')
            ->whereNotNull('person_id')
            ->get();

        // Existing company_person pairs to avoid duplication
        $existing = DB::table('company_person')
            ->pluck('person_id', 'company_id')
            ->toArray();

        $existingPairs = DB::table('company_person')
            ->get(['company_id', 'person_id'])
            ->mapWithKeys(fn($r) => ["{$r->company_id}:{$r->person_id}" => true])
            ->toArray();

        foreach ($identities as $identity) {
            // Find accounts with this email and a company
            $accounts = Account::whereNotNull('company_id')
                ->whereRaw("meta_json->>'email' = ?", [$identity->value])
                ->get();

            foreach ($accounts as $account) {
                $key = "{$account->company_id}:{$identity->person_id}";
                if (isset($existingPairs[$key])) {
                    continue;
                }

                DB::table('company_person')->insert([
                    'company_id' => $account->company_id,
                    'person_id'  => $identity->person_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $existingPairs[$key] = true;
                $linked++;
                $this->log[] = "Linked person #{$identity->person_id} to company #{$account->company_id} via email {$identity->value}";
            }

            // Also match via email domain → company domain
            if (str_contains($identity->value, '@')) {
                $domain    = strtolower(trim(explode('@', $identity->value)[1] ?? ''));
                $companyId = $domain ? CompanyDomain::where('domain', $domain)->value('company_id') : null;
                if ($companyId) {
                    $key = "{$companyId}:{$identity->person_id}";
                    if (!isset($existingPairs[$key]) && !in_array($domain, self::GENERIC_DOMAINS, true)) {
                        DB::table('company_person')->insert([
                            'company_id' => $companyId,
                            'person_id'  => $identity->person_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $existingPairs[$key] = true;
                        $linked++;
                        $this->log[] = "Linked person #{$identity->person_id} to company #{$companyId} via domain {$domain}";
                    }
                }
            }
        }

        return $linked;
    }

    // -------------------------------------------------------------------------
    // MC ticket activities → target_url (backfill)
    // -------------------------------------------------------------------------

    public function linkTicketActivities(): int
    {
        $linked = 0;

        // Flat map: ticket_number (string) => conversation_id
        $ticketMap = \App\Models\Conversation::where('channel_type', 'ticket')
            ->whereNotNull('external_thread_id')
            ->get(['id', 'external_thread_id'])
            ->mapWithKeys(fn($c) => [
                // strip "ticket_" prefix → numeric ticket ID
                (string) substr($c->external_thread_id, 7) => $c->id,
            ]);

        if ($ticketMap->isEmpty()) {
            return 0;
        }

        \App\Models\Activity::whereRaw("meta_json->>'system_type' = 'metricscube'")
            ->whereRaw("meta_json->>'mc_type' IN ('Opened Ticket','Ticket Replied','Closed Ticket')")
            ->whereNull('target_url')
            ->cursor()
            ->each(function (\App\Models\Activity $activity) use ($ticketMap, &$linked) {
                $meta   = $activity->meta_json;
                $mcType = $meta['mc_type'] ?? '';

                // For "Ticket Replied": relation_id is the reply log ID, not ticket ID.
                // Extract the actual ticket ID from the description:
                // e.g. "replied to ticket to #613496 - Subject"
                if ($mcType === 'Ticket Replied') {
                    $desc = $meta['description'] ?? '';
                    if (!preg_match('/ticket to #(\d+)/i', $desc, $m)) {
                        return; // can't determine ticket ID
                    }
                    $ticketId = $m[1];
                } else {
                    // Opened Ticket / Closed Ticket: relation_id IS the ticket ID
                    $ticketId = (string) ($meta['relation_id'] ?? '');
                    if ($ticketId === '') return;
                }

                if (isset($ticketMap[$ticketId])) {
                    $activity->update(['target_url' => '/conversations/' . $ticketMap[$ticketId]]);
                    $linked++;
                    $this->log[] = "Linked activity #{$activity->id} ({$mcType}) → ticket #{$ticketId}";
                }
            });

        return $linked;
    }

    // -------------------------------------------------------------------------
    // Activities → company_id (backfill from linked conversation)
    // -------------------------------------------------------------------------

    public function fillActivityCompanies(): int
    {
        $filled = 0;

        // Process all activities that have a conversation_external_id in meta.
        // Update company_id AND target_url to match the linked conversation.
        \App\Models\Activity::whereRaw("meta_json->>'conversation_external_id' IS NOT NULL")
            ->cursor()
            ->each(function (\App\Models\Activity $activity) use (&$filled) {
                $meta        = $activity->meta_json ?? [];
                $convExtId   = $meta['conversation_external_id'] ?? null;
                $channelType = $meta['channel_type'] ?? null;
                $systemType  = $meta['system_type'] ?? null;
                $systemSlug  = $meta['system_slug'] ?? null;

                if (!$convExtId) return;

                $query = Conversation::where('external_thread_id', $convExtId);
                if ($channelType) $query->where('channel_type', $channelType);
                if ($systemType)  $query->where('system_type', $systemType);
                if ($systemSlug)  $query->where('system_slug', $systemSlug);

                $conv = $query->first();
                if (!$conv) return;

                $changes = [];

                if ($conv->company_id && $conv->company_id !== $activity->company_id) {
                    $changes['company_id'] = $conv->company_id;
                }

                $expectedUrl = '/conversations/' . $conv->id;
                if ($activity->target_url !== $expectedUrl) {
                    $changes['target_url'] = $expectedUrl;
                }

                if (!empty($changes)) {
                    $activity->update($changes);
                    $filled++;
                }
            });

        return $filled;
    }

    // -------------------------------------------------------------------------
    // Conversations → Companies (backfill)
    // -------------------------------------------------------------------------

    public function fillConversationCompanies(): int
    {
        $filled = 0;

        Conversation::whereNull('company_id')
            ->cursor()
            ->each(function (Conversation $conv) use (&$filled) {
                $companyId = null;

                // 1. Via meta client_id (for WHMCS ticket conversations)
                if ($conv->system_type && $conv->system_slug && !empty($conv->meta_json['client_id'])) {
                    $companyId = Account::where('system_type', $conv->system_type)
                        ->where('system_slug', $conv->system_slug)
                        ->where('external_id', (string) $conv->meta_json['client_id'])
                        ->whereNotNull('company_id')
                        ->value('company_id');
                }

                // 2. Via account: match (system_type, system_slug) — only if single company for this system
                if (!$companyId && $conv->system_type && $conv->system_slug) {
                    $companies = Account::where('system_type', $conv->system_type)
                        ->where('system_slug', $conv->system_slug)
                        ->whereNotNull('company_id')
                        ->distinct()
                        ->pluck('company_id');

                    if ($companies->count() === 1) {
                        $companyId = $companies->first();
                    }
                }

                // 3. Via first customer message's sender email → account → company
                if (!$companyId) {
                    $firstMsg = $conv->messages()
                        ->where('direction', 'customer')
                        ->whereNotNull('identity_id')
                        ->oldest('occurred_at')
                        ->first();
                    if ($firstMsg?->identity) {
                        $email     = $firstMsg->identity->value;
                        $companyId = Account::whereNotNull('company_id')
                            ->whereRaw("meta_json->>'email' = ?", [$email])
                            ->value('company_id');
                    }
                }

                // 4. Via message's identity → person → company
                // Prefer customer senders: persons who are not our_org AND have no team identities.
                // This prevents assigning the operator's company to Discord/Slack client channels.
                if (!$companyId) {
                    $customerMsg = $conv->messages()
                        ->whereNotNull('identity_id')
                        ->whereHas('identity.person', function ($q) {
                            $q->where('is_our_org', false)
                              ->whereDoesntHave('identities', fn($i) => $i->where('is_team_member', true));
                        })
                        ->oldest('occurred_at')
                        ->first();
                    $msg = $customerMsg ?? $conv->messages()->whereNotNull('identity_id')->oldest('occurred_at')->first();
                    if ($msg?->identity?->person) {
                        $companyId = $msg->identity->person->companies()->value('companies.id');
                    }
                }

                if ($companyId) {
                    $conv->update(['company_id' => $companyId]);
                    $filled++;
                }
            });

        return $filled;
    }

    // -------------------------------------------------------------------------
    // Activities → person_id (email activities — backfill contact person)
    // -------------------------------------------------------------------------

    /**
     * For email activities without a person_id, resolve the external contact:
     *  - Strategy 1: find a customer-direction message sender who is not a team member
     *  - Strategy 2: parse the 'to' header from message meta (handles sent-only threads)
     *  - Quick path: use contact_email stored in meta (set by ImapNormalizer for new activities)
     */
    public function fillActivityPersons(): int
    {
        $filled = 0;

        \App\Models\Activity::whereNull('person_id')
            ->whereRaw("meta_json->>'channel_type' = 'email'")
            ->whereRaw("meta_json->>'conversation_external_id' IS NOT NULL")
            ->cursor()
            ->each(function (\App\Models\Activity $activity) use (&$filled) {
                $meta       = $activity->meta_json ?? [];
                $convExtId  = $meta['conversation_external_id'] ?? null;
                $systemType = $meta['system_type'] ?? null;
                $systemSlug = $meta['system_slug'] ?? null;

                if (!$convExtId) return;

                // Quick path: contact_email already in meta (future activities from updated normalizer)
                if (!empty($meta['contact_email'])) {
                    $identity = Identity::where('type', 'email')
                        ->where('value_normalized', strtolower(trim($meta['contact_email'])))
                        ->where('is_team_member', false)
                        ->whereNotNull('person_id')
                        ->first();
                    if ($identity) {
                        $activity->update(['person_id' => $identity->person_id]);
                        $filled++;
                        $this->log[] = "Filled activity #{$activity->id} person_id={$identity->person_id} via contact_email meta";
                        return;
                    }
                }

                // Find the conversation
                $query = Conversation::where('external_thread_id', $convExtId)
                    ->where('channel_type', 'email');
                if ($systemType) $query->where('system_type', $systemType);
                if ($systemSlug) $query->where('system_slug', $systemSlug);

                $conv = $query->first();
                if (!$conv) return;

                $personId = null;

                // Strategy 1: customer-direction message sender (non-team identity with person)
                foreach ($conv->messages()->where('direction', 'customer')->whereNotNull('identity_id')->with('identity')->oldest('occurred_at')->get() as $msg) {
                    if ($msg->identity?->person_id && !$msg->identity->is_team_member) {
                        $personId = $msg->identity->person_id;
                        break;
                    }
                }

                // Strategy 2: parse 'to' from message meta (sent-only threads — outgoing emails)
                if (!$personId) {
                    foreach ($conv->messages()->oldest('occurred_at')->get() as $msg) {
                        $to = $msg->meta_json['to'] ?? null;
                        if (!$to) continue;

                        if (preg_match('/<([^>]+@[^>]+)>/', $to, $match)) {
                            $email = strtolower(trim($match[1]));
                        } elseif (filter_var(trim($to), FILTER_VALIDATE_EMAIL)) {
                            $email = strtolower(trim($to));
                        } else {
                            continue;
                        }

                        $identity = Identity::where('type', 'email')
                            ->where('value_normalized', $email)
                            ->where('is_team_member', false)
                            ->whereNotNull('person_id')
                            ->first();
                        if ($identity) {
                            $personId = $identity->person_id;
                            break;
                        }
                    }
                }

                if ($personId) {
                    $activity->update(['person_id' => $personId]);
                    $filled++;
                    $this->log[] = "Filled activity #{$activity->id} person_id={$personId} via email conversation {$convExtId}";
                }
            });

        return $filled;
    }

    // -------------------------------------------------------------------------
    // MetricsCube activities → person_id (by customer display name)
    // -------------------------------------------------------------------------

    /**
     * Match MetricsCube activities to people by parsing the customer field.
     * Customer format: "#<clientId> - <Firstname Lastname>" or just "<Firstname Lastname>".
     */
    public function fillMcActivityPersons(): int
    {
        $filled = 0;

        \App\Models\Activity::whereRaw("meta_json->>'system_type' = 'metricscube'")
            ->whereNull('person_id')
            ->cursor()
            ->each(function (\App\Models\Activity $activity) use (&$filled) {
                $customer = trim($activity->meta_json['customer'] ?? '');
                if (!$customer) return;

                // Strip leading "#N - " prefix if present
                $name = preg_replace('/^#\d+\s*-\s*/', '', $customer);
                $name = trim($name);
                if (!$name) return;

                $parts = explode(' ', $name, 2);
                $firstName = trim($parts[0]);
                $lastName  = isset($parts[1]) ? trim($parts[1]) : '';

                $person = Person::where('first_name', $firstName)
                    ->where('last_name', $lastName !== '' ? $lastName : null)
                    ->first();

                if (!$person && $lastName !== '') {
                    // Try with last_name IS NULL for single-word names
                    $person = Person::where('first_name', $firstName)
                        ->whereNull('last_name')
                        ->first();
                }

                if ($person) {
                    $activity->update(['person_id' => $person->id]);
                    $filled++;
                    $this->log[] = "MC activity #{$activity->id}: linked person #{$person->id} via customer '{$customer}'";
                }
            });

        return $filled;
    }

    // -------------------------------------------------------------------------
    // Orphan people cleanup
    // -------------------------------------------------------------------------

    /**
     * Delete people that have no active identities and no meaningful data.
     * These arise when an identity is deleted via UI but the person record is left behind.
     * Safe to remove: no identities + not our_org + no company links + no activities + no notes.
     */
    public function cleanOrphanPeople(): int
    {
        $removed = 0;

        Person::where('is_our_org', false)
            ->whereDoesntHave('identities')
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('company_person')->whereColumn('person_id', 'people.id'))
            ->whereDoesntHave('activities')
            ->whereDoesntHave('notes')
            ->cursor()
            ->each(function (Person $person) use (&$removed) {
                $person->delete();
                $removed++;
                $this->log[] = "Removed orphan person #{$person->id} '{$person->full_name}' (no identities)";
            });

        return $removed;
    }
}
