<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Note;
use App\Models\NoteLink;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        abort_if(! auth()->user()->can('notes_write'), 403);

        $data = $request->validate([
            'content' => 'required|string',
            'linkable_type' => 'required|in:company,person,conversation',
            'linkable_id' => 'required|integer',
            'source' => 'nullable|in:manual,email_ingest,ai',
        ]);

        $typeMap = [
            'company' => Company::class,
            'person' => Person::class,
            'conversation' => \App\Models\Conversation::class,
        ];

        $note = Note::create([
            'user_id' => auth()->id(),
            'content' => $data['content'],
            'source' => $data['source'] ?? 'manual',
        ]);

        NoteLink::create([
            'note_id' => $note->id,
            'linkable_type' => $typeMap[$data['linkable_type']],
            'linkable_id' => $data['linkable_id'],
        ]);

        // Record audit log based on entity type
        $entity = ($typeMap[$data['linkable_type']])::find($data['linkable_id']);
        if ($entity) {
            AuditLog::record('added_note', $entity, 'Added note to '.class_basename($entity)." #{$entity->id}");
        }

        // Redirect back to the entity that was noted
        $redirect = match ($data['linkable_type']) {
            'company' => route('companies.show', $data['linkable_id']),
            'person' => route('people.show', $data['linkable_id']),
            'conversation' => route('conversations.show', $data['linkable_id']),
            default => route('dashboard'),
        };

        if ($request->wantsJson()) {
            return response()->json([
                'ok'   => true,
                'note' => [
                    'id'      => $note->id,
                    'content' => $note->content,
                    'user'    => $note->user?->name,
                ],
            ]);
        }

        return redirect($redirect)->with('success', 'Note added.');
    }

    public function destroy(Request $request, Note $note): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        abort_if(! auth()->user()->can('notes_write'), 403);

        $link = $note->links()->first();
        $note->delete();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        // Redirect back to entity
        if ($link) {
            $redirect = match (class_basename($link->linkable_type)) {
                'Company'      => route('companies.show', $link->linkable_id),
                'Person'       => route('people.show', $link->linkable_id),
                'Conversation' => route('conversations.show', $link->linkable_id),
                default        => route('dashboard'),
            };
            return redirect($redirect)->with('success', 'Note deleted.');
        }

        return redirect()->back()->with('success', 'Note deleted.');
    }
}
