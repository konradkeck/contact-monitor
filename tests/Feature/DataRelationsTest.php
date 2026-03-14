<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Identity;
use App\Models\Person;
use App\Models\SynchronizerServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataRelationsTest extends TestCase
{
    use RefreshDatabase;

    private function createServer(): void
    {
        SynchronizerServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080',
            'api_token' => 'test-token',
            'ingest_secret' => 'test-secret',
        ]);
    }

    public function test_data_relations_index_returns_200(): void
    {
        $this->createServer();

        $response = $this->get(route('data-relations.index'));

        $response->assertStatus(200);
    }

    public function test_data_relations_index_shows_stats(): void
    {
        $this->createServer();

        $response = $this->get(route('data-relations.index'));

        $response->assertStatus(200);
        $response->assertViewHas('stats');
    }

    public function test_data_relations_index_with_accounts(): void
    {
        $this->createServer();

        $company = Company::create(['name' => 'WHMCS Co']);

        Account::create([
            'system_type' => 'whmcs',
            'system_slug' => 'whmcs-main',
            'external_id' => '123',
            'company_id' => $company->id,
            'meta_json' => ['email' => 'client@whmcs.co', 'company_name' => 'WHMCS Co'],
        ]);

        $response = $this->get(route('data-relations.index'));

        $response->assertStatus(200);
        $response->assertViewHas('accountSystems');
    }

    public function test_data_relations_mapping_account_based_returns_200(): void
    {
        $this->createServer();

        Account::create([
            'system_type' => 'whmcs',
            'system_slug' => 'main',
            'external_id' => '1',
            'company_id' => null,
            'meta_json' => ['company_name' => 'Test Co'],
        ]);

        $response = $this->get(route('data-relations.mapping', ['systemType' => 'whmcs', 'systemSlug' => 'main']));

        $response->assertStatus(200);
    }

    public function test_data_relations_mapping_identity_based_returns_200(): void
    {
        $this->createServer();

        Identity::create([
            'type' => 'slack_user',
            'system_slug' => 'workspace',
            'value' => 'U123456',
            'value_normalized' => 'u123456',
        ]);

        $response = $this->get(route('data-relations.mapping', ['systemType' => 'slack', 'systemSlug' => 'workspace']));

        $response->assertStatus(200);
    }

    public function test_data_relations_mapping_aborts_when_account_slug_used_for_identity(): void
    {
        $this->createServer();

        Account::create([
            'system_type' => 'whmcs',
            'system_slug' => 'protected-slug',
            'external_id' => '1',
            'company_id' => null,
        ]);

        // Trying to access identity mapping for a WHMCS slug should 404
        $response = $this->get(route('data-relations.mapping', ['systemType' => 'imap', 'systemSlug' => 'protected-slug']));

        $response->assertStatus(404);
    }
}
