<template>
  <!-- System event -->
  <div v-if="message.role === 'system_event'" class="flex justify-center py-1">
    <div
      class="text-xs italic px-3 py-1 rounded-full"
      :class="message._isError ? 'text-red-400 bg-red-950/50 border border-red-800/40' : 'text-gray-600 bg-gray-900/50'"
    >{{ message.content }}</div>
  </div>

  <!-- User / Assistant message -->
  <div
    v-else
    class="group flex gap-3"
    :class="message.role === 'user' ? 'flex-row-reverse' : ''"
  >
    <!-- Avatar -->
    <div
      class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 text-xs font-bold mt-0.5"
      :class="message.role === 'user' ? 'bg-brand-700 text-white' : 'bg-gray-700 text-gray-200'"
    >
      <template v-if="message.role === 'user'">{{ userInitial }}</template>
      <template v-else>
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
        </svg>
      </template>
    </div>

    <!-- Content -->
    <div class="flex-1 min-w-0" :class="message.role === 'user' ? 'items-end flex flex-col' : ''">
      <!-- Author line -->
      <div class="text-xs text-gray-500 mb-1">
        <template v-if="message.role === 'user'">
          {{ authorName }}
        </template>
        <template v-else>Assistant</template>
        <span class="ml-2">{{ formatTime(message.created_at) }}</span>
      </div>

      <!-- Editing mode -->
      <template v-if="editing">
        <div class="max-w-xl w-full">
          <textarea
            ref="editInput"
            v-model="editContent"
            class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-gray-200 resize-none focus:outline-none focus:border-brand-600"
            rows="3"
          />
          <div class="flex gap-2 mt-1">
            <button class="btn btn-primary btn-sm text-xs" @click="submitEdit">Save & resubmit</button>
            <button class="btn btn-secondary btn-sm text-xs" @click="cancelEdit">Cancel</button>
          </div>
        </div>
      </template>

      <!-- User message (not editing) -->
      <template v-else-if="message.role === 'user'">
        <div
          class="bg-brand-900/60 border border-brand-800/50 rounded-2xl rounded-tr-sm px-4 py-2.5 text-sm text-gray-200 max-w-xl whitespace-pre-wrap break-words"
        >{{ message.content }}</div>
      </template>

      <!-- Assistant message (streaming or complete) -->
      <template v-else>
        <div class="max-w-2xl bg-gray-800/70 border border-gray-700/50 rounded-2xl rounded-tl-sm px-4 py-2.5">
          <template v-if="message.id === streamingId && streamingContent">
            <div class="text-sm text-gray-200 whitespace-pre-wrap break-words">
              {{ streamingContent }}<span class="inline-block w-1.5 h-3.5 bg-brand-400 ml-0.5 animate-pulse align-middle" />
            </div>
          </template>
          <template v-else>
            <div class="prose-ai" v-html="renderedMarkdown" />
          </template>
        </div>
      </template>

      <!-- Hover actions -->
      <div
        v-if="!editing"
        class="opacity-0 group-hover:opacity-100 transition-opacity mt-1 flex gap-2"
        :class="message.role === 'user' ? 'justify-end' : ''"
      >
        <button
          class="text-xs text-gray-600 hover:text-gray-400"
          @click="copyContent"
        >Copy</button>
        <button
          v-if="message.role === 'user' && canEdit"
          class="text-xs text-gray-600 hover:text-gray-400"
          @click="startEdit"
        >Edit</button>
        <button
          v-if="message.role === 'assistant'"
          class="text-xs text-gray-600 hover:text-gray-400"
          @click="$emit('retry', message.id)"
        >Retry</button>
        <button
          class="text-xs text-gray-600 hover:text-gray-400"
          @click="$emit('branch', message.id)"
        >Branch</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { marked } from 'marked'

// Configure marked for safe rendering
marked.setOptions({
  gfm: true,
  breaks: true,
})

const props = defineProps({
  message: Object,
  streamingId: { type: Number, default: null },
  streamingContent: { type: String, default: '' },
  isSharedChat: { type: Boolean, default: false },
  currentUserId: { type: Number, default: null },
})

const emit = defineEmits(['branch', 'retry', 'edit'])

const editing = ref(false)
const editContent = ref('')
const editInput = ref(null)

const userInitial = computed(() => {
  const name = props.message.meta?.user_name ?? 'U'
  return name[0]?.toUpperCase() ?? 'U'
})

const authorName = computed(() => {
  return props.message.meta?.user_name || 'You'
})

const canEdit = computed(() => {
  // Can edit own messages only
  if (!props.currentUserId) return true // fallback: allow
  return props.message.meta?.user_id === props.currentUserId
})

const renderedMarkdown = computed(() => {
  if (!props.message.content) return ''
  try {
    return marked.parse(props.message.content)
  } catch {
    return `<pre>${escapeHtml(props.message.content)}</pre>`
  }
})

function escapeHtml(str) {
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

function formatTime(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
}

function copyContent() {
  navigator.clipboard.writeText(props.message.content).catch(() => {})
}

function startEdit() {
  editContent.value = props.message.content
  editing.value = true
  nextTick(() => editInput.value?.focus())
}

function cancelEdit() {
  editing.value = false
}

function submitEdit() {
  editing.value = false
  emit('edit', { messageId: props.message.id, content: editContent.value.trim() })
}
</script>

<style>
/* Markdown rendering styles for AI responses — plain CSS (Tailwind v4 doesn't support @apply in Vue SFC) */
.prose-ai {
  font-size: 0.875rem;
  line-height: 1.5;
  color: #e5e7eb;
  overflow-wrap: break-word;
}
.prose-ai p {
  margin-bottom: 0.5rem;
  line-height: 1.625;
}
.prose-ai p:last-child { margin-bottom: 0; }
.prose-ai h1, .prose-ai h2, .prose-ai h3, .prose-ai h4 {
  font-weight: 600;
  color: #f3f4f6;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
}
.prose-ai h1 { font-size: 1.125rem; }
.prose-ai h2 { font-size: 1rem; }
.prose-ai h3 { font-size: 0.875rem; }
.prose-ai ul, .prose-ai ol {
  padding-left: 1.25rem;
  margin-bottom: 0.5rem;
}
.prose-ai ul { list-style-type: disc; }
.prose-ai ol { list-style-type: decimal; }
.prose-ai li { line-height: 1.625; margin-bottom: 0.25rem; }
.prose-ai code {
  background: #1f2937;
  color: var(--color-brand-300, #f0abcf);
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-family: ui-monospace, monospace;
}
.prose-ai pre {
  background: #111827;
  border: 1px solid #374151;
  border-radius: 0.5rem;
  padding: 0.75rem;
  margin-bottom: 0.5rem;
  overflow-x: auto;
}
.prose-ai pre code {
  background: transparent;
  color: #e5e7eb;
  padding: 0;
}
.prose-ai blockquote {
  border-left: 2px solid #4b5563;
  padding-left: 0.75rem;
  color: #9ca3af;
  font-style: italic;
  margin-bottom: 0.5rem;
}
.prose-ai a {
  color: var(--color-brand-400, #e05694);
  text-decoration: underline;
}
.prose-ai a:hover { color: var(--color-brand-300, #f0abcf); }
.prose-ai table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 0.5rem;
}
.prose-ai th, .prose-ai td {
  border: 1px solid #374151;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
}
.prose-ai th {
  background: #1f2937;
  font-weight: 600;
}
.prose-ai hr {
  border-color: #374151;
  margin: 0.75rem 0;
}
.prose-ai strong { font-weight: 600; color: #f3f4f6; }
.prose-ai em { font-style: italic; }
</style>
