<?php

namespace Tests\Feature;

use App\Models\McpLog;
use App\Models\Company;
use App\Models\Note;
use App\Models\Person;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('mcp_enabled', true);
    }

    private function mcp(string $method, array $params = [], ?string $id = '1'): array
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'method'  => $method,
            'params'  => $params,
        ]);

        return $response->json();
    }

    // ── Auth ─────────────────────────────────────────────────────────────────

    public function test_mcp_disabled_returns_error(): void
    {
        SystemSetting::set('mcp_enabled', false);

        $result = $this->mcp('initialize');

        $this->assertEquals(-32002, $result['error']['code']);
    }

    public function test_localhost_allowed_without_api_key(): void
    {
        // The test runner hits from 127.0.0.1 — should be allowed
        $result = $this->mcp('initialize');

        $this->assertArrayHasKey('result', $result);
        $this->assertEquals('Contact Monitor MCP', $result['result']['serverInfo']['name']);
    }

    public function test_external_access_disabled_returns_error(): void
    {
        SystemSetting::set('mcp_external_enabled', false);

        // Simulate external IP by using REMOTE_ADDR header
        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
            ->postJson('/api/mcp', [
                'jsonrpc' => '2.0',
                'id'      => '1',
                'method'  => 'initialize',
                'params'  => [],
            ]);

        $body = $response->json();
        $this->assertEquals(-32001, $body['error']['code']);
    }

    public function test_external_access_with_valid_api_key(): void
    {
        $rawKey = bin2hex(random_bytes(32));
        SystemSetting::set('mcp_api_key', hash('sha256', $rawKey));
        SystemSetting::set('mcp_external_enabled', true);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
            ->withHeaders(['Authorization' => 'Bearer ' . $rawKey])
            ->postJson('/api/mcp', [
                'jsonrpc' => '2.0',
                'id'      => '1',
                'method'  => 'initialize',
                'params'  => [],
            ]);

        $body = $response->json();
        $this->assertArrayHasKey('result', $body);
    }

    public function test_external_access_with_invalid_api_key_returns_error(): void
    {
        SystemSetting::set('mcp_api_key', hash('sha256', 'correctkey'));
        SystemSetting::set('mcp_external_enabled', true);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
            ->withHeaders(['Authorization' => 'Bearer wrongkey'])
            ->postJson('/api/mcp', [
                'jsonrpc' => '2.0',
                'id'      => '1',
                'method'  => 'initialize',
                'params'  => [],
            ]);

        $body = $response->json();
        $this->assertEquals(-32001, $body['error']['code']);
    }

    // ── Protocol ─────────────────────────────────────────────────────────────

    public function test_initialize_returns_server_info(): void
    {
        $result = $this->mcp('initialize');

        $this->assertArrayHasKey('result', $result);
        $this->assertEquals('2024-11-05', $result['result']['protocolVersion']);
        $this->assertEquals('Contact Monitor MCP', $result['result']['serverInfo']['name']);
        $this->assertArrayHasKey('resources', $result['result']['capabilities']);
        $this->assertArrayHasKey('tools', $result['result']['capabilities']);
    }

    public function test_unknown_method_returns_method_not_found(): void
    {
        $result = $this->mcp('nonexistent/method');
        $this->assertEquals(-32601, $result['error']['code']);
    }

    public function test_invalid_json_returns_parse_error(): void
    {
        $response = $this->call('POST', '/api/mcp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR'  => '127.0.0.1',
        ], 'not valid json');

        $body = $response->json();
        $this->assertEquals(-32700, $body['error']['code']);
    }

    // ── Resources/list ───────────────────────────────────────────────────────

    public function test_resources_list_returns_all_resources(): void
    {
        $result = $this->mcp('resources/list');

        $this->assertArrayHasKey('result', $result);
        $resources = $result['result']['resources'];
        $uris = array_column($resources, 'uri');

        $this->assertContains('companies://list', $uris);
        $this->assertContains('people://list', $uris);
        $this->assertContains('conversations://list', $uris);
        $this->assertContains('activity://search', $uris);
        $this->assertContains('notes://list', $uris);
        $this->assertContains('smart_notes://list', $uris);
        $this->assertContains('audit_log://list', $uris);
    }

    // ── Resources/read ───────────────────────────────────────────────────────

    public function test_read_companies_list(): void
    {
        Company::create(['name' => 'Acme Corp']);
        Company::create(['name' => 'Beta Ltd']);

        $result = $this->mcp('resources/read', ['uri' => 'companies://list']);

        $this->assertArrayHasKey('result', $result);
        $data = json_decode($result['result']['contents'][0]['text'], true);

        $this->assertGreaterThanOrEqual(2, $data['total']);
        $names = array_column($data['items'], 'name');
        $this->assertContains('Acme Corp', $names);
    }

    public function test_read_companies_list_with_search(): void
    {
        Company::create(['name' => 'Acme Corp']);
        Company::create(['name' => 'Beta Ltd']);

        $result = $this->mcp('resources/read', ['uri' => 'companies://list', 'params' => ['q' => 'Acme']]);

        $data = json_decode($result['result']['contents'][0]['text'], true);
        $this->assertEquals(1, $data['total']);
        $this->assertEquals('Acme Corp', $data['items'][0]['name']);
    }

    public function test_read_company_by_id(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);

        $result = $this->mcp('resources/read', ['uri' => "companies://{$company->id}"]);

        $data = json_decode($result['result']['contents'][0]['text'], true);
        $this->assertEquals('Acme Corp', $data['name']);
        $this->assertArrayHasKey('domains', $data);
        $this->assertArrayHasKey('people', $data);
    }

    public function test_read_people_list(): void
    {
        Person::create(['first_name' => 'John', 'last_name' => 'Doe']);

        $result = $this->mcp('resources/read', ['uri' => 'people://list']);

        $data = json_decode($result['result']['contents'][0]['text'], true);
        $this->assertGreaterThanOrEqual(1, $data['total']);
    }

    public function test_read_notes_list(): void
    {
        $result = $this->mcp('resources/read', ['uri' => 'notes://list']);

        $data = json_decode($result['result']['contents'][0]['text'], true);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('items', $data);
    }

    public function test_read_unknown_uri_returns_error(): void
    {
        $result = $this->mcp('resources/read', ['uri' => 'unknown://something']);
        $this->assertEquals(-32602, $result['error']['code']);
    }

    // ── Tools/list ───────────────────────────────────────────────────────────

    public function test_tools_list_returns_all_tools(): void
    {
        $result = $this->mcp('tools/list');

        $this->assertArrayHasKey('result', $result);
        $tools = $result['result']['tools'];
        $names = array_column($tools, 'name');

        $this->assertContains('company_create', $names);
        $this->assertContains('person_create', $names);
        $this->assertContains('note_create', $names);
        $this->assertContains('smart_note_recognize', $names);
        $this->assertContains('company_merge', $names);
        $this->assertContains('person_merge', $names);
    }

    // ── Tools/call ───────────────────────────────────────────────────────────

    public function test_company_create_tool(): void
    {
        $result = $this->mcp('tools/call', [
            'name'      => 'company_create',
            'arguments' => ['name' => 'New Corp', 'primary_domain' => 'newcorp.com'],
        ]);

        $this->assertArrayHasKey('result', $result);
        $data = json_decode($result['result']['content'][0]['text'], true);

        $this->assertEquals('New Corp', $data['name']);
        $this->assertDatabaseHas('companies', ['name' => 'New Corp']);
        $this->assertDatabaseHas('company_domains', ['domain' => 'newcorp.com', 'is_primary' => true]);
    }

    public function test_company_create_logs_to_ai_log(): void
    {
        $this->mcp('tools/call', [
            'name'      => 'company_create',
            'arguments' => ['name' => 'Logged Corp'],
        ]);

        $this->assertDatabaseHas('mcp_logs', ['tool_name' => 'company_create']);
    }

    public function test_person_create_tool(): void
    {
        $result = $this->mcp('tools/call', [
            'name'      => 'person_create',
            'arguments' => ['first_name' => 'Jane', 'last_name' => 'Smith'],
        ]);

        $data = json_decode($result['result']['content'][0]['text'], true);
        $this->assertEquals('Jane Smith', $data['full_name']);
        $this->assertDatabaseHas('people', ['first_name' => 'Jane', 'last_name' => 'Smith']);
    }

    public function test_note_create_tool(): void
    {
        $company = Company::create(['name' => 'Test Co']);

        $result = $this->mcp('tools/call', [
            'name'      => 'note_create',
            'arguments' => [
                'content'     => 'Test note content',
                'entity_type' => 'App\Models\Company',
                'entity_id'   => $company->id,
            ],
        ]);

        $data = json_decode($result['result']['content'][0]['text'], true);
        $this->assertEquals('Test note content', $data['content']);
        $this->assertDatabaseHas('notes', ['content' => 'Test note content', 'source' => 'mcp']);
        $this->assertDatabaseHas('note_links', ['linkable_type' => 'App\Models\Company', 'linkable_id' => $company->id]);
    }

    public function test_note_create_invalid_entity_type_returns_error(): void
    {
        $result = $this->mcp('tools/call', [
            'name'      => 'note_create',
            'arguments' => [
                'content'     => 'Test note',
                'entity_type' => 'App\Models\SomethingInvalid',
                'entity_id'   => 1,
            ],
        ]);

        $this->assertEquals(-32602, $result['error']['code']);
    }

    public function test_company_merge_tool(): void
    {
        $source = Company::create(['name' => 'Old Corp']);
        $target = Company::create(['name' => 'Main Corp']);

        $result = $this->mcp('tools/call', [
            'name'      => 'company_merge',
            'arguments' => ['source_id' => $source->id, 'target_id' => $target->id],
        ]);

        $data = json_decode($result['result']['content'][0]['text'], true);
        $this->assertEquals($target->id, $data['target_id']);

        $source->refresh();
        $this->assertEquals($target->id, $source->merged_into_id);
    }

    public function test_company_merge_same_id_returns_error(): void
    {
        $company = Company::create(['name' => 'Same Corp']);

        $result = $this->mcp('tools/call', [
            'name'      => 'company_merge',
            'arguments' => ['source_id' => $company->id, 'target_id' => $company->id],
        ]);

        $this->assertEquals(-32602, $result['error']['code']);
    }

    public function test_chat_context_requires_confirmation(): void
    {
        $result = $this->mcp('tools/call', [
            'name'      => 'company_create',
            'arguments' => ['name' => 'Confirm Corp', '_context' => 'chat'],
        ]);

        $data = json_decode($result['result']['content'][0]['text'], true);
        $this->assertTrue($data['confirmation_required']);
        $this->assertArrayHasKey('confirm_token', $data);

        // Company should NOT exist yet
        $this->assertDatabaseMissing('companies', ['name' => 'Confirm Corp']);
    }

    public function test_chat_context_confirm_token_executes_action(): void
    {
        // Step 1: get token
        $step1 = $this->mcp('tools/call', [
            'name'      => 'company_create',
            'arguments' => ['name' => 'Confirmed Corp', '_context' => 'chat'],
        ]);
        $token = json_decode($step1['result']['content'][0]['text'], true)['confirm_token'];

        // Step 2: confirm
        $step2 = $this->mcp('tools/call', [
            'name'      => 'company_create',
            'arguments' => ['name' => 'Confirmed Corp', '_confirm_token' => $token],
        ]);

        $data = json_decode($step2['result']['content'][0]['text'], true);
        $this->assertEquals('Confirmed Corp', $data['name']);
        $this->assertDatabaseHas('companies', ['name' => 'Confirmed Corp']);
    }

    public function test_automated_context_skips_confirmation(): void
    {
        $result = $this->mcp('tools/call', [
            'name'      => 'company_create',
            'arguments' => ['name' => 'Auto Corp', '_context' => 'automated'],
        ]);

        $data = json_decode($result['result']['content'][0]['text'], true);
        $this->assertEquals('Auto Corp', $data['name']);
        $this->assertDatabaseHas('companies', ['name' => 'Auto Corp']);

        $this->assertDatabaseHas('mcp_logs', ['tool_name' => 'company_create', 'context' => 'automated']);
    }

    public function test_invalid_confirm_token_returns_error(): void
    {
        $result = $this->mcp('tools/call', [
            'name'      => 'company_create',
            'arguments' => ['name' => 'Test', '_confirm_token' => 'invalid_token_xyz'],
        ]);

        $this->assertEquals(-32602, $result['error']['code']);
    }

    public function test_unknown_tool_returns_error(): void
    {
        $result = $this->mcp('tools/call', [
            'name'      => 'nonexistent_tool',
            'arguments' => [],
        ]);

        $this->assertEquals(-32602, $result['error']['code']);
    }
}
