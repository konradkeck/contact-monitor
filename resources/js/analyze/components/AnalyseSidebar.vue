<template>
  <!-- New conversation button -->
  <div class="flex items-center justify-between px-3 py-2.5 border-b border-gray-800">
    <span class="sidebar-section !py-0 !mb-0">Analyze</span>
    <button
      class="text-xs text-gray-400 hover:text-white px-2 py-1 rounded hover:bg-gray-800"
      title="New conversation"
      @click="createChat"
    >
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
    </button>
  </div>

  <!-- Search -->
  <SearchBar />

  <!-- Scrollable content -->
  <div class="flex-1 overflow-y-auto py-2">

    <!-- Shared with me -->
    <template v-if="sidebar.shared.length">
      <div class="sidebar-section px-4 py-1">Shared with me</div>
      <SharedRow
        v-for="chat in sidebar.shared"
        :key="'shared-' + chat.id"
        :chat="chat"
        :is-active="chat.id === activeChatId"
        @action="handleSharedAction"
      />
    </template>

    <!-- Projects section -->
    <div class="mb-1">
      <div class="sidebar-section px-4 py-1 flex items-center justify-between">
        <span>Projects</span>
        <button class="text-gray-600 hover:text-gray-400" title="New project" @click="createProject">
          <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
          </svg>
        </button>
      </div>
      <template v-if="sidebar.projects.length">
        <ProjectRow
          v-for="p in sidebar.projects"
          :key="p.id"
          :project="p"
          @action="handleProjectAction"
        />
      </template>
      <div v-else class="px-4 py-1">
        <span class="text-xs text-gray-600 italic">No projects yet.</span>
      </div>
    </div>

    <!-- My conversations -->
    <div class="sidebar-section px-4 py-1">Conversations</div>

    <template v-if="localChats.length === 0 && !localNextCursor">
      <div class="px-4 py-2 text-xs text-gray-600 italic">No conversations yet.</div>
    </template>

    <ConversationRow
      v-for="chat in localChats"
      :key="chat.id"
      :chat="chat"
      :is-active="chat.id === activeChatId"
      @action="handleChatAction"
    />

    <!-- Load more -->
    <div v-if="localNextCursor" class="px-4 py-1">
      <button
        class="text-xs text-gray-600 hover:text-gray-400"
        :disabled="loadingMore"
        @click="loadMore"
      >{{ loadingMore ? 'Loading...' : 'Load more' }}</button>
    </div>
  </div>

  <!-- New project modal -->
  <Teleport to="body">
    <div v-if="newProjectModal" class="fixed inset-0 flex items-center justify-center z-50 modal-overlay" @click.self="newProjectModal = false">
      <div class="bg-gray-800 border border-gray-700 rounded-xl p-5 w-72 shadow-xl">
        <h3 class="text-sm font-semibold text-gray-200 mb-3">New Project</h3>
        <input
          ref="newProjectInput"
          v-model="newProjectName"
          type="text"
          placeholder="Project name..."
          class="input w-full text-sm mb-3"
          @keydown.enter.prevent="submitNewProject"
        />
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary btn-sm text-xs" @click="newProjectModal = false">Cancel</button>
          <button class="btn btn-primary btn-sm text-xs" @click="submitNewProject">Create</button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Rename modal -->
  <Teleport to="body">
    <div v-if="renameModal" class="fixed inset-0 flex items-center justify-center z-50 modal-overlay" @click.self="renameModal = false">
      <div class="bg-gray-800 border border-gray-700 rounded-xl p-5 w-72 shadow-xl">
        <h3 class="text-sm font-semibold text-gray-200 mb-3">Rename</h3>
        <input
          ref="renameInput"
          v-model="renameValue"
          type="text"
          class="input w-full text-sm mb-3"
          @keydown.enter.prevent="submitRename"
        />
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary btn-sm text-xs" @click="renameModal = false">Cancel</button>
          <button class="btn btn-primary btn-sm text-xs" @click="submitRename">Save</button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Move to project modal -->
  <Teleport to="body">
    <div v-if="moveModal" class="fixed inset-0 flex items-center justify-center z-50 modal-overlay" @click.self="moveModal = false">
      <div class="bg-gray-800 border border-gray-700 rounded-xl p-5 w-72 shadow-xl">
        <h3 class="text-sm font-semibold text-gray-200 mb-3">Move to project</h3>
        <select v-model="moveProjectId" class="input w-full text-sm mb-3">
          <option value="">No project</option>
          <option v-for="p in sidebar.projects" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary btn-sm text-xs" @click="moveModal = false">Cancel</button>
          <button class="btn btn-primary btn-sm text-xs" @click="submitMove">Move</button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Share modal -->
  <Teleport to="body">
    <div v-if="shareModal" class="fixed inset-0 flex items-center justify-center z-50 modal-overlay" @click.self="shareModal = false">
      <div class="bg-gray-800 border border-gray-700 rounded-xl p-5 w-80 shadow-xl">
        <h3 class="text-sm font-semibold text-gray-200 mb-3">Share conversation</h3>
        <div class="flex gap-2 mb-3">
          <select v-model="shareUserId" class="input flex-1 text-sm">
            <option value="">Select user...</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
          </select>
          <button class="btn btn-primary btn-sm text-xs" @click="submitShare">Share</button>
        </div>
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary btn-sm text-xs" @click="shareModal = false">Done</button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Confirm delete modal -->
  <Teleport to="body">
    <div v-if="deleteModal" class="fixed inset-0 flex items-center justify-center z-50 modal-overlay" @click.self="deleteModal = false">
      <div class="bg-gray-800 border border-gray-700 rounded-xl p-5 w-72 shadow-xl">
        <h3 class="text-sm font-semibold text-gray-200 mb-2">Confirm delete</h3>
        <p class="text-xs text-gray-400 mb-4">Are you sure you want to delete "{{ deleteTarget?.title || deleteTarget?.name }}"? This cannot be undone.</p>
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary btn-sm text-xs" @click="deleteModal = false">Cancel</button>
          <button class="btn btn-sm text-xs bg-red-600 hover:bg-red-500 text-white border-red-600" @click="confirmDelete">Delete</button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Pin to project modal (for shared chats) -->
  <Teleport to="body">
    <div v-if="pinModal" class="fixed inset-0 flex items-center justify-center z-50 modal-overlay" @click.self="pinModal = false">
      <div class="bg-gray-800 border border-gray-700 rounded-xl p-5 w-72 shadow-xl">
        <h3 class="text-sm font-semibold text-gray-200 mb-3">Add to project</h3>
        <select v-model="pinProjectId" class="input w-full text-sm mb-3">
          <option value="">Select project...</option>
          <option v-for="p in sidebar.projects" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary btn-sm text-xs" @click="pinModal = false">Cancel</button>
          <button class="btn btn-primary btn-sm text-xs" :disabled="!pinProjectId" @click="submitPin">Add</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import SearchBar from './sidebar/SearchBar.vue'
import ConversationRow from './sidebar/ConversationRow.vue'
import ProjectRow from './sidebar/ProjectRow.vue'
import SharedRow from './sidebar/SharedRow.vue'

const props = defineProps({
  sidebar: Object,
  activeChatId: { type: Number, default: null },
  users: Array,
})

const emit = defineEmits(['chat-created'])

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? ''

// Scroll / load more
const loadingMore = ref(false)
const localChats = ref([...(props.sidebar.chats ?? [])])
const localNextCursor = ref(props.sidebar.nextCursor ?? null)

// New project modal
const newProjectModal = ref(false)
const newProjectName = ref('')
const newProjectInput = ref(null)

// Rename modal
const renameModal = ref(false)
const renameValue = ref('')
const renameTarget = ref(null)
const renameInput = ref(null)

// Move to project modal
const moveModal = ref(false)
const moveProjectId = ref('')
const moveChatId = ref(null)

// Share modal
const shareModal = ref(false)
const shareUserId = ref('')
const shareChatId = ref(null)

// Delete confirmation
const deleteModal = ref(false)
const deleteTarget = ref(null)

// Pin to project (shared chats)
const pinModal = ref(false)
const pinProjectId = ref('')
const pinChatId = ref(null)

// ── Chat actions ───────────────────────────────────────────────────────────

async function createChat() {
  const res = await fetch('/analyze/chats', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    body: JSON.stringify({}),
  })
  const data = await res.json()
  if (data.chat) {
    emit('chat-created', data.chat)
    router.visit(`/analyze/c/${data.chat.id}`)
  }
}

function handleChatAction({ action, chat }) {
  if (action === 'rename') {
    renameTarget.value = { type: 'chat', id: chat.id }
    renameValue.value = chat.title || ''
    renameModal.value = true
    nextTick(() => renameInput.value?.focus())
  } else if (action === 'move') {
    moveChatId.value = chat.id
    moveProjectId.value = chat.project_id || ''
    moveModal.value = true
  } else if (action === 'share') {
    shareChatId.value = chat.id
    shareUserId.value = ''
    shareModal.value = true
  } else if (action === 'archive') {
    toggleArchive(chat)
  } else if (action === 'delete') {
    deleteTarget.value = { type: 'chat', id: chat.id, title: chat.title }
    deleteModal.value = true
  }
}

async function toggleArchive(chat) {
  await fetch(`/analyze/chats/${chat.id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    body: JSON.stringify({ is_archived: !chat.is_archived }),
  })
  router.reload()
}

// ── Project actions ────────────────────────────────────────────────────────

function createProject() {
  newProjectName.value = ''
  newProjectModal.value = true
  nextTick(() => newProjectInput.value?.focus())
}

async function submitNewProject() {
  const name = newProjectName.value.trim()
  if (!name) return
  await fetch('/analyze/projects', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    body: JSON.stringify({ name }),
  })
  newProjectModal.value = false
  router.reload()
}

function handleProjectAction({ action, project }) {
  if (action === 'rename') {
    renameTarget.value = { type: 'project', id: project.id }
    renameValue.value = project.name || ''
    renameModal.value = true
    nextTick(() => renameInput.value?.focus())
  } else if (action === 'delete') {
    deleteTarget.value = { type: 'project', id: project.id, name: project.name }
    deleteModal.value = true
  }
}

// ── Shared chat actions ────────────────────────────────────────────────────

function handleSharedAction({ action, chat }) {
  if (action === 'add-to-project') {
    pinChatId.value = chat.id
    pinProjectId.value = ''
    pinModal.value = true
  } else if (action === 'branch') {
    branchSharedToPrivate(chat)
  } else if (action === 'leave') {
    leaveSharedChat(chat)
  }
}

async function branchSharedToPrivate(chat) {
  const res = await fetch(`/analyze/chats/${chat.id}/branch`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    body: JSON.stringify({}),
  })
  const data = await res.json()
  if (data.chat) router.visit(`/analyze/c/${data.chat.id}`)
}

async function leaveSharedChat(chat) {
  await fetch(`/analyze/chats/${chat.id}/leave`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': csrfToken() },
  })
  router.reload()
}

// ── Rename ─────────────────────────────────────────────────────────────────

async function submitRename() {
  const val = renameValue.value.trim()
  if (!val || !renameTarget.value) return

  const t = renameTarget.value
  const url = t.type === 'chat' ? `/analyze/chats/${t.id}` : `/analyze/projects/${t.id}`
  const body = t.type === 'chat' ? { title: val } : { name: val }

  await fetch(url, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    body: JSON.stringify(body),
  })
  renameModal.value = false
  router.reload()
}

// ── Move to project ────────────────────────────────────────────────────────

async function submitMove() {
  if (!moveChatId.value) return
  await fetch(`/analyze/chats/${moveChatId.value}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    body: JSON.stringify({ project_id: moveProjectId.value || null }),
  })
  moveModal.value = false
  router.reload()
}

// ── Share ──────────────────────────────────────────────────────────────────

async function submitShare() {
  if (!shareUserId.value || !shareChatId.value) return
  await fetch(`/analyze/chats/${shareChatId.value}/share`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    body: JSON.stringify({ user_id: shareUserId.value }),
  })
  shareUserId.value = ''
  shareModal.value = false
  router.reload()
}

// ── Delete ─────────────────────────────────────────────────────────────────

async function confirmDelete() {
  if (!deleteTarget.value) return
  const t = deleteTarget.value
  const url = t.type === 'chat' ? `/analyze/chats/${t.id}` : `/analyze/projects/${t.id}`
  await fetch(url, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': csrfToken() },
  })
  deleteModal.value = false
  if (t.type === 'chat' && t.id === props.activeChatId) {
    router.visit('/analyze')
  } else {
    router.reload()
  }
}

// ── Pin shared chat to project ─────────────────────────────────────────────

async function submitPin() {
  if (!pinProjectId.value || !pinChatId.value) return
  await fetch(`/analyze/projects/${pinProjectId.value}/pin-chat`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    body: JSON.stringify({ chat_id: pinChatId.value }),
  })
  pinModal.value = false
  router.reload()
}

// ── Load more ──────────────────────────────────────────────────────────────

async function loadMore() {
  if (!localNextCursor.value || loadingMore.value) return
  loadingMore.value = true
  const res = await fetch(`/analyze/chats?cursor=${encodeURIComponent(localNextCursor.value)}`)
  const data = await res.json()
  localChats.value.push(...(data.chats ?? []))
  localNextCursor.value = data.nextCursor ?? null
  loadingMore.value = false
}
</script>
