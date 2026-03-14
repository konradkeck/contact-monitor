<?php

namespace Tests\Feature;

use App\DataRelations\AutoResolver;
use App\Models\Identity;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoResolverBotsTest extends TestCase
{
    use RefreshDatabase;

    private function makeBot(string $type, string $value, string $displayName, ?int $personId = null): Identity
    {
        return Identity::create([
            'type'             => $type,
            'value'            => $value,
            'value_normalized' => strtolower($value),
            'system_type'      => $type === 'slack_user' ? 'slack' : 'discord',
            'system_slug'      => 'test',
            'is_bot'           => true,
            'is_team_member'   => false,
            'person_id'        => $personId,
            'meta_json'        => ['display_name' => $displayName],
        ]);
    }

    // ── autoLinkBots: creates person and links identity ──────────────────────

    public function test_slack_bot_gets_person_created_and_linked(): void
    {
        $identity = $this->makeBot('slack_user', 'U_BOT1', 'Onboarding Assistant');

        $resolver = new AutoResolver();
        $count    = $resolver->autoLinkBots();

        $this->assertEquals(1, $count);
        $identity->refresh();
        $this->assertNotNull($identity->person_id);
        $this->assertEquals('Onboarding', $identity->person->first_name);
        $this->assertEquals('Assistant', $identity->person->last_name);
    }

    public function test_discord_bot_gets_person_created_and_linked(): void
    {
        $identity = $this->makeBot('discord_user', 'D_BOT1', 'Onboarding Assistant');

        $resolver = new AutoResolver();
        $resolver->autoLinkBots();

        $identity->refresh();
        $this->assertNotNull($identity->person_id);
        $this->assertEquals('Onboarding', $identity->person->first_name);
    }

    // ── autoLinkBots: person is marked is_our_org ─────────────────────────────

    public function test_bot_person_is_marked_our_org(): void
    {
        $this->makeBot('slack_user', 'U_BOT2', 'Deploy Bot');

        (new AutoResolver())->autoLinkBots();

        $person = Person::where('first_name', 'Deploy')->where('last_name', 'Bot')->first();
        $this->assertNotNull($person);
        $this->assertTrue((bool) $person->is_our_org);
    }

    // ── autoLinkBots: identity is marked is_team_member ───────────────────────

    public function test_bot_identity_is_marked_team_member(): void
    {
        $identity = $this->makeBot('slack_user', 'U_BOT3', 'Notify Bot');

        (new AutoResolver())->autoLinkBots();

        $identity->refresh();
        $this->assertTrue((bool) $identity->is_team_member);
    }

    // ── autoLinkBots: reuses existing person by name ──────────────────────────

    public function test_bot_reuses_existing_person_with_same_name(): void
    {
        $existing = Person::create([
            'first_name' => 'Onboarding',
            'last_name'  => 'Assistant',
            'is_our_org' => false,
        ]);
        $identity = $this->makeBot('slack_user', 'U_BOT4', 'Onboarding Assistant');

        (new AutoResolver())->autoLinkBots();

        $identity->refresh();
        $this->assertEquals($existing->id, $identity->person_id);
        $this->assertEquals(1, Person::count()); // no duplicate created
        $existing->refresh();
        $this->assertTrue((bool) $existing->is_our_org); // updated to our org
    }

    // ── autoLinkBots: already-linked bot gets is_our_org fixed ───────────────

    public function test_already_linked_bot_gets_our_org_set(): void
    {
        $person   = Person::create(['first_name' => 'Old', 'last_name' => 'Bot', 'is_our_org' => false]);
        $identity = $this->makeBot('discord_user', 'D_BOT2', 'Old Bot', $person->id);

        (new AutoResolver())->autoLinkBots();

        $person->refresh();
        $this->assertTrue((bool) $person->is_our_org);
        $identity->refresh();
        $this->assertTrue((bool) $identity->is_team_member);
    }

    // ── autoLinkBots: non-bot identities are untouched ───────────────────────

    public function test_non_bot_identities_are_not_processed(): void
    {
        Identity::create([
            'type'             => 'slack_user',
            'value'            => 'U_HUMAN',
            'value_normalized' => 'u_human',
            'system_type'      => 'slack',
            'system_slug'      => 'test',
            'is_bot'           => false,
            'is_team_member'   => false,
            'meta_json'        => ['display_name' => 'Real Human'],
        ]);

        $count = (new AutoResolver())->autoLinkBots();

        $this->assertEquals(0, $count);
        $this->assertEquals(0, Person::count());
    }

    // ── autoLinkBots: single-word bot name ───────────────────────────────────

    public function test_bot_with_single_word_name(): void
    {
        $identity = $this->makeBot('slack_user', 'U_BOT5', 'Dyno');

        (new AutoResolver())->autoLinkBots();

        $identity->refresh();
        $person = $identity->person;
        $this->assertNotNull($person);
        $this->assertEquals('Dyno', $person->first_name);
        $this->assertNull($person->last_name);
        $this->assertTrue((bool) $person->is_our_org);
    }

    // ── both Slack and Discord bots handled in one call ───────────────────────

    public function test_both_slack_and_discord_bots_linked_in_one_call(): void
    {
        $slack   = $this->makeBot('slack_user',   'U_BOT6', 'Alpha Bot');
        $discord = $this->makeBot('discord_user', 'D_BOT3', 'Beta Bot');

        $count = (new AutoResolver())->autoLinkBots();

        $this->assertEquals(2, $count);
        $slack->refresh();
        $discord->refresh();
        $this->assertNotNull($slack->person_id);
        $this->assertNotNull($discord->person_id);
        $this->assertTrue((bool) $slack->is_team_member);
        $this->assertTrue((bool) $discord->is_team_member);
    }
}
