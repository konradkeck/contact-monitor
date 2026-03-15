@props(['notes', 'linkableType', 'linkableId'])

<div>
    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">Notes</p>
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl overflow-hidden shadow-sm">
        @if($notes->isEmpty())
            <p class="px-4 py-3 text-sm text-yellow-600 italic">No notes yet.</p>
        @else
            <ul class="divide-y divide-yellow-100 max-h-72 overflow-y-auto">
                @foreach($notes as $note)
                    <li class="px-4 py-3 flex gap-3 items-start">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-yellow-900 leading-snug whitespace-pre-wrap">{{ $note->content }}</p>
                            <p class="text-xs text-yellow-500 mt-1"
                               title="{{ $note->created_at->format('D, j M Y \a\t H:i') }}">
                                {{ $note->created_at->diffForHumans() }}
                                @if($note->user) · {{ $note->user->name }} @endif
                            </p>
                        </div>
                        @can('notes_write')
                        <form action="{{ route('notes.destroy', $note) }}" method="POST"
                              class="shrink-0" onsubmit="return confirm('Delete this note?')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="text-yellow-300 hover:text-red-500 transition text-lg leading-none"
                                    title="Delete note">&times;</button>
                        </form>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif
        @can('notes_write')
        <div class="px-4 py-3 border-t border-yellow-200">
            <form action="{{ route('notes.store') }}" method="POST">
                @csrf
                <input type="hidden" name="linkable_type" value="{{ $linkableType }}">
                <input type="hidden" name="linkable_id" value="{{ $linkableId }}">
                <textarea name="content" rows="2" placeholder="Add a note…"
                          class="w-full bg-white border border-yellow-200 rounded-lg px-3 py-2 text-sm
                                 placeholder-yellow-300 text-gray-700 resize-none focus:outline-none
                                 focus:ring-2 focus:ring-yellow-300"></textarea>
                <button type="submit"
                        class="mt-2 w-full py-1.5 bg-yellow-400 hover:bg-yellow-500 text-yellow-900
                               font-semibold text-xs rounded-lg transition">+ Add note</button>
            </form>
        </div>
        @endcan
    </div>
</div>
