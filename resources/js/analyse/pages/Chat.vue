<template>
  <div class="flex h-full overflow-hidden">
    <AnalyseSidebar
      :sidebar="sidebar"
      :active-chat-id="activeChatId"
      :users="users"
      @chat-created="onChatCreated"
    />

    <main class="flex flex-1 flex-col min-w-0 h-full overflow-hidden">
      <!-- Chat header / top bar -->
      <div class="flex items-center gap-3 px-5 py-3 border-b border-gray-800 shrink-0 lg:pl-5 pl-14">
        <div class="flex-1 min-w-0">
          <!-- Project breadcrumb -->
          <div v-if="chat.project_id && projectName" class="text-xs text-gray-600 mb-0.5">
            <a :href="`/analyse/p/${chat.project_id}`" class="hover:text-gray-400">{{ projectName }}</a>
            <span class="mx-1">/</span>
          </div>

          <div class="flex items-center gap-2">
            <h1
              class="text-sm font-medium text-gray-200 truncate cursor-pointer hover:text-white"
              :title="chat.title"
              @dblclick="startRename"
            >
              <span v-if="!renaming">{{ chat.title }}</span>
              <input
                v-else
                ref="renameInput"
                v-model="renameValue"
                class="bg-transparent border-b border-brand-500 outline-none text-white w-full"
                @blur="commitRename"
                @keydown.enter.prevent="commitRename"
                @keydown.escape="renaming = false"
              />
            </h1>

            <!-- Badges -->
            <span v-if="chat.is_shared" class="text-xs bg-blue-500/20 text-blue-300 px-1.5 py-0.5 rounded-full">shared</span>
            <span v-if="!chat.is_owner" class="text-xs bg-gray-700 text-gray-400 px-1.5 py-0.5 rounded-full">guest</span>
          </div>

          <div v-if="chat.source_chat_id" class="text-xs text-gray-500 mt-0.5">
            Branched conversation
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <!-- Move to project (owner only) -->
          <button
            v-if="chat.is_owner"
            class="text-xs text-gray-500 hover:text-gray-300 px-2 py-1 rounded"
            @click="projectPanel = !projectPanel"
          >
            Project
          </button>

          <!-- Share button (owner only) -->
          <button
            v-if="chat.is_owner"
            class="text-xs text-gray-400 hover:text-gray-200 px-2 py-1 rounded"
            @click="sharePanel = !sharePanel"
          >
            {{ chat.is_shared ? 'Shared' : 'Share' }}
          </button>

          <!-- Archive -->
          <button
            v-if="chat.is_owner"
            class="text-xs text-gray-500 hover:text-gray-300 px-2 py-1 rounded"
            :title="chat.is_archived ? 'Unarchive' : 'Archive'"
            @click="toggleArchive"
          >
            {{ chat.is_archived ? 'Unarchive' : 'Archive' }}
          </button>

          <!-- Delete -->
          <button
            v-if="chat.is_owner"
            class="text-xs text-gray-500 hover:text-red-400 px-2 py-1 rounded"
            @click="deleteChat"
          >
            Delete
          </button>

          <!-- Leave (participant) -->
          <button
            v-if="!chat.is_owner"
            class="text-xs text-gray-500 hover:text-red-400 px-2 py-1 rounded"
            @click="leaveChat"
          >
            Leave
          </button>
        </div>
      </div>

      <!-- Project assignment panel -->
      <div v-if="projectPanel && chat.is_owner" class="border-b border-gray-800 bg-gray-900 shrink-0">
        <div class="max-w-3xl mx-auto px-5 py-3">
          <div class="text-xs text-gray-400 mb-2 font-medium">Assign to project</div>
          <div class="flex gap-2">
            <select v-model="selectedProjectId" class="bg-gray-800 border border-gray-700 text-gray-200 text-xs rounded px-2 py-1 flex-1">
              <option :value="null">No project</option>
              <option v-for="p in sidebar.projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <button class="btn btn-primary btn-sm text-xs" @click="assignProject">Save</button>
          </div>
        </div>
      </div>

      <!-- Share panel -->
      <div v-if="sharePanel && chat.is_owner" class="border-b border-gray-800 bg-gray-900 shrink-0">
        <div class="max-w-3xl mx-auto px-5 py-3">
          <div class="text-xs text-gray-400 mb-2 font-medium">Share with team member</div>
          <div class="flex gap-2 mb-3">
            <select v-model="shareUserId" class="bg-gray-800 border border-gray-700 text-gray-200 text-xs rounded px-2 py-1 flex-1">
              <option value="">Select user...</option>
              <option v-for="u in availableUsers" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
            </select>
            <button class="btn btn-primary btn-sm text-xs" @click="addParticipant">Add</button>
          </div>
          <div v-if="localParticipants.length" class="flex flex-col gap-1">
            <div
              v-for="p in localParticipants"
              :key="p.user_id"
              class="flex items-center justify-between text-xs text-gray-300"
            >
              <span>{{ p.name }} <span class="text-gray-500">({{ p.email }})</span></span>
              <button
                class="text-gray-500 hover:text-red-400 ml-2"
                @click="removeParticipant(p.user_id)"
              >Remove</button>
            </div>
          </div>
          <div v-else class="text-xs text-gray-600 italic">No participants yet.</div>
        </div>
      </div>

      <!-- Messages area -->
      <div ref="messagesEl" class="flex-1 overflow-y-auto min-h-0">
        <div class="max-w-3xl mx-auto px-5 py-4 space-y-4">
          <template v-if="localMessages.length === 0">
            <div class="flex items-center justify-center py-32 text-gray-600 text-sm italic">
              Send a message to start the conversation.
            </div>
          </template>

          <ChatMessage
            v-for="msg in localMessages"
            :key="msg.id"
            :message="msg"
            :streaming-id="streamingMessageId"
            :streaming-content="streamingContent"
            :is-shared-chat="chat.is_shared"
            :current-user-id="currentUserId"
            @branch="branchFromMessage"
            @retry="retryMessage"
            @edit="editMessage"
          />

          <!-- Thinking indicator -->
          <div v-if="isStreaming && !streamingMessageId" class="flex gap-3">
            <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 bg-gray-700 text-gray-200 mt-0.5">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-xs text-gray-500 mb-1">Assistant</div>
              <div class="bg-gray-800/70 border border-gray-700/50 rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-2xl">
                <div class="flex gap-1 items-center">
                  <span class="w-1.5 h-1.5 rounded-full bg-gray-500 animate-bounce" style="animation-delay: 0ms" />
                  <span class="w-1.5 h-1.5 rounded-full bg-gray-500 animate-bounce" style="animation-delay: 150ms" />
                  <span class="w-1.5 h-1.5 rounded-full bg-gray-500 animate-bounce" style="animation-delay: 300ms" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Input area (pinned to bottom) -->
      <div class="shrink-0 border-t border-gray-800">
        <div class="max-w-3xl mx-auto px-5 py-4">
          <div v-if="chat.is_archived" class="text-center text-sm text-gray-500 italic py-2">
            This conversation is archived. Unarchive it to send messages.
          </div>
          <template v-else>
            <div class="flex gap-3 items-end">
              <textarea
                ref="inputEl"
                v-model="inputText"
                rows="1"
                placeholder="Message..."
                class="flex-1 bg-gray-800 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-gray-200 placeholder-gray-600 resize-none focus:outline-none focus:border-brand-600 transition-colors"
                style="min-height: 42px; max-height: 200px; overflow-y: auto;"
                @keydown.enter.exact.prevent="sendMessage"
                @keydown.enter.shift.exact="null"
                @input="autoResize"
              />
              <button
                v-if="!isStreaming"
                class="btn btn-primary shrink-0 px-4 py-2.5 text-sm"
                :disabled="!inputText.trim()"
                @click="sendMessage"
              >
                Send
              </button>
              <button
                v-else
                class="btn btn-secondary shrink-0 px-4 py-2.5 text-sm"
                @click="stopStreaming"
              >
                Stop
              </button>
            </div>
            <div class="text-xs text-gray-600 mt-1.5">
              Enter to send &middot; Shift+Enter for new line
            </div>
          </template>
        </div>
      </div>
    </main>

    <!-- Delete confirmation -->
    <Teleport to="body">
      <div v-if="deleteConfirm" class="fixed inset-0 flex items-center justify-center z-50 bg-black/40" @click.self="deleteConfirm = false">
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-5 w-72 shadow-xl">
          <h3 class="text-sm font-semibold text-gray-200 mb-2">Delete conversation?</h3>
          <p class="text-xs text-gray-400 mb-4">This cannot be undone.</p>
          <div class="flex gap-2 justify-end">
            <button class="btn btn-secondary btn-sm text-xs" @click="deleteConfirm = false">Cancel</button>
            <button class="btn btn-sm text-xs bg-red-600 hover:bg-red-500 text-white border-red-600" @click="confirmDelete">Delete</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AnalyseSidebar from '../components/AnalyseSidebar.vue'
import ChatMessage from '../components/ChatMessage.vue'

const props = defineProps({
  chat: Object,
  analyseEnabled: Boolean,
  activeChatId: Number,
  sidebar: Object,
  users: Array,
})

const page = usePage()
const currentUserId = computed(() => page.props.auth?.user?.id ?? null)

// ── Local state ──────────────────────────────────────────────────────────────
const localMessages = ref([...(props.chat.messages ?? [])])
const localParticipants = ref([...(props.chat.participants ?? [])])
const inputText = ref('')
const isStreaming = ref(false)
const streamingMessageId = ref(null)
const streamingContent = ref('')
const messagesEl = ref(null)
const inputEl = ref(null)
const renaming = ref(false)
const renameValue = ref(props.chat.title)
const renameInput = ref(null)
const sharePanel = ref(false)
const shareUserId = ref('')
const projectPanel = ref(false)
const selectedProjectId = ref(props.chat.project_id)
const deleteConfirm = ref(false)

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? ''

const projectName = computed(() => {
  if (!props.chat.project_id) return null
  const p = props.sidebar.projects.find(p => p.id === props.chat.project_id)
  return p?.name ?? null
})

const availableUsers = computed(() => {
  const participantIds = localParticipants.value.map(p => p.user_id)
  return (props.users ?? []).filter(u => !participantIds.includes(u.id))
})

// ── Echo channel ─────────────────────────────────────────────────────────────
let channel = null

onMounted(() => {
  scrollToBottom()
  subscribeChannel()
})

onUnmounted(() => {
  if (channel) window.Echo?.leave?.(`chat.${props.chat.id}`)
})

watch(() => props.chat.id, (newId, oldId) => {
  if (channel && oldId) window.Echo?.leave?.(`chat.${oldId}`)
  localMessages.value = [...(props.chat.messages ?? [])]
  localParticipants.value = [...(props.chat.participants ?? [])]
  streamingContent.value = ''
  streamingMessageId.value = null
  isStreaming.value = false
  selectedProjectId.value = props.chat.project_id
  subscribeChannel()
  nextTick(scrollToBottom)
})

function subscribeChannel() {
  if (!window.Echo) return
  channel = window.Echo.private(`chat.${props.chat.id}`)
    .listen('.AiMessageChunk', (e) => {
      if (!isStreaming.value) {
        isStreaming.value = true
        streamingMessageId.value = e.messageId
        streamingContent.value = ''
      }
      streamingContent.value += e.chunk
      nextTick(scrollToBottom)
    })
    .listen('.AiMessageComplete', (e) => {
      isStreaming.value = false
      const idx = localMessages.value.findIndex(m => m.id === e.messageId)
      if (idx !== -1) {
        localMessages.value[idx] = { ...localMessages.value[idx], content: e.content }
      } else {
        localMessages.value.push({
          id: e.messageId,
          role: 'assistant',
          content: e.content,
          created_at: new Date().toISOString(),
        })
      }
      streamingContent.value = ''
      streamingMessageId.value = null
      nextTick(scrollToBottom)
    })
    .listen('.ChatTitleGenerated', (e) => {
      document.title = `${e.title} — Analyse`
    })
    .listen('.UserMessageAdded', (e) => {
      if (!localMessages.value.find(m => m.id === e.message.id)) {
        localMessages.value.push(e.message)
        nextTick(scrollToBottom)
      }
    })
    .listen('.ParticipantUpdated', (e) => {
      localParticipants.value = e.participants
    })
}

// ── Actions ──────────────────────────────────────────────────────────────────
async function sendMessage() {
  const content = inputText.value.trim()
  if (!content || isStreaming.value) return

  inputText.value = ''
  nextTick(() => { if (inputEl.value) inputEl.value.style.height = '42px' })

  const tempId = Date.now()
  localMessages.value.push({
    id: tempId,
    role: 'user',
    content,
    created_at: new Date().toISOString(),
    meta: { user_name: 'You', user_id: currentUserId.value },
  })
  nextTick(scrollToBottom)

  isStreaming.value = true

  try {
    const res = await fetch(`/analyse/chats/${props.chat.id}/messages`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify({ content }),
    })
    const data = await res.json()
    if (!res.ok) {
      isStreaming.value = false
      showError(data.error ?? 'Failed to send message.')
    } else if (data.message) {
      // HTTP fallback: if WebSocket didn't deliver the message, add it from the response
      isStreaming.value = false
      streamingContent.value = ''
      streamingMessageId.value = null
      const exists = localMessages.value.find(m => m.id === data.message.id)
      if (!exists) {
        localMessages.value.push(data.message)
        nextTick(scrollToBottom)
      }
    }
  } catch (e) {
    isStreaming.value = false
    showError('Network error: ' + (e.message || 'unknown'))
  }
}

async function stopStreaming() {
  await fetch(`/analyse/chats/${props.chat.id}/stop`, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': csrfToken() },
  })
}

async function toggleArchive() {
  const res = await fetch(`/analyse/chats/${props.chat.id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    body: JSON.stringify({ is_archived: !props.chat.is_archived }),
  })
  if (res.ok) router.reload()
}

function deleteChat() {
  deleteConfirm.value = true
}

async function confirmDelete() {
  deleteConfirm.value = false
  await fetch(`/analyse/chats/${props.chat.id}`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': csrfToken() },
  })
  router.visit('/analyse')
}

async function leaveChat() {
  const res = await fetch(`/analyse/chats/${props.chat.id}/leave`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': csrfToken() },
  })
  if (res.ok) router.visit('/analyse')
}

async function branchFromMessage(messageId) {
  const res = await fetch(`/analyse/chats/${props.chat.id}/branch`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    body: JSON.stringify({ message_id: messageId }),
  })
  const data = await res.json()
  if (data.chat) router.visit(`/analyse/c/${data.chat.id}`)
}

async function retryMessage(messageId) {
  // In shared chats, auto-branch before retrying
  if (props.chat.is_shared && !props.chat.is_owner) {
    const res = await fetch(`/analyse/chats/${props.chat.id}/branch`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify({ message_id: messageId }),
    })
    const data = await res.json()
    if (data.chat) router.visit(`/analyse/c/${data.chat.id}`)
    return
  }

  // Find the user message before this assistant message and resend it
  const msgIdx = localMessages.value.findIndex(m => m.id === messageId)
  if (msgIdx < 0) return

  // Find preceding user message
  let userContent = ''
  for (let i = msgIdx - 1; i >= 0; i--) {
    if (localMessages.value[i].role === 'user') {
      userContent = localMessages.value[i].content
      break
    }
  }
  if (!userContent) return

  // Remove the assistant message and anything after
  localMessages.value = localMessages.value.slice(0, msgIdx)

  isStreaming.value = true
  try {
    const res = await fetch(`/analyse/chats/${props.chat.id}/messages`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify({ content: userContent }),
    })
    const data = await res.json()
    if (!res.ok) {
      isStreaming.value = false
      showError(data.error ?? 'Failed to retry.')
    } else if (data.message) {
      isStreaming.value = false
      streamingContent.value = ''
      streamingMessageId.value = null
      if (!localMessages.value.find(m => m.id === data.message.id)) {
        localMessages.value.push(data.message)
        nextTick(scrollToBottom)
      }
    }
  } catch {
    isStreaming.value = false
  }
}

async function editMessage({ messageId, content }) {
  if (!content) return

  // In shared chats, auto-branch
  if (props.chat.is_shared && !props.chat.is_owner) {
    const res = await fetch(`/analyse/chats/${props.chat.id}/branch`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify({ message_id: messageId }),
    })
    const data = await res.json()
    if (data.chat) router.visit(`/analyse/c/${data.chat.id}`)
    return
  }

  // Remove this message and everything after it, then re-send with new content
  const msgIdx = localMessages.value.findIndex(m => m.id === messageId)
  if (msgIdx < 0) return

  localMessages.value = localMessages.value.slice(0, msgIdx)

  // Add edited user message optimistically
  localMessages.value.push({
    id: Date.now(),
    role: 'user',
    content,
    created_at: new Date().toISOString(),
    meta: { user_name: 'You', user_id: currentUserId.value },
  })
  nextTick(scrollToBottom)

  isStreaming.value = true
  try {
    const res = await fetch(`/analyse/chats/${props.chat.id}/messages`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify({ content }),
    })
    const data = await res.json()
    if (!res.ok) {
      isStreaming.value = false
      showError(data.error ?? 'Failed to send.')
    } else if (data.message) {
      isStreaming.value = false
      streamingContent.value = ''
      streamingMessageId.value = null
      if (!localMessages.value.find(m => m.id === data.message.id)) {
        localMessages.value.push(data.message)
        nextTick(scrollToBottom)
      }
    }
  } catch {
    isStreaming.value = false
  }
}

async function addParticipant() {
  if (!shareUserId.value) return
  const res = await fetch(`/analyse/chats/${props.chat.id}/share`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    body: JSON.stringify({ user_id: shareUserId.value }),
  })
  const data = await res.json()
  if (data.participants) {
    localParticipants.value = data.participants
    shareUserId.value = ''
  }
}

async function removeParticipant(userId) {
  const res = await fetch(`/analyse/chats/${props.chat.id}/participants/${userId}`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': csrfToken() },
  })
  const data = await res.json()
  if (data.participants) localParticipants.value = data.participants
}

async function assignProject() {
  await fetch(`/analyse/chats/${props.chat.id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    body: JSON.stringify({ project_id: selectedProjectId.value }),
  })
  projectPanel.value = false
  router.reload()
}

function startRename() {
  renameValue.value = props.chat.title
  renaming.value = true
  nextTick(() => renameInput.value?.focus())
}

async function commitRename() {
  renaming.value = false
  const newTitle = renameValue.value.trim()
  if (!newTitle || newTitle === props.chat.title) return

  await fetch(`/analyse/chats/${props.chat.id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    body: JSON.stringify({ title: newTitle }),
  })
  router.reload({ only: ['chat'] })
}

function onChatCreated(chat) {
  router.visit(`/analyse/c/${chat.id}`)
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function showError(message) {
  localMessages.value.push({
    id: Date.now(),
    role: 'system_event',
    content: 'Error: ' + message,
    created_at: new Date().toISOString(),
    _isError: true,
  })
  nextTick(scrollToBottom)
}

function scrollToBottom() {
  if (messagesEl.value) {
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight
  }
}

function autoResize(e) {
  const el = e.target
  el.style.height = 'auto'
  el.style.height = Math.min(el.scrollHeight, 200) + 'px'
}
</script>
