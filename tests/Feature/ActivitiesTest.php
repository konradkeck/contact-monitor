<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Company;
use App\Models\SynchronizerServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActivitiesTest extends TestCase
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

    public function test_activity_index_returns_200(): void
    {
        $this->createServer();

        $response = $this->get(route('activity.index'));

        $response->assertStatus(200);
    }

    public function test_activity_index_empty(): void
    {
        $this->createServer();

        $response = $this->get(route('activity.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Activity/Index')
            ->has('convSystems')
            ->has('activityTypes')
        );
    }

    public function test_activity_index_shows_activity(): void
    {
        $this->createServer();

        $company = Company::create(['name' => 'Activity Co']);

        Activity::create([
            'company_id' => $company->id,
            'type' => 'note',
            'occurred_at' => now(),
        ]);

        $response = $this->get(route('activity.index'));

        $response->assertStatus(200);
    }

    public function test_activity_timeline_returns_200(): void
    {
        $this->createServer();

        $response = $this->get(route('activity.timeline'));

        $response->assertStatus(200);
    }

    public function test_activity_timeline_with_cursor_pagination(): void
    {
        $this->createServer();

        $company = Company::create(['name' => 'Timeline Co']);

        // Create more than 1 page worth of activities
        for ($i = 0; $i < 5; $i++) {
            Activity::create([
                'company_id' => $company->id,
                'type' => 'note',
                'occurred_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->get(route('activity.timeline'));

        $response->assertStatus(200);
    }

    public function test_activity_timeline_excludes_type(): void
    {
        $this->createServer();

        $response = $this->get(route('activity.timeline', ['exclude_type' => 'conversation']));

        $response->assertStatus(200);
    }

    public function test_activity_timeline_search(): void
    {
        $this->createServer();

        $company = Company::create(['name' => 'Search Target Co']);

        Activity::create([
            'company_id'  => $company->id,
            'type'        => 'note',
            'occurred_at' => now(),
            'meta_json'   => ['description' => 'important meeting notes'],
        ]);

        $response = $this->get(route('activity.timeline', ['q' => 'meeting']));

        $response->assertStatus(200);
    }

    public function test_activity_timeline_search_empty_query(): void
    {
        $this->createServer();

        // Empty search should not crash
        $response = $this->get(route('activity.timeline', ['q' => '']));

        $response->assertStatus(200);
    }

    public function test_activity_stats_returns_200(): void
    {
        $this->createServer();

        $response = $this->get(route('activity.stats'));

        $response->assertStatus(200);
    }
}
