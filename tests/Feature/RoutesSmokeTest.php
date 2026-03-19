<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Person;
use App\Models\SynchronizerServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests — every major page should return 200, not 500.
 * These catch route/controller registration errors, missing view data, etc.
 */
class RoutesSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        SynchronizerServer::create([
            'name'          => 'Test Server',
            'url'           => 'http://localhost:8080',
            'api_token'     => 'token',
            'ingest_secret' => 'secret',
        ]);
    }

    // ── Browse data ───────────────────────────────────────────────────────────

    public function test_dashboard_loads(): void
    {
        $this->get(route('dashboard'))->assertStatus(200);
    }

    public function test_companies_index_loads(): void
    {
        $this->get(route('companies.index'))->assertStatus(200);
    }

    public function test_companies_create_loads(): void
    {
        $this->get(route('companies.create'))->assertStatus(200);
    }

    public function test_company_show_loads(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('DISTINCT ON requires PostgreSQL');
        }
        $company = Company::create(['name' => 'Test Co']);

        $this->get(route('companies.show', $company))->assertStatus(200);
    }

    public function test_company_edit_loads(): void
    {
        $company = Company::create(['name' => 'Test Co']);

        $this->get(route('companies.edit', $company))->assertStatus(200);
    }

    public function test_people_index_loads(): void
    {
        $this->get(route('people.index'))->assertStatus(200);
    }

    public function test_people_create_loads(): void
    {
        $this->get(route('people.create'))->assertStatus(200);
    }

    public function test_person_show_loads(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Requires PostgreSQL (window functions)');
        }
        $person = Person::create(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $this->get(route('people.show', $person))->assertStatus(200);
    }

    public function test_person_edit_loads(): void
    {
        $person = Person::create(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $this->get(route('people.edit', $person))->assertStatus(200);
    }

    public function test_conversations_index_loads(): void
    {
        $this->get(route('conversations.index'))->assertStatus(200);
    }

    public function test_activity_index_loads(): void
    {
        $this->get(route('activity.index'))->assertStatus(200);
    }

    public function test_audit_log_loads(): void
    {
        $this->get(route('audit-log.index'))->assertStatus(200);
    }

    // ── Configuration ─────────────────────────────────────────────────────────

    public function test_setup_assistant_loads(): void
    {
        $this->get(route('setup-assistant.index'))->assertStatus(200);
    }

    public function test_team_access_loads(): void
    {
        $this->get(route('team-access.index'))->assertStatus(200);
    }

    public function test_synchronizer_servers_index_loads(): void
    {
        $this->get(route('synchronizer.servers.index'))->assertStatus(200);
    }

    public function test_data_relations_index_loads(): void
    {
        $this->get(route('data-relations.index'))->assertStatus(200);
    }

    public function test_filtering_index_loads(): void
    {
        $this->get(route('filtering.index'))->assertStatus(200);
    }

    public function test_our_organization_loads(): void
    {
        $this->get(route('our-company.index'))->assertStatus(200);
    }

    public function test_change_password_page_loads(): void
    {
        $this->get(route('auth.change-password'))->assertStatus(200);
    }

    // ── Wizard (regression: was 500 before ->except(['show']) fix) ───────────

    public function test_synchronizer_wizard_step1_loads(): void
    {
        $this->get(route('synchronizer.wizard.step1'))->assertStatus(200);
    }

    public function test_synchronizer_wizard_configure_new_loads(): void
    {
        $this->get(route('synchronizer.wizard.configure-new'))->assertStatus(200);
    }

    public function test_synchronizer_wizard_connect_existing_loads(): void
    {
        $this->get(route('synchronizer.wizard.connect-existing'))->assertStatus(200);
    }
}
