<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Note;
use App\Models\NoteLink;
use App\Models\Person;
use App\Models\SmartNote;
use App\Models\SmartNoteFilter;
use App\Models\SynchronizerServer;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        SynchronizerServer::create([
            'name'          => 'Test',
            'url'           => 'http://localhost:8080',
            'api_token'     => 'token',
            'ingest_secret' => 'secret',
        ]);
    }

    // ── Configuration page ────────────────────────────────────────────────────

    public function test_smart_notes_config_page_returns_200(): void
    {
        $this->get(route('smart-notes.config.index'))->assertStatus(200);
    }

    public function test_can_enable_smart_notes(): void
    {
        $this->post(route('smart-notes.config.settings'), ['enabled' => '1'])
            ->assertRedirect();

        $this->assertTrue((bool) SystemSetting::get('smart_notes_enabled', false));
    }

    public function test_can_disable_smart_notes(): void
    {
        SystemSetting::set('smart_notes_enabled', true);

        $this->post(route('smart-notes.config.settings'), ['enabled' => '0'])
            ->assertRedirect();

        $this->assertFalse((bool) SystemSetting::get('smart_notes_enabled', false));
    }

    public function test_can_create_email_message_filter(): void
    {
        $this->post(route('smart-notes.config.filters.store'), [
            'type'             => 'email_message',
            'mailbox_slugs'    => ['dev6', 'inbox'],
            'address'          => 'notes@example.com',
            'direction'        => 'any',
            'as_internal_note' => '0',
        ])->assertRedirect();

        $filter = SmartNoteFilter::first();
        $this->assertNotNull($filter);
        $this->assertEquals('email_message', $filter->type);
        $this->assertEquals(['dev6', 'inbox'], $filter->criteria['mailbox_slugs']);
        $this->assertEquals('notes@example.com', $filter->criteria['address']);
    }

    public function test_can_create_email_message_filter_without_mailboxes(): void
    {
        $this->post(route('smart-notes.config.filters.store'), [
            'type'    => 'email_message',
            'address' => 'notes@example.com',
        ])->assertRedirect();

        $filter = SmartNoteFilter::first();
        $this->assertNotNull($filter);
        $this->assertEquals([], $filter->criteria['mailbox_slugs']);
        $this->assertEquals('notes@example.com', $filter->criteria['address']);
    }

    public function test_email_message_filter_requires_address(): void
    {
        $this->post(route('smart-notes.config.filters.store'), [
            'type' => 'email_message',
        ])->assertSessionHasErrors('address');
    }

    public function test_can_create_email_subject_filter(): void
    {
        $this->post(route('smart-notes.config.filters.store'), [
            'type'    => 'email_subject',
            'keyword' => 'NOTES',
        ])->assertRedirect();

        $filter = SmartNoteFilter::first();
        $this->assertEquals('email_subject', $filter->type);
        $this->assertEquals('NOTES', $filter->criteria['keyword']);
    }

    public function test_can_create_discord_filter(): void
    {
        $this->post(route('smart-notes.config.filters.store'), [
            'type'            => 'discord_any',
            'connection_slug' => 'my-discord',
        ])->assertRedirect();

        $filter = SmartNoteFilter::first();
        $this->assertEquals('discord_any', $filter->type);
        $this->assertEquals('my-discord', $filter->criteria['connection_slug']);
    }

    public function test_can_delete_filter(): void
    {
        $filter = SmartNoteFilter::create([
            'type'             => 'email_subject',
            'criteria'         => ['keyword' => 'TEST'],
            'as_internal_note' => false,
            'is_active'        => true,
        ]);

        $this->delete(route('smart-notes.config.filters.destroy', $filter))
            ->assertRedirect();

        $this->assertDatabaseMissing('smart_note_filters', ['id' => $filter->id]);
    }

    public function test_filter_validation_fails_without_keyword(): void
    {
        $this->post(route('smart-notes.config.filters.store'), [
            'type' => 'email_subject',
            // keyword missing
        ])->assertSessionHasErrors('keyword');
    }

    // ── Browse Data page ─────────────────────────────────────────────────────

    public function test_smart_notes_index_returns_200(): void
    {
        $this->get(route('smart-notes.index'))->assertStatus(200);
    }

    public function test_smart_notes_index_shows_unrecognized_tab_by_default(): void
    {
        SmartNote::create([
            'source_type'      => 'email',
            'content'          => 'Test note content',
            'status'           => 'unrecognized',
            'as_internal_note' => false,
        ]);

        $this->get(route('smart-notes.index'))
            ->assertStatus(200)
            ->assertSee('Test note content');
    }

    public function test_smart_notes_recognized_tab_shows_recognized(): void
    {
        SmartNote::create([
            'source_type'      => 'email',
            'content'          => 'Recognized note',
            'status'           => 'recognized',
            'as_internal_note' => false,
        ]);

        SmartNote::create([
            'source_type'      => 'email',
            'content'          => 'Unrecognized note',
            'status'           => 'unrecognized',
            'as_internal_note' => false,
        ]);

        $this->get(route('smart-notes.index', ['tab' => 'recognized']))
            ->assertStatus(200)
            ->assertSee('Recognized note')
            ->assertDontSee('Unrecognized note');
    }

    // ── Recognize flow ────────────────────────────────────────────────────────

    public function test_recognize_page_returns_200(): void
    {
        $smartNote = SmartNote::create([
            'source_type' => 'email',
            'content'     => 'Note to recognize',
            'status'      => 'unrecognized',
        ]);

        $this->get(route('smart-notes.recognize', $smartNote))
            ->assertStatus(200)
            ->assertSee('Note to recognize');
    }

    public function test_recognize_redirects_if_already_recognized(): void
    {
        $smartNote = SmartNote::create([
            'source_type' => 'email',
            'content'     => 'Already done',
            'status'      => 'recognized',
        ]);

        $this->get(route('smart-notes.recognize', $smartNote))
            ->assertRedirect(route('smart-notes.index'));
    }

    public function test_can_save_recognition_and_creates_note(): void
    {
        $person = Person::create(['first_name' => 'Jane', 'last_name' => 'Doe']);
        $smartNote = SmartNote::create([
            'source_type'      => 'email',
            'content'          => 'Full content',
            'status'           => 'unrecognized',
            'as_internal_note' => false,
        ]);

        $this->post(route('smart-notes.save-recognition', $smartNote), [
            'segments' => [
                [
                    'content'   => 'Segment one',
                    'assign_to' => 'person',
                    'entity_id' => $person->id,
                ],
            ],
        ])->assertRedirect(route('smart-notes.index'));

        $smartNote->refresh();
        $this->assertEquals('recognized', $smartNote->status);
        $this->assertNotNull($smartNote->segments_json);

        // Note should be created and linked to person
        $note = Note::where('source', 'smart_note')->first();
        $this->assertNotNull($note);
        $this->assertEquals('Segment one', $note->content);

        $link = NoteLink::where('note_id', $note->id)->first();
        $this->assertNotNull($link);
        $this->assertEquals(Person::class, $link->linkable_type);
        $this->assertEquals($person->id, $link->linkable_id);
    }

    public function test_can_save_recognition_assigned_to_company(): void
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $smartNote = SmartNote::create([
            'source_type' => 'email',
            'content'     => 'Company note',
            'status'      => 'unrecognized',
        ]);

        $this->post(route('smart-notes.save-recognition', $smartNote), [
            'segments' => [
                [
                    'content'   => 'Company note content',
                    'assign_to' => 'company',
                    'entity_id' => $company->id,
                ],
            ],
        ])->assertRedirect();

        $link = NoteLink::where('linkable_type', Company::class)->where('linkable_id', $company->id)->first();
        $this->assertNotNull($link);
    }

    public function test_can_save_recognition_without_assignment(): void
    {
        $smartNote = SmartNote::create([
            'source_type' => 'email',
            'content'     => 'Unassigned note',
            'status'      => 'unrecognized',
        ]);

        $this->post(route('smart-notes.save-recognition', $smartNote), [
            'segments' => [
                [
                    'content'   => 'Just text, no entity',
                    'assign_to' => '',
                    'entity_id' => null,
                ],
            ],
        ])->assertRedirect();

        $smartNote->refresh();
        $this->assertEquals('recognized', $smartNote->status);
        // No note should be created since no entity assigned
        $this->assertDatabaseCount('notes', 0);
    }

    public function test_can_unrecognize_smart_note(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('whereJsonContains on meta_json->smart_note_id requires PostgreSQL.');
        }

        $person = Person::create(['first_name' => 'John', 'last_name' => 'Smith']);
        $smartNote = SmartNote::create([
            'source_type'  => 'email',
            'content'      => 'Some note',
            'status'       => 'recognized',
            'segments_json' => [['content' => 'Some note', 'person_id' => $person->id]],
        ]);

        $note = Note::create([
            'content'   => 'Some note',
            'source'    => 'smart_note',
            'meta_json' => ['smart_note_id' => $smartNote->id],
        ]);
        NoteLink::create(['note_id' => $note->id, 'linkable_type' => Person::class, 'linkable_id' => $person->id]);

        $this->post(route('smart-notes.unrecognize', $smartNote))->assertRedirect();

        $smartNote->refresh();
        $this->assertEquals('unrecognized', $smartNote->status);
        $this->assertNull($smartNote->segments_json);
        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_can_delete_smart_note(): void
    {
        $smartNote = SmartNote::create([
            'source_type' => 'email',
            'content'     => 'Delete me',
            'status'      => 'unrecognized',
        ]);

        $this->delete(route('smart-notes.destroy', $smartNote))->assertRedirect();

        $this->assertSoftDeleted('smart_notes', ['id' => $smartNote->id]);
    }

    // ── Scan ─────────────────────────────────────────────────────────────────

    public function test_scan_returns_redirect(): void
    {
        $this->post(route('smart-notes.config.scan'))->assertRedirect();
    }

    public function test_scan_with_no_filters_creates_nothing(): void
    {
        $this->post(route('smart-notes.config.scan'));
        $this->assertDatabaseCount('smart_notes', 0);
    }

    // ── Model labels ─────────────────────────────────────────────────────────

    public function test_smart_note_filter_type_labels(): void
    {
        $types = [
            'email_message' => 'Email Message',
            'email_subject' => 'Email Subject',
            'discord_any'   => 'Discord',
            'slack_any'     => 'Slack',
        ];

        foreach ($types as $type => $expected) {
            $filter = new SmartNoteFilter(['type' => $type, 'criteria' => []]);
            $this->assertEquals($expected, $filter->typeLabel());
        }
    }

    public function test_smart_note_source_label(): void
    {
        $labels = ['email' => 'Email', 'discord' => 'Discord', 'slack' => 'Slack', 'ticket' => 'Ticket'];
        foreach ($labels as $type => $expected) {
            $note = new SmartNote(['source_type' => $type]);
            $this->assertEquals($expected, $note->sourceLabel());
        }
    }
}
