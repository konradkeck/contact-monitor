<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Identity;
use App\Models\Note;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->input('tab', 'assigned');
        $search = trim($request->input('q', ''));
        $companyId = $request->input('company_id');
        $channelType = $request->input('channel_type');
        $systemSlug = $request->input('system_slug');
        $personId = $request->input('person_id');
        // Multi-system filter: each element is "channel_type|system_slug"
        $systems = array_filter((array) $request->input('systems', []));
        $f_date_from = $request->input('f_date_from', '');
        $f_date_to   = $request->input('f_date_to', '');

        $convSystems = DB::table('conversations')
            ->whereNotNull('channel_type')
            ->select('channel_type', 'system_slug', 'system_type')
            ->distinct()->get()->sortBy('channel_type')->values();

        $query = Conversation::with(['company'])->orderByDesc('last_message_at');

        if (! empty($systems)) {
            $pairs = array_map(fn ($s) => explode('|', $s, 2), $systems);
            $query->where(function ($q) use ($pairs) {
                foreach ($pairs as $pair) {
                    if (count($pair) === 2) {
                        $q->orWhere(fn ($q2) => $q2
                            ->where('channel_type', $pair[0])
                            ->where('system_slug', $pair[1]));
                    }
                }
            });
        } elseif ($channelType) {
            $query->where('channel_type', $channelType);
            if ($systemSlug) {
                $query->where('system_slug', $systemSlug);
            }
        }

        $activeSystems = $systems;

        if ($personId) {
            $query->where(function ($q) use ($personId) {
                $q->whereExists(function ($sub) use ($personId) {
                    $sub->select(DB::raw(1))
                        ->from('conversation_messages as cm')
                        ->join('identities as i', 'i.id', '=', 'cm.identity_id')
                        ->whereColumn('cm.conversation_id', 'conversations.id')
                        ->where('i.person_id', $personId);
                })->orWhereExists(function ($sub) use ($personId) {
                    $sub->select(DB::raw(1))
                        ->from('conversation_participants as cp')
                        ->whereColumn('cp.conversation_id', 'conversations.id')
                        ->where('cp.person_id', $personId);
                });
            });
        }

        // Company filter (overrides tab logic)
        if ($companyId) {
            $query->where('company_id', $companyId);
        } elseif ($personId) {
            // person filter spans all companies — no tab restriction
        } elseif ($tab === 'filtered') {
            $filterDomains = \App\Models\SystemSetting::get('filter_domains', []);
            $filterEmails = \App\Models\SystemSetting::get('filter_emails', []);
            $filterSubjects = \App\Models\SystemSetting::get('filter_subjects', []);

            $query->where(function ($q) use ($filterDomains, $filterEmails, $filterSubjects) {
                $q->where('is_archived', true);
                $this->applySystemFilters($q, $filterDomains, $filterEmails, $filterSubjects, 'include');
            });
        } else {
            // unassigned / assigned tabs exclude filtered conversations
            $query->where(fn ($q) => $q->where('is_archived', false)->orWhereNull('is_archived'));
            if ($tab === 'assigned') {
                $query->whereNotNull('company_id');
            } else {
                $query->whereNull('company_id');
            }

            // Dynamically exclude conversations matching domain/email/subject filter rules
            $filterDomains = \App\Models\SystemSetting::get('filter_domains', []);
            $filterEmails = \App\Models\SystemSetting::get('filter_emails', []);
            $filterSubjects = \App\Models\SystemSetting::get('filter_subjects', []);

            $this->applySystemFilters($query, $filterDomains, $filterEmails, $filterSubjects, 'exclude');
        }

        if ($search !== '') {
            $query->where(function ($q2) use ($search) {
                $q2->where('subject', 'ilike', "%{$search}%")
                    ->orWhereHas('company', fn ($c) => $c->where('name', 'ilike', "%{$search}%"));
            });
        }

        if (!empty($f_date_from)) {
            $query->whereDate('last_message_at', '>=', $f_date_from);
        }
        if (!empty($f_date_to)) {
            $query->whereDate('last_message_at', '<=', $f_date_to);
        }

        $conversations = $query->paginate(50)->withQueryString();
        $convIds = $conversations->pluck('id');

        $active = fn ($q) => $q->where(fn ($q) => $q->where('is_archived', false)->orWhereNull('is_archived'));
        $filterDomains = \App\Models\SystemSetting::get('filter_domains', []);
        $filterEmails = \App\Models\SystemSetting::get('filter_emails', []);
        $filterSubjectsCount = \App\Models\SystemSetting::get('filter_subjects', []);
        $filteredQuery = Conversation::where(function ($q) use ($filterDomains, $filterEmails, $filterSubjectsCount) {
            $q->where('is_archived', true);
            $this->applySystemFilters($q, $filterDomains, $filterEmails, $filterSubjectsCount, 'include');
        });
        $tabCounts = [
            'unassigned' => Conversation::whereNull('company_id')->tap($active)->count(),
            'assigned' => Conversation::whereNotNull('company_id')->tap($active)->count(),
            'filtered' => (clone $filteredQuery)->count(),
        ];

        // Single query: distinct (conversation_id, identity_id) per direction
        $msgIdentities = DB::table('conversation_messages')
            ->whereIn('conversation_id', $convIds)
            ->whereNotNull('identity_id')
            ->where('direction', '!=', 'system')
            ->select('conversation_id', 'identity_id', 'direction', 'author_name')
            ->get()
            ->unique(fn ($r) => $r->conversation_id.'|'.$r->identity_id)
            ->groupBy('conversation_id');

        // Load all identities with person in one query
        $allIdentityIds = $msgIdentities->flatten()->pluck('identity_id')->unique();
        $identities = Identity::with('person')
            ->whereIn('id', $allIdentityIds)
            ->get()
            ->keyBy('id');

        // Build per-conversation participant lists
        $convParticipants = [];
        foreach ($msgIdentities as $convId => $rows) {
            $customer = [];
            $team = [];
            foreach ($rows as $row) {
                $identity = $identities->get($row->identity_id);

                // Resolve gravatar email: email identity → value; others → email_hint
                $gravatarEmail = null;
                if ($identity) {
                    $gravatarEmail = $identity->type === 'email'
                        ? $identity->value
                        : ($identity->meta_json['email_hint'] ?? null);
                }

                // Avatar URL (Discord CDN hash → URL; Slack stores direct URL)
                $avatarUrl = null;
                if ($identity) {
                    if (in_array($identity->type, ['discord_user', 'discord_id'])) {
                        if (! empty($identity->meta_json['avatar'])) {
                            $avatarUrl = 'https://cdn.discordapp.com/avatars/'
                                .$identity->value_normalized.'/'
                                .$identity->meta_json['avatar'].'.webp?size=56';
                        } else {
                            $idx = (int) substr($identity->value_normalized ?? '0', -1) % 5;
                            $avatarUrl = 'https://cdn.discordapp.com/embed/avatars/'.$idx.'.png';
                        }
                    } elseif ($identity->type === 'slack_user' && ! empty($identity->meta_json['avatar'])) {
                        $avatarUrl = $identity->meta_json['avatar'];
                    }
                }

                $person = $identity?->person;
                $displayName = $identity?->meta_json['display_name'] ?? $row->author_name;
                $gravatarHash = $gravatarEmail ? md5(strtolower(trim($gravatarEmail))) : null;

                $entry = [
                    'identity' => $identity,
                    'author_name' => $row->author_name,
                    'display_name' => $displayName,
                    'gravatar_hash' => $gravatarHash,
                    'avatar_url' => $avatarUrl,
                    // Pre-computed display fields
                    '_person' => $person,
                    '_label' => $person ? $person->initials() : mb_strtoupper(mb_substr($displayName ?? '?', 0, 2)),
                    '_title' => $person ? trim($person->first_name . ' ' . $person->last_name) : ($displayName ?? ''),
                    '_imgSrc' => $avatarUrl ?? ($gravatarHash ? 'https://www.gravatar.com/avatar/' . $gravatarHash . '?d=identicon&s=56' : null),
                ];
                if ($identity?->is_team_member || $row->direction === 'internal') {
                    $team[] = $entry;
                } else {
                    $customer[] = $entry;
                }
            }
            $convParticipants[$convId] = compact('customer', 'team');
        }

        $q = $search;
        $activeConvFilterCount = (($f_date_from !== '' || $f_date_to !== '') ? 1 : 0) + (count($systems) > 0 ? 1 : 0);

        return view('conversations.index', compact(
            'conversations', 'convParticipants', 'tab', 'tabCounts',
            'companyId', 'channelType', 'systemSlug', 'personId', 'q',
            'convSystems', 'activeSystems',
            'f_date_from', 'f_date_to', 'activeConvFilterCount'
        ));
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $conversation->load([
            'company',
            'primaryPerson',
            'participants.identity',
            'participants.person',
            'messages.attachments',
            'messages.identity',
        ]);

        // Group messages: thread_key → replies
        $threads = $conversation->messages
            ->where('thread_key', null)
            ->keyBy('id');
        $replies = $conversation->messages
            ->where('thread_key', '!=', null)
            ->groupBy('thread_key');

        $notes = Note::whereHas('links', fn ($q) => $q->where('linkable_type', Conversation::class)->where('linkable_id', $conversation->id)
        )->orderByDesc('created_at')->get();

        $allIdentities = Identity::with('person')->orderBy('value')->get()
            ->reject(fn ($id) => $conversation->participants->pluck('identity_id')->contains($id->id));

        // Discord: build user_id → display_name map for resolving <@userId> mentions
        $discordMentionMap = [];
        if ($conversation->channel_type === 'discord') {
            $discordMentionMap = DB::table('identities')
                ->where('type', 'discord_user')
                ->where('system_slug', $conversation->system_slug)
                ->get(['value_normalized', 'meta_json'])
                ->mapWithKeys(fn ($row) => [
                    $row->value_normalized => json_decode($row->meta_json ?? '{}', true)['display_name'] ?? $row->value_normalized,
                ])
                ->all();
        }

        // Slack: build value_normalized → display_name map for resolving <@UXXXXXX> mentions
        $slackMentionMap = [];
        if ($conversation->channel_type === 'slack') {
            $slackMentionMap = DB::table('identities')
                ->where('type', 'slack_user')
                ->where('system_slug', $conversation->system_slug)
                ->get(['value_normalized', 'meta_json'])
                ->mapWithKeys(fn ($row) => [
                    $row->value_normalized => json_decode($row->meta_json ?? '{}', true)['display_name'] ?? $row->value_normalized,
                ])
                ->all();
        }

        $backLink = $this->resolveBackLink($request);

        $showSysLogo = $conversation->system_type
            && get_class(\App\Integrations\IntegrationRegistry::get($conversation->system_type))
               !== get_class(\App\Integrations\IntegrationRegistry::get($conversation->channel_type));

        $debugInfo = array_filter([
            'conversation_id'    => $conversation->id,
            'channel_type'       => $conversation->channel_type,
            'system_type'        => $conversation->system_type,
            'system_slug'        => $conversation->system_slug,
            'external_thread_id' => $conversation->external_thread_id,
            'company_id'         => $conversation->company_id,
            'message_count'      => $conversation->message_count,
            'started_at'         => $conversation->started_at?->toIso8601String(),
            'last_message_at'    => $conversation->last_message_at?->toIso8601String(),
        ], fn ($v) => $v !== null && $v !== '');

        return view('conversations.show', compact(
            'conversation', 'notes', 'allIdentities', 'threads', 'replies', 'backLink',
            'discordMentionMap', 'slackMentionMap', 'showSysLogo', 'debugInfo'
        ));
    }

    public function modal(Request $request, Conversation $conversation)
    {
        $date = $request->get('date'); // optional YYYY-MM-DD for Discord/Slack daily aggregates

        $isChat = in_array($conversation->channel_type, ['slack', 'discord']);
        $preview = $request->boolean('preview');

        $msgQuery = $conversation->messages()
            ->with(['identity', 'attachments'])
            ->orderBy('occurred_at');

        if ($preview) {
            // Index page quick-view: last 3 for email/ticket, last 20 top-level for chat
            if ($isChat) {
                $topLevel = $conversation->messages()
                    ->with(['identity', 'attachments'])
                    ->whereNull('thread_key')
                    ->orderByDesc('occurred_at')
                    ->limit(20)
                    ->get();
                $replyRows = $topLevel->isNotEmpty()
                    ? $conversation->messages()
                        ->with(['identity', 'attachments'])
                        ->whereNotNull('thread_key')
                        ->whereIn('thread_key', $topLevel->pluck('id'))
                        ->orderBy('occurred_at')
                        ->get()
                    : collect();
                $messages = $topLevel->merge($replyRows);
            } else {
                $messages = $conversation->messages()
                    ->with(['identity', 'attachments'])
                    ->orderByDesc('occurred_at')
                    ->limit(3)
                    ->get();
            }
        } elseif ($date) {
            $messages = $msgQuery->whereDate('occurred_at', $date)->limit(10)->get();
        } else {
            $messages = $msgQuery->limit(1)->get();
        }

        // Group thread replies keyed by parent message id (for Slack/Discord threading)
        $replies = $messages
            ->whereNotNull('thread_key')
            ->groupBy('thread_key');

        $discordMentionMap = [];
        if ($conversation->channel_type === 'discord') {
            $discordMentionMap = DB::table('identities')
                ->where('type', 'discord_user')
                ->where('system_slug', $conversation->system_slug)
                ->get(['value_normalized', 'meta_json'])
                ->mapWithKeys(fn ($row) => [
                    $row->value_normalized => json_decode($row->meta_json ?? '{}', true)['display_name'] ?? $row->value_normalized,
                ])
                ->all();
        }

        $slackMentionMap = [];
        if ($conversation->channel_type === 'slack') {
            $slackMentionMap = DB::table('identities')
                ->where('type', 'slack_user')
                ->where('system_slug', $conversation->system_slug)
                ->get(['value_normalized', 'meta_json'])
                ->mapWithKeys(fn ($row) => [
                    $row->value_normalized => json_decode($row->meta_json ?? '{}', true)['display_name'] ?? $row->value_normalized,
                ])
                ->all();
        }

        $isEmail  = $conversation->channel_type === 'email';
        $isTicket = $conversation->channel_type === 'ticket';

        $emailFrom = $emailTo = $emailCc = $fromGravatarHash = null;
        $firstMsg = $messages->first();
        if ($isEmail && $firstMsg) {
            $msgMeta   = $firstMsg->meta_json ?? [];
            $fromEmail = $firstMsg->identity?->value ?? null;
            $fromName  = $firstMsg->author_name;
            $emailFrom = $fromName ? "{$fromName} <{$fromEmail}>" : $fromEmail;
            $emailTo   = $msgMeta['to'] ?? null;
            $emailCc   = $msgMeta['cc'] ?? null;
            if ($fromEmail) {
                $fromGravatarHash = md5(strtolower(trim($fromEmail)));
            }
        }

        return view('conversations.modal', compact(
            'conversation', 'messages', 'replies', 'date', 'discordMentionMap', 'slackMentionMap',
            'isEmail', 'isTicket', 'isChat', 'preview', 'emailFrom', 'emailTo', 'emailCc', 'fromGravatarHash'
        ));
    }

    /**
     * Return a filter-rule modal partial for the given conversation IDs.
     * Collects suggested rule values from participants.
     */
    public function filterModal(Request $request)
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        $conversations = Conversation::with(['company', 'participants.identity.person'])
            ->whereIn('id', $ids)
            ->get();

        // Collect suggestions per rule type
        $emails = collect();
        $domains = collect();
        $contacts = collect(); // [person_id => full_name]
        $subjects = collect();

        foreach ($conversations as $conv) {
            if ($conv->subject) {
                $subjects->push($conv->subject);
            }
            foreach ($conv->participants as $p) {
                $identity = $p->identity;
                if (! $identity) {
                    continue;
                }
                if ($identity->type === 'email') {
                    $emails->push($identity->value);
                    $domain = substr(strrchr($identity->value, '@'), 1);
                    if ($domain) {
                        $domains->push($domain);
                    }
                }
                if ($identity->person) {
                    $contacts->put($identity->person->id, $identity->person->full_name);
                }
            }
        }

        $emails = $emails->unique()->values();
        $domains = $domains->unique()->values();
        $contacts = $contacts->unique();
        $subjects = $subjects->unique()->values();

        $tabs = ['none' => 'No rule', 'domain' => 'Domain', 'email' => 'Email'];
        if ($contacts->isNotEmpty()) {
            $tabs['contact'] = 'Contact';
        }
        if ($subjects->isNotEmpty()) {
            $tabs['subject'] = 'Subject';
        }

        return view('conversations.filter-modal', compact(
            'conversations', 'ids', 'emails', 'domains', 'contacts', 'subjects', 'tabs'
        ));
    }

    /**
     * Archive conversations and optionally add a filter rule.
     */
    public function archiveWithRule(Request $request): RedirectResponse
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        $ruleType = $request->input('rule_type', 'none');

        // Support both rule_values[] (multi) and legacy rule_value (single)
        $rawValues = $request->input('rule_values', []);
        if (empty($rawValues)) {
            $single = trim($request->input('rule_value', ''));
            $rawValues = $single !== '' ? [$single] : [];
        }
        $ruleValues = array_filter(array_map('trim', (array) $rawValues));

        if (! empty($ids)) {
            Conversation::whereIn('id', $ids)->update(['is_archived' => true]);
        }

        if ($ruleType !== 'none' && ! empty($ruleValues)) {
            foreach ($ruleValues as $val) {
                match ($ruleType) {
                    'domain' => $this->addFilterDomain($val),
                    'email' => $this->addFilterEmail($val),
                    'contact' => $this->addFilterContact((int) $val),
                    'subject' => $this->addFilterSubject($val),
                    default => null,
                };
            }
        }

        $n = count($ids);
        $msg = $n.' conversation(s) filtered';
        if ($ruleType !== 'none' && ! empty($ruleValues)) {
            $msg .= ' + filter rule added (' . $ruleType . ': ' . count($ruleValues) . ' value(s))';
        }

        return back()->with('success', $msg.'.');
    }

    private function addFilterDomain(string $domain): void
    {
        $domains = \App\Models\SystemSetting::get('filter_domains', []);
        $domain = strtolower(trim($domain));
        if (! in_array($domain, $domains, true)) {
            $domains[] = $domain;
            \App\Models\SystemSetting::set('filter_domains', $domains);
        }
    }

    private function addFilterEmail(string $email): void
    {
        $emails = \App\Models\SystemSetting::get('filter_emails', []);
        $email = strtolower(trim($email));
        if (! in_array($email, $emails, true)) {
            $emails[] = $email;
            \App\Models\SystemSetting::set('filter_emails', $emails);
        }
    }

    private function addFilterContact(int $personId): void
    {
        if ($personId) {
            DB::table('filter_contacts')->insertOrIgnore([
                'person_id' => $personId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function addFilterSubject(string $subject): void
    {
        $subjects = \App\Models\SystemSetting::get('filter_subjects', []);
        $subject = trim($subject);
        if ($subject !== '' && ! in_array($subject, $subjects, true)) {
            $subjects[] = $subject;
            \App\Models\SystemSetting::set('filter_subjects', $subjects);
        }
    }

    /**
     * Apply domain/email/subject filter rules to a query.
     *
     * Mode 'include': add orWhere conditions (for the "filtered" tab — show matching conversations).
     * Mode 'exclude': add whereNotExists/whereRaw NOT LIKE (for assigned/unassigned tabs — hide matching conversations).
     */
    private function applySystemFilters(Builder $query, array $filterDomains, array $filterEmails, array $filterSubjects, string $mode): Builder
    {
        if ($mode === 'include') {
            if (! empty($filterDomains) || ! empty($filterEmails)) {
                $query->orWhereExists(function ($sub) use ($filterDomains, $filterEmails) {
                    $sub->select(DB::raw(1))
                        ->from('conversation_messages as cm_f')
                        ->join('identities as i_f', 'i_f.id', '=', 'cm_f.identity_id')
                        ->whereColumn('cm_f.conversation_id', 'conversations.id')
                        ->where('i_f.type', 'email')
                        ->where(function ($q2) use ($filterDomains, $filterEmails) {
                            foreach ($filterDomains as $domain) {
                                $q2->orWhereRaw('LOWER(i_f.value) LIKE ?', ['%@'.strtolower($domain)]);
                            }
                            if (! empty($filterEmails)) {
                                $q2->orWhereIn(DB::raw('LOWER(i_f.value)'), array_map('strtolower', $filterEmails));
                            }
                        });
                });
            }
            foreach ($filterSubjects as $subject) {
                $query->orWhereRaw('LOWER(subject) LIKE ?', ['%'.strtolower($subject).'%']);
            }
        } else {
            if (! empty($filterDomains) || ! empty($filterEmails)) {
                $query->whereNotExists(function ($sub) use ($filterDomains, $filterEmails) {
                    $sub->select(DB::raw(1))
                        ->from('conversation_messages as cm_f')
                        ->join('identities as i_f', 'i_f.id', '=', 'cm_f.identity_id')
                        ->whereColumn('cm_f.conversation_id', 'conversations.id')
                        ->where('i_f.type', 'email')
                        ->where(function ($q2) use ($filterDomains, $filterEmails) {
                            foreach ($filterDomains as $domain) {
                                $q2->orWhereRaw('LOWER(i_f.value) LIKE ?', ['%@'.strtolower($domain)]);
                            }
                            if (! empty($filterEmails)) {
                                $q2->orWhereIn(DB::raw('LOWER(i_f.value)'), array_map('strtolower', $filterEmails));
                            }
                        });
                });
            }
            foreach ($filterSubjects as $subject) {
                $query->whereRaw('LOWER(subject) NOT LIKE ?', ['%'.strtolower($subject).'%']);
            }
        }

        return $query;
    }

    public function bulkArchive(Request $request): RedirectResponse
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        if (! empty($ids)) {
            Conversation::whereIn('id', $ids)->update(['is_archived' => true]);
        }

        return back()->with('success', count($ids).' conversation(s) filtered.');
    }

    public function storeParticipant(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'identity_id' => 'required|exists:identities,id',
            'role' => 'nullable|string|max:100',
            'display_name' => 'nullable|string|max:255',
        ]);

        $identity = Identity::find($data['identity_id']);
        $data['person_id'] = $identity->person_id;

        $conversation->participants()->create($data);

        return back()->with('success', 'Participant added.');
    }

    public function destroyParticipant(Conversation $conversation, ConversationParticipant $participant): RedirectResponse
    {
        $participant->delete();

        return back()->with('success', 'Participant removed.');
    }
}
