<template>
  <div>
    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">Notes</p>
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl overflow-hidden shadow-sm">
      <template v-if="!localNotes.length">
        <p class="px-4 py-3 text-sm text-yellow-600 italic">No notes yet.</p>
      </template>
      <template v-else>
        <ul class="divide-y divide-yellow-100 max-h-72 overflow-y-auto">
          <li v-for="note in localNotes" :key="note.id" class="px-4 py-3 flex gap-3 items-start">
            <div class="flex-1 min-w-0">
              <p class="text-sm text-yellow-900 leading-snug whitespace-pre-wrap">{{ note.content }}</p>
              <p class="text-xs text-yellow-500 mt-1" :title="formatDate(note.created_at)">
                {{ timeAgo(note.created_at) }}
                <template v-if="note.user_name"> · {{ note.user_name }}</template>
              </p>
            </div>
            <button
              v-if="canWrite"
              type="button"
              @click="deleteNote(note.id)"
              class="shrink-0 text-yellow-300 hover:text-red-500 transition text-lg leading-none"
              title="Delete note"
            >&times;</button>
          </li>
        </ul>
      </template>
      <div v-if="canWrite" class="px-4 py-3 border-t border-yellow-200">
        <form @submit.prevent="addNote">
          <textarea
            v-model="newContent"
            rows="2"
            placeholder="Add a note..."
            class="w-full bg-white border border-yellow-200 rounded-lg px-3 py-2 text-sm
                   placeholder-yellow-300 text-gray-700 resize-none focus:outline-none
                   focus:ring-2 focus:ring-yellow-300"
          />
          <button
            type="submit"
            :disabled="!newContent.trim() || saving"
            class="mt-2 w-full py-1.5 bg-yellow-400 hover:bg-yellow-500 text-yellow-900
                   font-semibold text-xs rounded-lg transition disabled:opacity-50"
          >+ Add note</button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  notes: { type: Array, default: () => [] },
  linkableType: { type: String, required: true },
  linkableId: { type: [Number, String], required: true },
})

const page = usePage()
const canWrite = computed(() => page.props.auth?.permissions?.notes_write)

const localNotes = ref([...props.notes])
const newContent = ref('')
const saving = ref(false)

function addNote() {
  if (!newContent.value.trim() || saving.value) return
  saving.value = true

  router.post('/notes', {
    linkable_type: props.linkableType,
    linkable_id: props.linkableId,
    content: newContent.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      newContent.value = ''
      // Reload page to get fresh notes data
      router.reload({ only: ['notes'] })
    },
    onFinish: () => { saving.value = false },
  })
}

function deleteNote(noteId) {
  if (!confirm('Delete this note?')) return

  router.delete(`/notes/${noteId}`, {
    preserveScroll: true,
    onSuccess: () => {
      localNotes.value = localNotes.value.filter(n => n.id !== noteId)
    },
  })
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function timeAgo(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  const now = new Date()
  const seconds = Math.floor((now - d) / 1000)
  if (seconds < 60) return 'just now'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  if (days < 30) return `${days}d ago`
  const months = Math.floor(days / 30)
  if (months < 12) return `${months}mo ago`
  return `${Math.floor(months / 12)}y ago`
}
</script>
