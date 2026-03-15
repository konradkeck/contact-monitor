{{-- Popup (fixed — unaffected by parent overflow:hidden or form context) --}}
<div id="{{ $popupId }}"
     class="fixed inset-0 z-50 hidden"
     onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="absolute inset-0 bg-black/20"></div>
    <div class="absolute modal-center bg-yellow-50 border border-yellow-200 rounded-xl shadow-xl w-80 max-h-[480px] overflow-hidden flex flex-col"
         onclick="event.stopPropagation()">

        <div class="px-4 py-3 border-b border-yellow-200 flex items-center justify-between shrink-0">
            <span class="text-sm font-semibold text-yellow-800">
                Notes{{ $entityName ? ' — '.$entityName : '' }}
            </span>
            <button type="button"
                    onclick="document.getElementById('{{ $popupId }}').classList.add('hidden')"
                    class="text-yellow-400 hover:text-yellow-700 text-xl leading-none">×</button>
        </div>

        <ul id="{{ $popupId }}-list" class="divide-y divide-yellow-100 overflow-y-auto flex-1">
            @forelse($notes as $note)
                <li class="px-4 py-3 group/note" data-note-id="{{ $note->id }}">
                    <p class="text-sm text-yellow-900 leading-snug">{{ $note->content }}</p>
                    <div class="flex items-center justify-between mt-1">
                        <p class="text-xs text-yellow-500"
                           title="{{ $note->created_at->format('D, j M Y \a\t H:i') }}">
                            {{ $note->created_at->diffForHumans() }}
                            @if($note->user) · {{ $note->user->name }} @endif
                        </p>
                        @can('notes_write')
                        <button type="button"
                                onclick="npDeleteNote('{{ $popupId }}', {{ $note->id }})"
                                class="text-[10px] text-yellow-400 hover:text-red-500 transition opacity-0 group-hover/note:opacity-100">
                            Delete
                        </button>
                        @endcan
                    </div>
                </li>
            @empty
                <li id="{{ $popupId }}-empty" class="px-4 py-3 text-sm text-yellow-600 italic">No notes yet.</li>
            @endforelse
        </ul>

        @can('notes_write')
        <div class="px-4 py-3 border-t border-yellow-100 shrink-0">
            <textarea id="{{ $popupId }}-input" rows="2" placeholder="Add a note…"
                      class="w-full bg-white border border-yellow-200 rounded-lg px-3 py-2 text-sm
                             placeholder-yellow-300 text-gray-700 resize-none focus:outline-none
                             focus:ring-2 focus:ring-yellow-300"></textarea>
            <button type="button"
                    onclick="npSubmitNote('{{ $popupId }}', '{{ $linkableType }}', {{ $linkableId }})"
                    class="mt-2 w-full py-1.5 bg-yellow-400 hover:bg-yellow-500 text-yellow-900
                           font-semibold text-xs rounded-lg transition">+ Add note</button>
        </div>
        @endcan

    </div>
</div>

{{-- Trigger: type="button" prevents form submission when inside a <form> --}}
<button type="button"
        onclick="document.getElementById('{{ $popupId }}').classList.remove('hidden')"
        class="shrink-0 transition leading-none cursor-pointer {{ $hasNotes ? 'text-amber-400 hover:text-amber-500' : 'text-orange-200 hover:text-orange-400' }}"
        title="{{ $hasNotes ? $notes->count().' note(s)' : 'Add note' }}">
    @if($hasNotes)
        <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>
            <path d="M8 9a1 1 0 000 2h4a1 1 0 100-2H8zM8 12a1 1 0 000 2h4a1 1 0 100-2H8z"/>
        </svg>
    @else
        <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>
        </svg>
    @endif
</button>

<script>
function npSubmitNote(popupId, linkableType, linkableId) {
    var input = document.getElementById(popupId + '-input');
    var content = input.value.trim();
    if (!content) return;

    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('{{ route('notes.store') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ content: content, linkable_type: linkableType, linkable_id: linkableId }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            // Remove empty state if present
            var empty = document.getElementById(popupId + '-empty');
            if (empty) empty.remove();

            // Append new note to list
            var list = document.getElementById(popupId + '-list');
            var li = document.createElement('li');
            li.className = 'px-4 py-3 group/note';
            li.dataset.noteId = data.note.id;
            li.innerHTML = '<p class="text-sm text-yellow-900 leading-snug">' + npEsc(data.note.content) + '</p>' +
                '<div class="flex items-center justify-between mt-1">' +
                '<p class="text-xs text-yellow-500">just now' + (data.note.user ? ' · ' + npEsc(data.note.user) : '') + '</p>' +
                '<button type="button" onclick="npDeleteNote(\'' + popupId + '\', ' + data.note.id + ')" ' +
                'class="text-[10px] text-yellow-400 hover:text-red-500 transition opacity-0 group-hover/note:opacity-100">Delete</button>' +
                '</div>';
            list.appendChild(li);
            input.value = '';
        }
    })
    .catch(() => {});
}

function npDeleteNote(popupId, noteId) {
    if (!confirm('Delete this note?')) return;
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('/notes/' + noteId, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            var li = document.querySelector('[data-note-id="' + noteId + '"]');
            if (li) li.remove();
            var list = document.getElementById(popupId + '-list');
            if (list && list.children.length === 0) {
                var empty = document.createElement('li');
                empty.id = popupId + '-empty';
                empty.className = 'px-4 py-3 text-sm text-yellow-600 italic';
                empty.textContent = 'No notes yet.';
                list.appendChild(empty);
            }
        }
    })
    .catch(() => {});
}

function npEsc(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
