<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\SynchronizerServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PeopleTest extends TestCase
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

    public function test_people_index_returns_200(): void
    {
        $this->createServer();

        $response = $this->get(route('people.index'));

        $response->assertStatus(200);
    }

    public function test_people_index_empty(): void
    {
        $this->createServer();

        $response = $this->get(route('people.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('People/Index')
            ->has('people')
        );
    }

    public function test_people_index_shows_person(): void
    {
        $this->createServer();

        Person::create(['first_name' => 'John', 'last_name' => 'Doe', 'is_our_org' => false]);

        $response = $this->get(route('people.index'));

        $response->assertStatus(200);
        $response->assertSee('John');
        $response->assertSee('Doe');
    }

    public function test_people_index_excludes_our_org(): void
    {
        $this->createServer();

        Person::create(['first_name' => 'Internal', 'last_name' => 'Staff', 'is_our_org' => true]);
        Person::create(['first_name' => 'External', 'last_name' => 'Client', 'is_our_org' => false]);

        $response = $this->get(route('people.index'));

        $response->assertStatus(200);
        $response->assertSee('External');
        $response->assertDontSee('Internal');
    }

    public function test_people_show_returns_404_for_nonexistent(): void
    {
        $this->createServer();

        $response = $this->get(route('people.show', 99999));

        $response->assertStatus(404);
    }
}
