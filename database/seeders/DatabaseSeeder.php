<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Activity;
use App\Models\BrandProduct;
use App\Models\Campaign;
use App\Models\CampaignRun;
use App\Models\Company;
use App\Models\CompanyAlias;
use App\Models\CompanyBrandStatus;
use App\Models\CompanyDomain;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\Identity;
use App\Models\Note;
use App\Models\NoteLink;
use App\Models\Person;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $products = collect([
            ['name' => 'ModulesGarden',  'variant' => null,         'slug' => 'modulesgarden'],
            ['name' => 'PanelAlpha',     'variant' => 'Cloud',      'slug' => 'panelalpha-cloud'],
            ['name' => 'PanelAlpha',     'variant' => 'On-Premise', 'slug' => 'panelalpha-onpremise'],
            ['name' => 'EasyDCIM',       'variant' => null,         'slug' => 'easydcim'],
            ['name' => 'WHMCS',          'variant' => 'Integration', 'slug' => 'whmcs-integration'],
        ])->map(fn ($d) => BrandProduct::create($d));

        $companies = [
            $this->makeCompany('Hostify Ltd', 'hostify.io', ['Hostify', 'Hostify Cloud'], ['whmcs' => 'WH-10042', 'metricscube' => 'MC-7731']),
            $this->makeCompany('ServerPro GmbH', 'serverpro.de', ['ServerPro', 'SP GmbH'], ['whmcs' => 'WH-10198']),
            $this->makeCompany('CloudNest Inc', 'cloudnest.com', ['CloudNest', 'CN'], ['metricscube' => 'MC-2219']),
            $this->makeCompany('DataCore SRL', 'datacore.ro', ['DataCore'], ['whmcs' => 'WH-10301']),
        ];

        $stages = ['lead', 'trial', 'active', 'churned'];
        foreach ($companies as $i => $company) {
            foreach ($products->random(rand(2, 4)) as $j => $product) {
                CompanyBrandStatus::firstOrCreate(
                    ['company_id' => $company->id, 'brand_product_id' => $product->id],
                    ['stage' => $stages[($i + $j) % count($stages)], 'evaluation_score' => rand(3, 10), 'evaluation_notes' => 'Auto-seeded.', 'last_evaluated_at' => now()->subDays(rand(5, 120))]
                );
            }
        }

        $people = [
            $this->makePerson('Alice', 'Kowalski', 'alice@hostify.io', 'alice.k'),
            $this->makePerson('Bob', 'Nowak', 'bob@serverpro.de', 'bob.nowak'),
            $this->makePerson('Carol', 'Wisniewska', 'carol@cloudnest.com', 'carol_w'),
            $this->makePerson('Dave', 'Zielinski', 'dave@datacore.ro', 'dave.z'),
            $this->makePerson('Eve', 'Jablonska', 'eve@hostify.io', 'eve_j'),
        ];

        $companies[0]->people()->attach($people[0]->id, ['role' => 'CEO', 'started_at' => '2020-01-01']);
        $companies[0]->people()->attach($people[4]->id, ['role' => 'Tech Lead', 'started_at' => '2021-03-15']);
        $companies[1]->people()->attach($people[1]->id, ['role' => 'Owner', 'started_at' => '2019-06-01']);
        $companies[2]->people()->attach($people[2]->id, ['role' => 'CTO', 'started_at' => '2022-02-10']);
        $companies[3]->people()->attach($people[3]->id, ['role' => 'Director', 'started_at' => '2021-11-01']);

        $channels = ['email', 'slack', 'ticket', 'email'];
        $convs = [];
        foreach ($companies as $i => $company) {
            $channel = $channels[$i];
            $started = now()->subDays(rand(20, 60));
            $conv = Conversation::create([
                'company_id' => $company->id,
                'primary_person_id' => $people[$i]->id,
                'channel_type' => $channel,
                'system_slug' => 'default',
                'external_thread_id' => 'thread-'.strtoupper(substr(md5($company->name), 0, 8)),
                'message_count' => 0,
                'started_at' => $started,
                'last_message_at' => $started->copy()->addDays(rand(1, 10)),
            ]);
            ConversationParticipant::create([
                'conversation_id' => $conv->id,
                'identity_id' => $people[$i]->identities()->first()->id,
                'person_id' => $people[$i]->id,
                'role' => 'sender',
                'display_name' => $people[$i]->full_name,
            ]);
            $convs[] = ['conv' => $conv, 'person' => $people[$i], 'channel' => $channel, 'started' => $started];
        }

        // Seed Slack conversation (companies[1] = ServerPro)
        $slackConv = $convs[1]['conv'];
        $slackPerson = $convs[1]['person'];
        $slackMsgs = [
            ['author' => $slackPerson->full_name, 'dir' => 'customer', 'body' => 'Hey, we just upgraded our plan. Can you confirm the new limits?', 'offset' => 0],
            ['author' => 'Support', 'dir' => 'internal', 'body' => 'Hi Bob! Yes, confirmed — you now have 200 servers and 10TB storage. Let me know if you need anything else.', 'offset' => 5],
            ['author' => $slackPerson->full_name, 'dir' => 'customer', 'body' => 'Perfect, thanks! One more thing — the WHMCS plugin keeps throwing an error on import.', 'offset' => 12, 'thread_count' => 2],
            ['author' => 'Support', 'dir' => 'internal', 'body' => 'Can you paste the error message?', 'offset' => 15, 'thread_key' => 3],
            ['author' => $slackPerson->full_name, 'dir' => 'customer', 'body' => 'ERROR: Invalid product ID mapping. Line 42.', 'offset' => 20, 'thread_key' => 3],
            ['author' => 'Support', 'dir' => 'internal', 'body' => 'Got it! That\'s a known issue with v2.4 — fix is in the next release (ETA Thursday). Workaround: use CSV import instead.', 'offset' => 25],
            ['author' => $slackPerson->full_name, 'dir' => 'customer', 'body' => 'Thanks, CSV works! 🙌', 'offset' => 30],
        ];
        $threadKeys = [];
        $msgIds = [];
        foreach ($slackMsgs as $idx => $m) {
            $threadKey = null;
            if (isset($m['thread_key'])) {
                $parentIdx = $m['thread_key'] - 1;
                $threadKey = $msgIds[$parentIdx] ?? null;
            }
            $msg = ConversationMessage::create([
                'conversation_id' => $slackConv->id,
                'author_name' => $m['author'],
                'direction' => $m['dir'],
                'body_text' => $m['body'],
                'thread_key' => $threadKey ? (string) $threadKey : null,
                'thread_count' => $m['thread_count'] ?? 0,
                'occurred_at' => $convs[1]['started']->copy()->addMinutes($m['offset']),
            ]);
            $msgIds[$idx] = $msg->id;
        }
        $slackConv->update(['message_count' => count($slackMsgs)]);

        // Seed Email conversation (companies[0] = Hostify)
        $emailConv = $convs[0]['conv'];
        $emailPerson = $convs[0]['person'];
        $emailMsgs = [
            [
                'author' => $emailPerson->full_name, 'dir' => 'customer',
                'body' => "Hi,\n\nWe're evaluating PanelAlpha Cloud for our hosting infrastructure. Could you send over a pricing sheet for 500 accounts?\n\nBest,\nAlice",
                'offset' => 0,
                'meta' => ['subject' => 'PanelAlpha Cloud Pricing Inquiry'],
            ],
            [
                'author' => 'Sales', 'dir' => 'internal',
                'body' => "Hi Alice,\n\nThank you for your interest! I've attached our pricing sheet for the 500-account tier. Key highlights:\n• €299/mo for up to 500 accounts\n• Free migration assistance\n• 99.9% SLA\n\nHappy to schedule a demo — just let me know!\n\nBest,\nSales Team",
                'offset' => 60 * 4,
                'attachments' => [['name' => 'PanelAlpha_Pricing_2026.pdf', 'url' => '#']],
                'meta' => ['subject' => 'Re: PanelAlpha Cloud Pricing Inquiry'],
            ],
            [
                'author' => $emailPerson->full_name, 'dir' => 'customer',
                'body' => "Thanks for the quick response! The pricing looks good. Let's schedule a demo for next week.",
                'offset' => 60 * 24,
                'meta' => ['subject' => 'Re: PanelAlpha Cloud Pricing Inquiry'],
            ],
        ];
        foreach ($emailMsgs as $m) {
            ConversationMessage::create([
                'conversation_id' => $emailConv->id,
                'author_name' => $m['author'],
                'direction' => $m['dir'],
                'body_text' => $m['body'],
                'attachments_json' => $m['attachments'] ?? null,
                'meta_json' => $m['meta'] ?? null,
                'occurred_at' => $convs[0]['started']->copy()->addMinutes($m['offset']),
            ]);
        }
        $emailConv->update(['message_count' => count($emailMsgs)]);

        // Seed Ticket conversation (companies[2] = CloudNest)
        $ticketConv = $convs[2]['conv'];
        $ticketPerson = $convs[2]['person'];
        $ticketMsgs = [
            [
                'author' => $ticketPerson->full_name, 'dir' => 'customer',
                'body' => "We're getting 504 timeouts on the API endpoint /v2/servers/list. Started about 2 hours ago. Affects production.",
                'offset' => 0,
                'meta' => ['subject' => 'API 504 Timeout — URGENT', 'ticket_status' => 'open', 'priority' => 'high'],
            ],
            [
                'author' => 'System', 'dir' => 'system',
                'body' => 'Ticket assigned to Support Team · Priority: High',
                'is_system' => true,
                'offset' => 2,
            ],
            [
                'author' => 'Support', 'dir' => 'internal',
                'body' => "Hi Carol,\n\nWe're investigating this now. Our monitoring also picked up elevated response times on that endpoint starting at 14:30 UTC. We'll keep you updated every 30 minutes.",
                'offset' => 15,
            ],
            [
                'author' => 'Support', 'dir' => 'internal',
                'body' => 'Update: we identified a database connection pool exhaustion. Fix is being deployed now. ETA: 15 minutes.',
                'offset' => 45,
            ],
            [
                'author' => 'System', 'dir' => 'system',
                'body' => 'Status changed: open → resolved',
                'is_system' => true,
                'offset' => 65,
            ],
            [
                'author' => 'Support', 'dir' => 'internal',
                'body' => 'The issue has been resolved. API response times are back to normal (<200ms). Please confirm everything is working on your end.',
                'offset' => 65,
                'meta' => ['ticket_status' => 'resolved'],
            ],
            [
                'author' => $ticketPerson->full_name, 'dir' => 'customer',
                'body' => 'Confirmed — API is working again. Thanks for the fast response!',
                'offset' => 80,
            ],
        ];
        foreach ($ticketMsgs as $m) {
            ConversationMessage::create([
                'conversation_id' => $ticketConv->id,
                'author_name' => $m['author'],
                'direction' => $m['dir'],
                'body_text' => $m['body'],
                'is_system_message' => $m['is_system'] ?? false,
                'meta_json' => $m['meta'] ?? null,
                'occurred_at' => $convs[2]['started']->copy()->addMinutes($m['offset']),
            ]);
        }
        $ticketConv->update(['message_count' => count($ticketMsgs)]);

        $activitySets = [
            ['type' => 'payment',      'meta' => ['description' => 'Invoice #INV-2026-0312 paid', 'amount' => 299, 'currency' => 'EUR']],
            ['type' => 'renewal',      'meta' => ['description' => 'Subscription renewed for 12 months', 'plan' => 'PanelAlpha Cloud 500']],
            ['type' => 'ticket',       'meta' => ['description' => 'API timeout reported', 'priority' => 'high']],
            ['type' => 'note',         'meta' => ['description' => 'Follow-up call scheduled']],
            ['type' => 'conversation', 'meta' => ['description' => 'Demo meeting completed via Slack']],
            ['type' => 'status_change', 'meta' => ['description' => 'Stage changed: trial → active']],
            ['type' => 'campaign_run', 'meta' => ['description' => 'Q1 follow-up campaign delivered']],
        ];
        foreach ($companies as $i => $company) {
            foreach ($activitySets as $j => $act) {
                Activity::create([
                    'company_id' => $company->id,
                    'person_id' => ($j < count($people)) ? $people[($i + $j) % count($people)]->id : null,
                    'type' => $act['type'],
                    'meta_json' => $act['meta'],
                    'occurred_at' => now()->subDays(rand(1, 180)),
                ]);
            }
        }

        $noteContents = [
            'Customer requested a demo of PanelAlpha Cloud.',
            'Renewal negotiation in progress — 15% discount offered.',
            'Technical issues with WHMCS integration reported.',
            'Decision maker changed — need to re-qualify.',
        ];
        foreach ($companies as $i => $company) {
            $note = Note::create(['content' => $noteContents[$i % 4], 'source' => 'manual']);
            NoteLink::create(['note_id' => $note->id, 'linkable_type' => Company::class, 'linkable_id' => $company->id]);
        }

        $personNote = Note::create(['content' => 'Key contact at Hostify. Prefers Slack.', 'source' => 'manual']);
        NoteLink::create(['note_id' => $personNote->id, 'linkable_type' => Person::class, 'linkable_id' => $people[0]->id]);

        $campaign = Campaign::create(['name' => 'Trial Users — Q1 Follow-up', 'prompt' => 'List all companies in trial stage with last_evaluated_at older than 30 days.']);
        CampaignRun::create(['campaign_id' => $campaign->id, 'status' => 'completed', 'result_summary' => '4 companies matched.', 'generated_at' => now()->subHours(2)]);
        CampaignRun::create(['campaign_id' => $campaign->id, 'status' => 'queued']);
    }

    private function makeCompany(string $name, string $domain, array $aliases, array $accounts): Company
    {
        $company = Company::create(['name' => $name, 'primary_domain' => $domain, 'timezone' => 'Europe/Warsaw']);
        CompanyDomain::create(['company_id' => $company->id, 'domain' => $domain, 'is_primary' => true]);
        foreach ($aliases as $alias) {
            CompanyAlias::create(['company_id' => $company->id, 'alias' => $alias]);
        }
        foreach ($accounts as $type => $extId) {
            Account::create(['company_id' => $company->id, 'system_type' => $type, 'system_slug' => 'default', 'external_id' => $extId]);
        }

        return $company;
    }

    private function makePerson(string $first, string $last, string $email, string $slackId): Person
    {
        $person = Person::create(['first_name' => $first, 'last_name' => $last]);
        Identity::create(['person_id' => $person->id, 'system_slug' => 'default', 'type' => 'email', 'value' => $email]);
        Identity::create(['person_id' => $person->id, 'system_slug' => 'default', 'type' => 'slack_id', 'value' => $slackId]);

        return $person;
    }
}
