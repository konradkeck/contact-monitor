<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SynchronizerServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompaniesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private function createServer(): void
    {
        SynchronizerServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080',
            'api_token' => 'test-token',
            'ingest_secret' => 'test-secret',
        ]);
    }

    public function test_companies_index_returns_200(): void
    {
        $this->createServer();

        $response = $this->get(route('companies.index'));

        $response->assertStatus(200);
    }

    public function test_companies_index_empty_list(): void
    {
        $this->createServer();

        $response = $this->get(route('companies.index'));

        $response->assertStatus(200);
        $response->assertViewHas('companies');
    }

    public function test_companies_index_shows_company_name(): void
    {
        $this->createServer();

        Company::create(['name' => 'Acme Corp']);

        $response = $this->get(route('companies.index'));

        $response->assertStatus(200);
        $response->assertSee('Acme Corp');
    }

    public function test_companies_show_returns_404_for_nonexistent(): void
    {
        $this->createServer();

        $response = $this->get(route('companies.show', 99999));

        $response->assertStatus(404);
    }

    public function test_companies_search_requires_two_chars(): void
    {
        $this->createServer();

        $response = $this->get(route('companies.search', ['q' => 'A']));

        $response->assertStatus(200);
        $response->assertExactJson([]);
    }
}
