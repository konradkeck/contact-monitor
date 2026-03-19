<?php

namespace Tests\Feature;

use App\Models\SynchronizerServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiIngestTest extends TestCase
{
    use RefreshDatabase;

    private function createServer(string $secret = 'valid-secret'): SynchronizerServer
    {
        return SynchronizerServer::create([
            'name'          => 'Test Server',
            'url'           => 'http://localhost:8080',
            'api_token'     => 'token',
            'ingest_secret' => $secret,
        ]);
    }

    public function test_ingest_without_secret_returns_401(): void
    {
        $this->createServer();

        $response = $this->postJson('/api/ingest/batch', [
            'batch_id'    => 'test-batch',
            'source_type' => 'whmcs',
            'source_slug' => 'test',
            'items'       => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_ingest_with_wrong_secret_returns_401(): void
    {
        $this->createServer('valid-secret');

        $response = $this->postJson('/api/ingest/batch', [
            'batch_id'    => 'test-batch',
            'source_type' => 'whmcs',
            'source_slug' => 'test',
            'items'       => [],
        ], ['X-Ingest-Secret' => 'wrong-secret']);

        $response->assertStatus(401);
    }

    public function test_ingest_with_valid_secret_and_empty_batch_succeeds(): void
    {
        $this->createServer('valid-secret');

        $response = $this->postJson('/api/ingest/batch', [
            'batch_id'    => 'test-batch-001',
            'source_type' => 'whmcs',
            'source_slug' => 'test',
            'items'       => [],
        ], ['X-Ingest-Secret' => 'valid-secret']);

        $response->assertSuccessful();
    }

    public function test_ingest_without_any_server_returns_401(): void
    {
        $response = $this->postJson('/api/ingest/batch', [
            'batch_id'    => 'test-batch',
            'source_type' => 'whmcs',
            'source_slug' => 'test',
            'items'       => [],
        ], ['X-Ingest-Secret' => 'any-secret']);

        $response->assertStatus(401);
    }
}
