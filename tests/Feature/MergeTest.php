<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Identity;
use App\Models\Person;
use App\Models\SynchronizerServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        SynchronizerServer::create([
            'name'          => 'Test Server',
            'url'           => 'http://localhost:8080',
            'api_token'     => 'token',
            'ingest_secret' => 'secret',
        ]);
    }

    // ── Company merge ────────────────────────────────────────────────────────

    public function test_companies_index_excludes_merged(): void
    {
        $primary = Company::create(['name' => 'Primary Co']);
        $merged  = Company::create(['name' => 'Merged Co', 'merged_into_id' => $primary->id]);

        $response = $this->get(route('companies.index'));
        $response->assertStatus(200);
        $response->assertSee('Primary Co');
        $response->assertDontSee('Merged Co');
    }

    public function test_company_merge_modal_requires_two_ids(): void
    {
        $company = Company::create(['name' => 'Only One']);

        $response = $this->get(route('companies.merge-modal', ['ids[]' => [$company->id]]));
        $response->assertStatus(400);
    }

    public function test_company_merge_modal_loads_with_two_companies(): void
    {
        $a = Company::create(['name' => 'Alpha Corp']);
        $b = Company::create(['name' => 'Beta Corp']);

        $response = $this->getJson(route('companies.merge-modal', ['ids' => [$a->id, $b->id]]));
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Alpha Corp']);
        $response->assertJsonFragment(['name' => 'Beta Corp']);
    }

    public function test_company_merge_sets_merged_into_id(): void
    {
        $primary = Company::create(['name' => 'Primary']);
        $secondary = Company::create(['name' => 'Secondary']);

        $response = $this->post(route('companies.merge'), [
            'primary_id' => $primary->id,
            'merge_ids'  => [$secondary->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $this->assertDatabaseHas('companies', [
            'id'             => $secondary->id,
            'merged_into_id' => $primary->id,
        ]);
        $this->assertDatabaseHas('companies', [
            'id'             => $primary->id,
            'merged_into_id' => null,
        ]);
    }

    public function test_company_merge_validates_primary_not_in_merge_ids(): void
    {
        $primary   = Company::create(['name' => 'Primary']);
        $secondary = Company::create(['name' => 'Secondary']);

        // primary_id in merge_ids should be silently skipped — only secondary is merged
        $response = $this->post(route('companies.merge'), [
            'primary_id' => $primary->id,
            'merge_ids'  => [$primary->id, $secondary->id],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('companies', ['id' => $primary->id, 'merged_into_id' => null]);
        $this->assertDatabaseHas('companies', ['id' => $secondary->id, 'merged_into_id' => $primary->id]);
    }

    public function test_company_unmerge_clears_merged_into_id(): void
    {
        $primary   = Company::create(['name' => 'Primary']);
        $secondary = Company::create(['name' => 'Secondary', 'merged_into_id' => $primary->id]);

        $response = $this->post(route('companies.unmerge', $secondary));
        $response->assertRedirect();

        $this->assertDatabaseHas('companies', ['id' => $secondary->id, 'merged_into_id' => null]);
    }

    public function test_merged_company_activities_appear_on_primary(): void
    {
        $primary   = Company::create(['name' => 'Primary']);
        $secondary = Company::create(['name' => 'Secondary', 'merged_into_id' => $primary->id]);

        Activity::create([
            'company_id'  => $secondary->id,
            'type'        => 'note',
            'occurred_at' => now(),
            'meta_json'   => ['description' => 'from merged company'],
        ]);

        // Primary company's activities() builder should include merged company activities
        $activityIds = $primary->activities()->pluck('id');
        $this->assertGreaterThan(0, $activityIds->count());
    }

    public function test_company_tab_counts_exclude_merged(): void
    {
        Company::create(['name' => 'A']);
        $primary   = Company::create(['name' => 'B']);
        Company::create(['name' => 'C', 'merged_into_id' => $primary->id]);

        $response = $this->get(route('companies.index'));
        $response->assertStatus(200);

        // 2 non-merged companies (A and B)
        $this->assertEquals(2, Company::notMerged()->count());
    }

    // ── Person merge ─────────────────────────────────────────────────────────

    public function test_people_index_excludes_merged(): void
    {
        $primary = Person::create(['first_name' => 'Jane', 'last_name' => 'Doe']);
        Person::create(['first_name' => 'Jane', 'last_name' => 'Smith', 'merged_into_id' => $primary->id]);

        $response = $this->get(route('people.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('People/Index')
            ->has('people.data', 1)
            ->where('people.data.0.first_name', 'Jane')
            ->where('people.data.0.last_name', 'Doe')
        );
    }

    public function test_person_merge_modal_requires_two_ids(): void
    {
        $person = Person::create(['first_name' => 'Only']);
        $response = $this->get(route('people.merge-modal', ['ids[]' => [$person->id]]));
        $response->assertStatus(400);
    }

    public function test_person_merge_modal_loads_with_two_people(): void
    {
        $a = Person::create(['first_name' => 'Alice', 'last_name' => 'A']);
        $b = Person::create(['first_name' => 'Bob',   'last_name' => 'B']);

        $response = $this->getJson(route('people.merge-modal', ['ids' => [$a->id, $b->id]]));
        $response->assertStatus(200);
        $response->assertJsonFragment(['full_name' => 'Alice A']);
        $response->assertJsonFragment(['full_name' => 'Bob B']);
    }

    public function test_person_merge_sets_merged_into_id(): void
    {
        $primary   = Person::create(['first_name' => 'Jane', 'last_name' => 'Doe']);
        $secondary = Person::create(['first_name' => 'Jane', 'last_name' => 'Smith']);

        $response = $this->post(route('people.merge'), [
            'primary_id' => $primary->id,
            'merge_ids'  => [$secondary->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $this->assertDatabaseHas('people', ['id' => $secondary->id, 'merged_into_id' => $primary->id]);
        $this->assertDatabaseHas('people', ['id' => $primary->id,   'merged_into_id' => null]);
    }

    public function test_person_unmerge_clears_merged_into_id(): void
    {
        $primary   = Person::create(['first_name' => 'Jane', 'last_name' => 'Doe']);
        $secondary = Person::create(['first_name' => 'Jane', 'last_name' => 'Smith', 'merged_into_id' => $primary->id]);

        $response = $this->post(route('people.unmerge', $secondary));
        $response->assertRedirect();

        $this->assertDatabaseHas('people', ['id' => $secondary->id, 'merged_into_id' => null]);
    }

    public function test_person_tab_counts_exclude_merged(): void
    {
        $primary = Person::create(['first_name' => 'Jane']);
        Person::create(['first_name' => 'Alice']);
        Person::create(['first_name' => 'Ghost', 'merged_into_id' => $primary->id]);

        $this->assertEquals(2, Person::notMerged()->where('is_our_org', false)->count());
    }

    public function test_merged_person_not_selectable_in_merge_modal(): void
    {
        $primary   = Person::create(['first_name' => 'Jane']);
        $secondary = Person::create(['first_name' => 'John']);
        $already   = Person::create(['first_name' => 'Ghost', 'merged_into_id' => $primary->id]);

        // Requesting merge modal with an already-merged person + another should abort
        $response = $this->get(route('people.merge-modal', ['ids' => [$secondary->id, $already->id]]));
        $response->assertStatus(400);
    }

    public function test_viewer_cannot_merge_companies(): void
    {
        $this->actingAsViewer();
        $a = Company::create(['name' => 'A']);
        $b = Company::create(['name' => 'B']);

        $this->post(route('companies.merge'), ['primary_id' => $a->id, 'merge_ids' => [$b->id]])
             ->assertStatus(403);
    }

    public function test_viewer_cannot_merge_people(): void
    {
        $this->actingAsViewer();
        $a = Person::create(['first_name' => 'A']);
        $b = Person::create(['first_name' => 'B']);

        $this->post(route('people.merge'), ['primary_id' => $a->id, 'merge_ids' => [$b->id]])
             ->assertStatus(403);
    }
}
