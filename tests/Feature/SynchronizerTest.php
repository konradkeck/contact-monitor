<?php

namespace Tests\Feature;

use App\Models\SynchronizerServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SynchronizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_synchronizer_index_redirects_when_no_server(): void
    {
        $response = $this->get(route('synchronizer.index'));

        $response->assertRedirect(route('synchronizer.servers.index'));
    }

    public function test_synchronizer_index_attempts_load_when_server_exists(): void
    {
        SynchronizerServer::create([
            'name' => 'Test Server',
            'url' => 'http://127.0.0.1:9999',
            'api_token' => 'test-token',
            'ingest_secret' => 'test-secret',
        ]);

        // The page loads but shows an error since the synchronizer isn't reachable
        $response = $this->get(route('synchronizer.index'));

        $response->assertStatus(200);
        $response->assertViewHas('error');
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
