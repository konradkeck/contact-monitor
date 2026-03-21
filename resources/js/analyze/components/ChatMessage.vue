<template>
  <!-- System event -->
  <div v-if="message.role === 'system_event'" class="flex justify-center py-1">
    <div
      class="text-xs italic px-3 py-1 rounded-full"
      :class="message._isError ? 'text-red-600 bg-red-50 border border-red-200' : 'text-gray-500 bg-gray-100'"
    >{{ message.content }}</div>
  </div>

  <!-- User / Assistant message -->
  <div
    v-else
    class="group flex gap-3"
    :class="message.role === 'user' ? 'flex-row-reverse' : ''"
  >
    <!-- Avatar -->
    <template v-if="message.role === 'user'">
      <img
        :src="userGravatarUrl"
        class="w-7 h-7 rounded-full shrink-0 mt-0.5"
        alt=""
      />
    </template>
    <template v-else>
      <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 bg-gray-200 mt-0.5">
        <img :src="'/ai-icon.svg'" class="w-4 h-4" alt="">
      </div>
    </template>

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
            class="w-full input rounded-xl px-4 py-2.5 text-sm resize-none"
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
          class="bg-brand-50 border border-brand-200 rounded-2xl rounded-tr-sm px-4 py-2.5 text-sm text-gray-800 max-w-xl whitespace-pre-wrap break-words"
        >{{ message.content }}</div>
      </template>

      <!-- Assistant message (streaming or complete) -->
      <template v-else>
        <div class="max-w-2xl bg-white border border-gray-200 rounded-2xl rounded-tl-sm px-4 py-2.5 shadow-sm">
          <template v-if="message.id === streamingId && streamingContent">
            <div class="text-sm text-gray-700 whitespace-pre-wrap break-words">
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
          class="text-xs text-gray-400 hover:text-gray-600"
          @click="copyContent"
        >Copy</button>
        <button
          v-if="message.role === 'user' && canEdit"
          class="text-xs text-gray-400 hover:text-gray-600"
          @click="startEdit"
        >Edit</button>
        <button
          v-if="message.role === 'assistant'"
          class="text-xs text-gray-400 hover:text-gray-600"
          @click="$emit('retry', message.id)"
        >Retry</button>
        <button
          class="text-xs text-gray-400 hover:text-gray-600"
          @click="$emit('branch', message.id)"
        >Branch</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { marked } from 'marked'
import md5 from '../../utils/md5.js'

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
  userEmail: { type: String, default: '' },
})

const emit = defineEmits(['branch', 'retry', 'edit'])

const editing = ref(false)
const editContent = ref('')
const editInput = ref(null)

const userGravatarUrl = computed(() => {
  const email = props.message.meta?.user_email || props.userEmail || ''
  const hash = md5(email.trim().toLowerCase())
  return `https://www.gravatar.com/avatar/${hash}?s=32&d=mp`
})

const authorName = computed(() => {
  return props.message.meta?.user_name || 'You'
})

const canEdit = computed(() => {
  if (!props.currentUserId) return true
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
/* Markdown rendering styles for AI responses — light theme */
.prose-ai {
  font-size: 0.875rem;
  line-height: 1.5;
  color: #374151;
  overflow-wrap: break-word;
}
.prose-ai p {
  margin-bottom: 0.5rem;
  line-height: 1.625;
}
.prose-ai p:last-child { margin-bottom: 0; }
.prose-ai h1, .prose-ai h2, .prose-ai h3, .prose-ai h4 {
  font-weight: 600;
  color: #111827;
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
  background: #f3f4f6;
  color: var(--color-brand-700, #8b004a);
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-family: ui-monospace, monospace;
}
.prose-ai pre {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 0.75rem;
  margin-bottom: 0.5rem;
  overflow-x: auto;
}
.prose-ai pre code {
  background: transparent;
  color: #374151;
  padding: 0;
}
.prose-ai blockquote {
  border-left: 2px solid #d1d5db;
  padding-left: 0.75rem;
  color: #6b7280;
  font-style: italic;
  margin-bottom: 0.5rem;
}
.prose-ai a {
  color: var(--color-brand-600, #a40057);
  text-decoration: underline;
}
.prose-ai a:hover { color: var(--color-brand-500, #c4006a); }
.prose-ai table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 0.5rem;
}
.prose-ai th, .prose-ai td {
  border: 1px solid #e5e7eb;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
}
.prose-ai th {
  background: #f9fafb;
  font-weight: 600;
}
.prose-ai hr {
  border-color: #e5e7eb;
  margin: 0.75rem 0;
}
.prose-ai strong { font-weight: 600; color: #111827; }
.prose-ai em { font-style: italic; }
</style>
