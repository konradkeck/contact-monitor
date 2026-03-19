<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    // ── /setup page (first-run flow) ─────────────────────────────────────────

    public function test_setup_page_accessible_when_no_users(): void
    {
        $this->get(route('setup'))->assertStatus(200);
    }

    public function test_setup_page_redirects_to_login_when_users_exist(): void
    {
        $group = Group::create(['name' => 'Admin', 'permissions' => Group::adminPermissions()]);
        User::create([
            'name'     => 'Existing',
            'email'    => 'existing@test.local',
            'password' => bcrypt('password'),
            'group_id' => $group->id,
        ]);

        $this->get(route('setup'))->assertRedirect(route('login'));
    }

    public function test_setup_creates_admin_and_logs_in(): void
    {
        $response = $this->post(route('setup.post'), [
            'email'                 => 'admin@example.com',
            'password'              => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
    }

    public function test_setup_creates_default_groups(): void
    {
        $this->post(route('setup.post'), [
            'email'                 => 'admin@example.com',
            'password'              => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $this->assertDatabaseHas('groups', ['name' => 'Admin']);
        $this->assertDatabaseHas('groups', ['name' => 'Analyst']);
        $this->assertDatabaseHas('groups', ['name' => 'Viewer']);
    }

    public function test_setup_assigns_admin_group_to_first_user(): void
    {
        $this->post(route('setup.post'), [
            'email'                 => 'admin@example.com',
            'password'              => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertEquals('Admin', $user->group->name);
    }

    public function test_setup_blocked_when_users_already_exist(): void
    {
        $group = Group::create(['name' => 'Admin', 'permissions' => Group::adminPermissions()]);
        User::create([
            'name'     => 'Existing',
            'email'    => 'existing@test.local',
            'password' => bcrypt('password'),
            'group_id' => $group->id,
        ]);

        $response = $this->post(route('setup.post'), [
            'email'                 => 'hacker@evil.com',
            'password'              => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('users', ['email' => 'hacker@evil.com']);
    }

    public function test_setup_validates_password_confirmation(): void
    {
        $response = $this->post(route('setup.post'), [
            'email'                 => 'admin@example.com',
            'password'              => 'secret1234',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
    }

    public function test_setup_validates_minimum_password_length(): void
    {
        $response = $this->post(route('setup.post'), [
            'email'                 => 'admin@example.com',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_setup_validates_email_format(): void
    {
        $response = $this->post(route('setup.post'), [
            'email'                 => 'not-an-email',
            'password'              => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertSessionHasErrors('email');
    }

    // ── /login redirects to /setup when no users ─────────────────────────────

    public function test_login_page_redirects_to_setup_when_no_users(): void
    {
        $this->get(route('login'))->assertRedirect(route('setup'));
    }

    public function test_login_page_accessible_when_users_exist(): void
    {
        $group = Group::create(['name' => 'Admin', 'permissions' => Group::adminPermissions()]);
        User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.local',
            'password' => bcrypt('password'),
            'group_id' => $group->id,
        ]);

        $this->get(route('login'))->assertStatus(200);
    }
}
