<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the synchronizer-servers wizard route conflict.
 *
 * Bug: Route::resource registered GET configuration/synchronizer-servers/{server}
 * which matched "wizard" as the server parameter and called the non-existent show()
 * method, causing a 500 error. Fixed by adding ->except(['show']) to the resource.
 */
class WizardRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_wizard_step1_returns_200_not_500(): void
    {
        $response = $this->get(route('synchronizer.wizard.step1'));

        $response->assertStatus(200);
    }

    public function test_wizard_configure_new_returns_200(): void
    {
        $response = $this->get(route('synchronizer.wizard.configure-new'));

        $response->assertStatus(200);
    }

    public function test_wizard_connect_existing_returns_200(): void
    {
        $response = $this->get(route('synchronizer.wizard.connect-existing'));

        $response->assertStatus(200);
    }

    public function test_synchronizer_servers_index_returns_200(): void
    {
        $response = $this->get(route('synchronizer.servers.index'));

        $response->assertStatus(200);
    }

    public function test_synchronizer_servers_create_returns_200(): void
    {
        $response = $this->get(route('synchronizer.servers.create'));

        $response->assertStatus(200);
    }
}
