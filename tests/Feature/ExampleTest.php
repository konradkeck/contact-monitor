<?php

namespace Tests\Feature;

use App\Models\SynchronizerServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_redirects_to_setup_when_no_server_configured(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('synchronizer.servers.index'));
    }

    public function test_dashboard_loads_when_server_is_configured(): void
    {
        SynchronizerServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost',
            'api_token' => 'token',
            'ingest_secret' => 'secret',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
