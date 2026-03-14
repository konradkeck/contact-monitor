<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Person;
use App\Models\SynchronizerServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotesTest extends TestCase
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

    public function test_store_note_for_company(): void
    {
        $this->createServer();

        $company = Company::create(['name' => 'Note Target Co']);

        $response = $this->post(route('notes.store'), [
            'content' => 'This is a company note.',
            'linkable_type' => 'company',
            'linkable_id' => $company->id,
        ]);

        $response->assertRedirect(route('companies.show', $company->id));
        $this->assertDatabaseHas('notes', ['content' => 'This is a company note.']);
        $this->assertDatabaseHas('note_links', [
            'linkable_type' => Company::class,
            'linkable_id' => $company->id,
        ]);
    }

    public function test_store_note_for_person(): void
    {
        $this->createServer();

        $person = Person::create(['first_name' => 'John', 'last_name' => 'Smith', 'is_our_org' => false]);

        $response = $this->post(route('notes.store'), [
            'content' => 'Person note content.',
            'linkable_type' => 'person',
            'linkable_id' => $person->id,
        ]);

        $response->assertRedirect(route('people.show', $person->id));
        $this->assertDatabaseHas('notes', ['content' => 'Person note content.']);
    }

    public function test_store_note_for_conversation(): void
    {
        $this->createServer();

        $conv = Conversation::create([
            'channel_type' => 'email',
            'system_type' => 'imap',
            'system_slug' => 'default',
            'subject' => 'Convo for note',
            'external_thread_id' => 'note-conv-1',
        ]);

        $response = $this->post(route('notes.store'), [
            'content' => 'Conversation note.',
            'linkable_type' => 'conversation',
            'linkable_id' => $conv->id,
        ]);

        $response->assertRedirect(route('conversations.show', $conv->id));
        $this->assertDatabaseHas('notes', ['content' => 'Conversation note.']);
    }

    public function test_store_note_validation_fails_without_content(): void
    {
        $this->createServer();

        $company = Company::create(['name' => 'Validation Co']);

        $response = $this->post(route('notes.store'), [
            'linkable_type' => 'company',
            'linkable_id' => $company->id,
        ]);

        $response->assertSessionHasErrors('content');
    }

    public function test_store_note_validation_fails_with_invalid_linkable_type(): void
    {
        $this->createServer();

        $response = $this->post(route('notes.store'), [
            'content' => 'Some note',
            'linkable_type' => 'invalid_type',
            'linkable_id' => 1,
        ]);

        $response->assertSessionHasErrors('linkable_type');
    }
}
