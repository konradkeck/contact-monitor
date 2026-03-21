<?php

namespace Tests\Feature;

use App\Models\AiCredential;
use App\Models\AiModelConfig;
use App\Models\SynchronizerServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        SynchronizerServer::create([
            'name'          => 'Test',
            'url'           => 'http://localhost:8080',
            'api_token'     => 'tok',
            'ingest_secret' => 'sec',
        ]);
    }

    // ── Config page ───────────────────────────────────────────────────────────

    public function test_ai_config_page_loads_credentials_tab(): void
    {
        $this->get(route('ai-config.index', ['tab' => 'credentials']))
            ->assertStatus(200)
            ->assertSee('Add Credential');
    }

    public function test_ai_config_page_loads_models_tab(): void
    {
        $this->get(route('ai-config.index', ['tab' => 'models']))
            ->assertStatus(200)
            ->assertSee('Model Assignment');
    }

    public function test_mcp_server_page_loads(): void
    {
        $this->get(route('mcp-server.index'))
            ->assertStatus(200)
            ->assertSee('MCP Server');
    }

    public function test_ai_config_page_loads_with_existing_credentials(): void
    {
        // Ensures ORDER BY on correct column name ('name', not 'label')
        AiCredential::create([
            'provider' => 'claude',
            'name'     => 'Key A',
            'api_key'  => 'sk-a',
        ]);
        AiCredential::create([
            'provider' => 'openai',
            'name'     => 'Key B',
            'api_key'  => 'sk-b',
        ]);

        $this->get(route('ai-config.index', ['tab' => 'credentials']))
            ->assertStatus(200)
            ->assertSee('Key A')
            ->assertSee('Key B');
    }

    public function test_ai_config_models_tab_with_credentials(): void
    {
        $cred = AiCredential::create([
            'provider' => 'claude',
            'name'     => 'My Key',
            'api_key'  => 'sk-test',
        ]);

        AiModelConfig::create([
            'action_type'   => 'analyze',
            'credential_id' => $cred->id,
            'model_name'    => 'claude-sonnet-4-6',
        ]);

        $this->get(route('ai-config.index', ['tab' => 'models']))
            ->assertStatus(200)
            ->assertSee('My Key');
    }

    // ── Credential CRUD ───────────────────────────────────────────────────────

    public function test_store_credential(): void
    {
        $this->post(route('ai-credentials.store'), [
            'provider' => 'claude',
            'name'     => 'My Claude Key',
            'api_key'  => 'sk-ant-test',
        ])->assertRedirect(route('ai-config.index', ['tab' => 'credentials']));

        $this->assertDatabaseHas('ai_credentials', [
            'provider' => 'claude',
            'name'     => 'My Claude Key',
        ]);

        // API key should be encrypted (not stored in plaintext)
        $cred = AiCredential::first();
        $this->assertNotEquals('sk-ant-test', $cred->getRawOriginal('api_key'));
        $this->assertEquals('sk-ant-test', $cred->getDecryptedApiKey());
    }

    public function test_store_credential_validates_provider(): void
    {
        $this->post(route('ai-credentials.store'), [
            'provider' => 'invalid-provider',
            'name'     => 'Bad',
            'api_key'  => 'key',
        ])->assertSessionHasErrors('provider');
    }

    public function test_store_credential_requires_fields(): void
    {
        $this->post(route('ai-credentials.store'), [])
            ->assertSessionHasErrors(['provider', 'name', 'api_key']);
    }

    public function test_destroy_credential(): void
    {
        $cred = AiCredential::create([
            'provider' => 'openai',
            'name'     => 'To Delete',
            'api_key'  => 'key',
        ]);

        $this->delete(route('ai-credentials.destroy', $cred))
            ->assertRedirect(route('ai-config.index', ['tab' => 'credentials']));

        $this->assertDatabaseMissing('ai_credentials', ['id' => $cred->id]);
    }

    public function test_destroy_credential_also_removes_model_configs(): void
    {
        $cred = AiCredential::create([
            'provider' => 'claude',
            'name'     => 'Key',
            'api_key'  => 'key',
        ]);

        AiModelConfig::create([
            'action_type'   => 'analyze',
            'credential_id' => $cred->id,
            'model_name'    => 'claude-sonnet-4-6',
        ]);

        $this->delete(route('ai-credentials.destroy', $cred));

        $this->assertDatabaseMissing('ai_model_configs', ['credential_id' => $cred->id]);
    }

    public function test_edit_credential_page_loads(): void
    {
        $cred = AiCredential::create([
            'provider' => 'claude',
            'name'     => 'My Key',
            'api_key'  => 'sk-test',
        ]);

        $this->get(route('ai-credentials.edit', $cred))
            ->assertStatus(200)
            ->assertSee('Edit Credential')
            ->assertSee('My Key');
    }

    public function test_update_credential(): void
    {
        $cred = AiCredential::create([
            'provider' => 'claude',
            'name'     => 'Old Name',
            'api_key'  => 'sk-old',
        ]);

        $this->put(route('ai-credentials.update', $cred), [
            'provider' => 'openai',
            'name'     => 'New Name',
        ])->assertRedirect(route('ai-config.index', ['tab' => 'credentials']));

        $cred->refresh();
        $this->assertEquals('New Name', $cred->name);
        $this->assertEquals('openai', $cred->provider);
        // API key should remain unchanged when not provided
        $this->assertEquals('sk-old', $cred->getDecryptedApiKey());
    }

    public function test_update_credential_with_new_api_key(): void
    {
        $cred = AiCredential::create([
            'provider' => 'claude',
            'name'     => 'Key',
            'api_key'  => 'sk-old',
        ]);

        $this->put(route('ai-credentials.update', $cred), [
            'provider' => 'claude',
            'name'     => 'Key',
            'api_key'  => 'sk-new',
        ]);

        $cred->refresh();
        $this->assertEquals('sk-new', $cred->getDecryptedApiKey());
    }

    // ── Model configs ─────────────────────────────────────────────────────────

    public function test_update_model_configs(): void
    {
        $cred = AiCredential::create([
            'provider' => 'claude',
            'name'     => 'Key',
            'api_key'  => 'key',
        ]);

        $this->post(route('ai-model-configs.update'), [
            'configs' => [
                'analyze' => [
                    'action_type'   => 'analyze',
                    'credential_id' => $cred->id,
                    'model_name'    => 'claude-sonnet-4-6',
                ],
            ],
        ])->assertRedirect(route('ai-config.index', ['tab' => 'models']));

        $this->assertDatabaseHas('ai_model_configs', [
            'action_type'   => 'analyze',
            'credential_id' => $cred->id,
            'model_name'    => 'claude-sonnet-4-6',
        ]);
    }

    public function test_update_model_configs_clears_empty(): void
    {
        $cred = AiCredential::create([
            'provider' => 'claude',
            'name'     => 'Key',
            'api_key'  => 'key',
        ]);

        AiModelConfig::create([
            'action_type'   => 'analyze',
            'credential_id' => $cred->id,
            'model_name'    => 'claude-sonnet-4-6',
        ]);

        // Submit with empty credential_id — should remove the config
        $this->post(route('ai-model-configs.update'), [
            'configs' => [
                'analyze' => [
                    'action_type'   => 'analyze',
                    'credential_id' => '',
                    'model_name'    => '',
                ],
            ],
        ]);

        $this->assertDatabaseMissing('ai_model_configs', ['action_type' => 'analyze']);
    }

    // ── AI Costs ──────────────────────────────────────────────────────────────

    public function test_ai_costs_index_loads(): void
    {
        $this->get(route('ai-costs.index'))->assertStatus(200);
    }

    public function test_ai_costs_pricing_loads(): void
    {
        $this->get(route('ai-costs.pricing'))->assertStatus(200);
    }

    // ── ACL ───────────────────────────────────────────────────────────────────

    public function test_viewer_cannot_access_ai_config(): void
    {
        $this->actingAsViewer();
        $this->get(route('ai-config.index'))->assertForbidden();
    }

    public function test_viewer_cannot_store_credential(): void
    {
        $this->actingAsViewer();
        $this->post(route('ai-credentials.store'), [
            'provider' => 'claude',
            'name'     => 'K',
            'api_key'  => 'key',
        ])->assertForbidden();
    }
}
