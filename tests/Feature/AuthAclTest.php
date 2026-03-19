<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\Note;
use App\Models\Person;
use App\Models\SynchronizerServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAclTest extends TestCase
{
    use RefreshDatabase;

    private function createServer(): void
    {
        SynchronizerServer::create([
            'name' => 'Test Server',
            'url'  => 'http://localhost:8080',
            'api_token'     => 'token',
            'ingest_secret' => 'secret',
        ]);
    }

    // ── Login / Logout ───────────────────────────────────────────────────────

    public function test_login_page_is_accessible(): void
    {
        // Requires at least one user to exist; otherwise /login redirects to /setup
        $group = Group::create(['name' => 'Admin', 'permissions' => Group::adminPermissions()]);
        User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.local',
            'password' => bcrypt('password'),
            'group_id' => $group->id,
        ]);

        $this->get(route('login'))->assertStatus(200);
    }

    public function test_unauthenticated_browse_redirects_to_login(): void
    {
        $this->createServer();
        $this->get(route('companies.index'))->assertRedirect(route('login'));
        $this->get(route('people.index'))->assertRedirect(route('login'));
    }

    public function test_login_with_valid_credentials(): void
    {
        $group = Group::create(['name' => 'Admin', 'permissions' => Group::adminPermissions()]);
        $user  = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.local',
            'password' => bcrypt('secret123'),
            'group_id' => $group->id,
        ]);

        $response = $this->post(route('login.post'), [
            'email'    => 'admin@test.local',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_invalid_credentials_fails(): void
    {
        $group = Group::create(['name' => 'Admin', 'permissions' => Group::adminPermissions()]);
        User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.local',
            'password' => bcrypt('secret123'),
            'group_id' => $group->id,
        ]);

        $response = $this->post(route('login.post'), [
            'email'    => 'admin@test.local',
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_clears_session(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ── Admin: full access ───────────────────────────────────────────────────

    public function test_admin_can_access_browse_data(): void
    {
        $this->actingAsAdmin();
        $this->createServer();

        $this->get(route('companies.index'))->assertStatus(200);
        $this->get(route('people.index'))->assertStatus(200);
    }

    public function test_admin_can_access_configuration(): void
    {
        $this->actingAsAdmin();

        $this->get(route('synchronizer.servers.index'))->assertStatus(200);
        $this->get(route('team-access.index'))->assertStatus(200);
    }

    public function test_admin_can_create_company(): void
    {
        $this->actingAsAdmin();
        $this->createServer();

        $response = $this->post(route('companies.store'), ['name' => 'Test Co']);

        $response->assertRedirect();
        $this->assertDatabaseHas('companies', ['name' => 'Test Co']);
    }

    public function test_admin_can_add_note(): void
    {
        $this->actingAsAdmin();
        $this->createServer();
        $company = Company::create(['name' => 'Test Co']);

        $response = $this->post(route('notes.store'), [
            'linkable_type' => 'company',
            'linkable_id'   => $company->id,
            'content'       => 'Test note',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notes', ['content' => 'Test note']);
    }

    // ── Viewer: browse only ──────────────────────────────────────────────────

    public function test_viewer_can_access_browse_data(): void
    {
        $this->actingAsViewer();
        $this->createServer();

        $this->get(route('companies.index'))->assertStatus(200);
        $this->get(route('people.index'))->assertStatus(200);
    }

    public function test_viewer_cannot_access_configuration(): void
    {
        $this->actingAsViewer();

        $this->get(route('synchronizer.servers.index'))->assertStatus(403);
        $this->get(route('team-access.index'))->assertStatus(403);
    }

    public function test_viewer_cannot_create_company(): void
    {
        $this->actingAsViewer();
        $this->createServer();

        $this->post(route('companies.store'), ['name' => 'Test Co'])->assertStatus(403);
    }

    public function test_viewer_cannot_add_note(): void
    {
        $this->actingAsViewer();
        $this->createServer();
        $company = Company::create(['name' => 'Test Co']);

        $this->post(route('notes.store'), [
            'linkable_type' => 'company',
            'linkable_id'   => $company->id,
            'content'       => 'Test note',
        ])->assertStatus(403);
    }

    // ── Analyst: browse + notes, no data_write, no configuration ────────────

    public function test_analyst_can_access_browse_data(): void
    {
        $this->actingAsAnalyst();
        $this->createServer();

        $this->get(route('companies.index'))->assertStatus(200);
        $this->get(route('people.index'))->assertStatus(200);
    }

    public function test_analyst_cannot_access_configuration(): void
    {
        $this->actingAsAnalyst();

        $this->get(route('synchronizer.servers.index'))->assertStatus(403);
        $this->get(route('team-access.index'))->assertStatus(403);
    }

    public function test_analyst_cannot_create_company(): void
    {
        $this->actingAsAnalyst();
        $this->createServer();

        $this->post(route('companies.store'), ['name' => 'Test Co'])->assertStatus(403);
    }

    public function test_analyst_cannot_create_person(): void
    {
        $this->actingAsAnalyst();
        $this->createServer();

        $this->post(route('people.store'), ['first_name' => 'Jane', 'last_name' => 'Doe'])->assertStatus(403);
    }

    public function test_analyst_can_add_note(): void
    {
        $this->actingAsAnalyst();
        $this->createServer();
        $company = Company::create(['name' => 'Test Co']);

        $response = $this->post(route('notes.store'), [
            'linkable_type' => 'company',
            'linkable_id'   => $company->id,
            'content'       => 'Analyst note',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notes', ['content' => 'Analyst note']);
    }

    // ── Change Password ──────────────────────────────────────────────────────

    public function test_user_can_change_password(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('auth.change-password.post'), [
            'current_password'      => 'password',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect();
    }

    public function test_change_password_fails_with_wrong_current(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('auth.change-password.post'), [
            'current_password'      => 'wrongpassword',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('current_password');
    }
}
