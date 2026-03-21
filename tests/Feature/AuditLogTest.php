<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\SynchronizerServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditLogTest extends TestCase
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

    public function test_audit_log_index_returns_200(): void
    {
        $this->createServer();

        $response = $this->get(route('audit-log.index'));

        $response->assertStatus(200);
    }

    public function test_audit_log_index_empty(): void
    {
        $this->createServer();

        $response = $this->get(route('audit-log.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('AuditLog')
            ->has('logs')
        );
    }

    public function test_audit_log_index_shows_log_entry(): void
    {
        $this->createServer();

        $company = Company::create(['name' => 'Logged Company']);

        AuditLog::record('created', $company, 'Created company: Logged Company', ['name' => 'Logged Company']);

        $response = $this->get(route('audit-log.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('AuditLog')
            ->has('logs.data', 1)
        );
    }

    public function test_audit_log_index_paginated(): void
    {
        $this->createServer();

        $company = Company::create(['name' => 'Paged Co']);

        for ($i = 0; $i < 5; $i++) {
            AuditLog::record('updated', $company, "Update #{$i}", []);
        }

        $response = $this->get(route('audit-log.index'));

        $response->assertStatus(200);
    }
}
