<?php

namespace Tests\Feature;

use App\Models\SynchronizerServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_dashboard_redirects_to_servers_when_no_server(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('synchronizer.servers.index'));
    }

    public function test_dashboard_loads_when_server_exists(): void
    {
        SynchronizerServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080',
            'api_token' => 'test-token',
            'ingest_secret' => 'test-secret',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_dashboard_shows_stats(): void
    {
        SynchronizerServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080',
            'api_token' => 'test-token',
            'ingest_secret' => 'test-secret',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('stats');
    }
}
