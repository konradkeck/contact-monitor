<?php

namespace Tests\Feature;

use App\Ai\CompanyAnalysis\PromptRenderer;
use App\Ai\CompanyAnalysis\ResultExtractor;
use App\Models\AnalysisStep;
use App\Models\AnalysisRun;
use App\Models\AnalysisStepRun;
use App\Models\Company;
use App\Models\DomainClassification;
use App\Models\SynchronizerServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        SynchronizerServer::create(['name' => 'Test', 'url' => 'http://localhost:8080', 'api_token' => 't', 'ingest_secret' => 's']);
    }

    // ── Config Page ──

    public function test_config_page_loads(): void
    {
        $response = $this->get(route('company-analysis.config.index'));
        $response->assertStatus(200);
    }

    public function test_default_steps_are_seeded(): void
    {
        $this->assertDatabaseHas('analysis_steps', ['key' => 'company_identity_resolution']);
        $this->assertDatabaseHas('analysis_steps', ['key' => 'company_profile_enrichment']);
        $this->assertDatabaseHas('analysis_steps', ['key' => 'gap_fill_missing_fields']);
    }

    // ── Step CRUD ──

    public function test_create_step(): void
    {
        $response = $this->post(route('company-analysis.config.steps.store'), [
            'key' => 'test_step',
            'name' => 'Test Step',
            'prompt_template' => 'Test {{company_name}}',
            'is_enabled' => true,
        ]);
        $response->assertRedirect(route('company-analysis.config.index'));
        $this->assertDatabaseHas('analysis_steps', ['key' => 'test_step']);
    }

    public function test_create_step_validates_key_format(): void
    {
        $response = $this->post(route('company-analysis.config.steps.store'), [
            'key' => 'Invalid Key!',
            'name' => 'Test',
            'prompt_template' => 'Test',
        ]);
        $response->assertSessionHasErrors('key');
    }

    public function test_create_step_validates_unique_key(): void
    {
        $response = $this->post(route('company-analysis.config.steps.store'), [
            'key' => 'company_identity_resolution',
            'name' => 'Dupe',
            'prompt_template' => 'Test',
        ]);
        $response->assertSessionHasErrors('key');
    }

    public function test_update_step(): void
    {
        $step = AnalysisStep::where('key', 'gap_fill_missing_fields')->first();
        $response = $this->put(route('company-analysis.config.steps.update', $step), [
            'key' => 'gap_fill_missing_fields',
            'name' => 'Updated Name',
            'prompt_template' => 'Updated prompt',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('analysis_steps', ['id' => $step->id, 'name' => 'Updated Name']);
    }

    public function test_delete_step(): void
    {
        $step = AnalysisStep::where('key', 'gap_fill_missing_fields')->first();
        $response = $this->delete(route('company-analysis.config.steps.destroy', $step));
        $response->assertRedirect();
        $this->assertDatabaseMissing('analysis_steps', ['id' => $step->id]);
    }

    public function test_reorder_steps(): void
    {
        $steps = AnalysisStep::ordered()->get();
        $response = $this->post(route('company-analysis.config.steps.reorder'), [
            'steps' => [
                ['id' => $steps[2]->id, 'sort_order' => 10],
                ['id' => $steps[0]->id, 'sort_order' => 20],
                ['id' => $steps[1]->id, 'sort_order' => 30],
            ],
        ]);
        $response->assertRedirect();
        $this->assertEquals(10, $steps[2]->fresh()->sort_order);
    }

    // ── ACL ──

    public function test_viewer_cannot_access_config(): void
    {
        $this->actingAsViewer();
        $this->get(route('company-analysis.config.index'))->assertStatus(403);
    }

    public function test_viewer_cannot_run_analysis(): void
    {
        $this->actingAsViewer();
        $company = Company::create(['name' => 'Test Co ' . rand(1000,9999)]);
        $this->post(route('company-analysis.run', $company), [
            'step_ids' => [1],
        ])->assertStatus(403);
    }

    // ── Preview ──

    public function test_preview_returns_steps_and_context(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $response = $this->getJson(route('company-analysis.preview', $company));
        $response->assertOk()
            ->assertJsonStructure(['steps', 'context', 'available_variables'])
            ->assertJsonPath('context.company_name', 'Acme Corp');
    }

    // ── Latest Summary ──

    public function test_latest_summary_returns_null_when_no_runs(): void
    {
        $company = Company::create(['name' => 'Test Co ' . rand(1000,9999)]);
        $response = $this->getJson(route('company-analysis.latest', $company));
        $response->assertOk()->assertJsonPath('run', null);
    }

    // ── Analysis Results Page ──

    public function test_analysis_show_page_loads(): void
    {
        $company = Company::create(['name' => 'Test Co ' . rand(1000,9999)]);
        $run = AnalysisRun::create([
            'company_id' => $company->id,
            'user_id' => auth()->id(),
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $response = $this->get(route('company-analysis.show', [$company, $run]));
        $response->assertStatus(200);
    }

    public function test_analysis_show_404_for_wrong_company(): void
    {
        $company1 = Company::create(['name' => 'Test Co ' . rand(1000,9999)]);
        $company2 = Company::create(['name' => 'Test Co ' . rand(1000,9999)]);
        $run = AnalysisRun::create([
            'company_id' => $company1->id,
            'user_id' => auth()->id(),
            'status' => 'completed',
        ]);
        $this->get(route('company-analysis.show', [$company2, $run]))->assertNotFound();
    }

    // ── Domain Classification ──

    public function test_domain_classification_helpers(): void
    {
        DomainClassification::create(['domain' => 'gmail.com', 'type' => 'free_email', 'source' => 'test']);
        DomainClassification::create(['domain' => 'tempmail.com', 'type' => 'disposable', 'source' => 'test']);

        $this->assertTrue(DomainClassification::isFreeEmail('gmail.com'));
        $this->assertFalse(DomainClassification::isFreeEmail('acme.com'));
        $this->assertTrue(DomainClassification::isDisposable('tempmail.com'));
        $this->assertFalse(DomainClassification::isDisposable('gmail.com'));
    }

    // ── Prompt Renderer ──

    public function test_prompt_renderer_replaces_base_vars(): void
    {
        $renderer = new PromptRenderer();
        $result = $renderer->render('Hello {{company_name}}, domain: {{primary_domain}}', [
            'company_name' => 'Acme',
            'primary_domain' => 'acme.com',
        ]);
        $this->assertEquals('Hello Acme, domain: acme.com', $result);
    }

    public function test_prompt_renderer_replaces_previous_step_vars(): void
    {
        $renderer = new PromptRenderer();
        $result = $renderer->render('Name: {{previous.step1.name}}', [], [
            'step1' => ['name' => 'Acme Corp', 'domain' => 'acme.com'],
        ]);
        $this->assertEquals('Name: Acme Corp', $result);
    }

    public function test_prompt_renderer_handles_missing_vars(): void
    {
        $renderer = new PromptRenderer();
        $result = $renderer->render('Hello {{missing_var}}!', []);
        $this->assertEquals('Hello !', $result);
    }

    public function test_prompt_renderer_full_previous_step(): void
    {
        $renderer = new PromptRenderer();
        $result = $renderer->render('Data: {{previous.step1}}', [], [
            'step1' => ['a' => 1],
        ]);
        $this->assertStringContainsString('"a": 1', $result);
    }

    // ── Result Extractor ──

    public function test_result_extractor_parses_json(): void
    {
        $extractor = new ResultExtractor();
        $result = $extractor->parseResponse('```json
{"field": "value"}
```');
        $this->assertEquals(['field' => 'value'], $result);
    }

    public function test_result_extractor_returns_null_for_invalid(): void
    {
        $extractor = new ResultExtractor();
        $this->assertNull($extractor->parseResponse('not json'));
    }

    // ── Domain Sync ──

    public function test_domain_sync_manual(): void
    {
        $response = $this->post(route('company-analysis.config.domain-sync'));
        $response->assertRedirect();
    }

    public function test_domain_settings_save(): void
    {
        $response = $this->post(route('company-analysis.config.domain-settings'), [
            'auto_enabled' => true,
            'sources' => [
                'disposable' => 'https://example.com/disposable.txt',
                'free_email' => 'https://example.com/free.txt',
            ],
        ]);
        $response->assertRedirect();
    }
}
