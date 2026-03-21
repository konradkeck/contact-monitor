<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Note;
use App\Models\NoteLink;
use App\Models\Person;
use App\Models\SmartNote;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SmartNotesController extends Controller
{
    public function index(Request $request)
    {
        $tab               = $request->input('tab', 'unrecognized');
        $smartNotesEnabled = SystemSetting::get('smart_notes_enabled', false);
        $unrecognizedCount = SmartNote::unrecognized()->count();
        $recognizedCount   = SmartNote::recognized()->count();

        if ($tab === 'recognized') {
            $notes = SmartNote::recognized()
                ->with('filter')
                ->orderByDesc('occurred_at')
                ->paginate(25);
        } else {
            $notes = SmartNote::unrecognized()
                ->with('filter')
                ->orderByDesc('occurred_at')
                ->paginate(25);
        }

        $notes->getCollection()->transform(function ($note) {
            $note->source_label = $note->sourceLabel();
            $note->filter_type_label = $note->filter?->typeLabel();
            $note->occurred_at_formatted = $note->occurred_at?->format('d M Y');
            return $note;
        });

        return Inertia::render('SmartNotes/Index', [
            'tab' => $tab,
            'smartNotesEnabled' => $smartNotesEnabled,
            'unrecognizedCount' => $unrecognizedCount,
            'recognizedCount' => $recognizedCount,
            'notes' => $notes,
        ]);
    }

    public function recognize(SmartNote $smartNote)
    {
        if ($smartNote->status === 'recognized') {
            return redirect()->route('smart-notes.index')->with('info', 'Already recognized.');
        }

        if ($smartNote->segments_json === null) {
            $smartNote->segments_json = [
                [
                    'content'      => $smartNote->content,
                    'company_id'   => null,
                    'person_id'    => null,
                    'note_id'      => null,
                    'company_name' => null,
                    'person_name'  => null,
                ],
            ];
        }

        $smartNote->source_label = $smartNote->sourceLabel();
        $smartNote->occurred_at_formatted = $smartNote->occurred_at?->format('d M Y H:i');

        $companies = Company::notMerged()->orderBy('name')->get(['id', 'name']);
        $people    = Person::notMerged()->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => trim($p->first_name . ' ' . $p->last_name)]);

        return Inertia::render('SmartNotes/Recognize', [
            'smartNote' => $smartNote,
            'companies' => $companies,
            'people' => $people,
        ]);
    }

    public function saveRecognition(Request $request, SmartNote $smartNote): RedirectResponse
    {
        $request->validate([
            'segments'              => ['required', 'array', 'min:1'],
            'segments.*.content'    => ['required', 'string'],
            'segments.*.assign_to'  => ['nullable', 'in:company,person'],
            'segments.*.entity_id'  => ['nullable', 'integer'],
        ]);

        $segments  = $request->input('segments', []);
        $savedSegs = [];

        foreach ($segments as $seg) {
            $assignTo = $seg['assign_to'] ?? null;
            $entityId = ! empty($seg['entity_id']) ? (int) $seg['entity_id'] : null;
            $noteId   = null;

            if ($assignTo && $entityId) {
                $note = Note::create([
                    'user_id'   => Auth::id(),
                    'content'   => $seg['content'],
                    'source'    => 'smart_note',
                    'meta_json' => [
                        'as_internal_note' => $smartNote->as_internal_note,
                        'smart_note_id'    => $smartNote->id,
                    ],
                ]);

                $linkableType = $assignTo === 'company' ? Company::class : Person::class;

                NoteLink::create([
                    'note_id'       => $note->id,
                    'linkable_type' => $linkableType,
                    'linkable_id'   => $entityId,
                ]);

                $noteId = $note->id;
            }

            $savedSegs[] = [
                'content'      => $seg['content'],
                'company_id'   => $assignTo === 'company' ? $entityId : null,
                'person_id'    => $assignTo === 'person' ? $entityId : null,
                'note_id'      => $noteId,
                'company_name' => null,
                'person_name'  => null,
            ];
        }

        $smartNote->segments_json = $savedSegs;
        $smartNote->status        = 'recognized';
        $smartNote->save();

        return redirect()->route('smart-notes.index')->with('success', 'Smart Note recognized and notes created.');
    }

    public function destroy(SmartNote $smartNote): RedirectResponse
    {
        $smartNote->delete();

        return back()->with('success', 'Smart Note deleted.');
    }

    public function unrecognize(SmartNote $smartNote): RedirectResponse
    {
        // Delete any notes previously created from this smart note
        if ($smartNote->status === 'recognized') {
            $noteIds = Note::whereJsonContains('meta_json->smart_note_id', $smartNote->id)->pluck('id');

            if ($noteIds->isNotEmpty()) {
                NoteLink::whereIn('note_id', $noteIds)->delete();
                Note::whereIn('id', $noteIds)->forceDelete();
            }
        }

        $smartNote->status        = 'unrecognized';
        $smartNote->segments_json = null;
        $smartNote->save();

        return back()->with('success', 'Smart Note moved back to unrecognized.');
    }
}
