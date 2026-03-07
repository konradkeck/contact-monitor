@props(['notes', 'linkableType', 'linkableId'])

<div class="bg-white rounded-lg border border-gray-200">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800 text-sm">Notes</h3>
        <span class="text-xs text-gray-400">{{ $notes->count() }}</span>
    </div>

    @if($notes->isEmpty())
        <p class="px-4 py-3 text-sm text-gray-400 italic">No notes yet.</p>
    @else
        <ul class="divide-y divide-gray-50">
            @foreach($notes as $note)
                <li class="px-4 py-3">
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $note->content }}</p>
                    <p class="text-xs text-gray-400 mt-1">
                        <x-badge color="gray">{{ $note->source }}</x-badge>
                        {{ $note->created_at->diffForHumans() }}
                    </p>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="px-4 py-3 border-t border-gray-100 bg-gray-50 rounded-b-lg">
        <form action="{{ route('notes.store') }}" method="POST">
            @csrf
            <input type="hidden" name="linkable_type" value="{{ $linkableType }}">
            <input type="hidden" name="linkable_id" value="{{ $linkableId }}">
            <textarea name="content" rows="2" placeholder="Add a note…"
                      class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500 resize-none"></textarea>
            <div class="flex justify-end mt-2">
                <button type="submit" class="px-3 py-1.5 bg-brand-600 text-white text-xs rounded hover:bg-brand-700 transition">
                    Add Note
                </button>
            </div>
        </form>
    </div>
</div>
