<template>
  <AppLayout>
    <template #sidebar>
      <AnalyseSidebar
        :sidebar="analyseSidebar"
        :active-chat-id="null"
        :users="users"
      />
    </template>

    <div class="p-6">
      <div class="max-w-2xl">
        <!-- Header -->
        <div class="flex items-center gap-3 mb-6">
          <div v-if="!renaming" class="flex items-center gap-2">
            <h1 class="text-xl font-semibold text-gray-800">{{ project.name }}</h1>
            <button
              class="text-gray-400 hover:text-gray-600 text-xs px-2 py-1 rounded"
              @click="startRename"
            >Rename</button>
          </div>
          <div v-else class="flex items-center gap-2 flex-1">
            <input
              ref="renameInput"
              v-model="renameValue"
              class="input text-sm flex-1"
              @blur="commitRename"
              @keydown.enter.prevent="commitRename"
              @keydown.escape="renaming = false"
            />
            <button class="btn btn-primary btn-sm text-xs" @click="commitRename">Save</button>
            <button class="btn btn-secondary btn-sm text-xs" @click="renaming = false">Cancel</button>
          </div>

          <button
            class="ml-auto text-xs text-red-500 hover:text-red-400"
            @click="deleteProject"
          >Delete project</button>
        </div>

        <!-- Chats in project -->
        <div class="card-xl-overflow">
          <div class="card-header">
            <span class="section-header-title">Conversations ({{ project.chats.length }})</span>
            <button class="btn btn-sm btn-primary text-xs" @click="newChat">New conversation</button>
          </div>
          <table class="w-full text-sm">
            <thead class="tbl-header">
              <tr>
                <th class="px-4 py-2.5 text-left">Title</th>
                <th class="px-4 py-2.5 text-left">Last message</th>
                <th class="px-4 py-2.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="chat in project.chats"
                :key="chat.id"
                class="tbl-row cursor-pointer"
                @click="openChat(chat.id)"
              >
                <td class="px-4 py-3">
                  {{ chat.title }}
                  <span v-if="chat.pinned" class="ml-1 text-xs text-amber-500" title="Pinned">&#9733;</span>
                  <span v-if="chat.is_shared" class="ml-1 badge badge-blue text-xs">shared</span>
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs">
                  {{ formatDate(chat.last_message_at) }}
                </td>
                <td class="px-4 py-3 text-right" @click.stop>
                  <button
                    v-if="chat.pinned"
                    class="row-action text-xs"
                    @click="unpin(chat.id)"
                  >Unpin</button>
                </td>
              </tr>
              <tr v-if="project.chats.length === 0">
                <td colspan="3" class="px-4 py-8 text-center empty-state italic">
                  No conversations yet.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Delete confirmation -->
    <Teleport to="body">
      <div v-if="confirmDeleteModal" class="fixed inset-0 flex items-center justify-center z-50 bg-black/40" @click.self="confirmDeleteModal = false">
        <div class="bg-white border border-gray-200 rounded-xl p-5 w-72 shadow-xl">
          <h3 class="text-sm font-semibold text-gray-800 mb-2">Delete project?</h3>
          <p class="text-xs text-gray-500 mb-4">Conversations will be unassigned, not deleted.</p>
          <div class="flex gap-2 justify-end">
            <button class="btn btn-secondary btn-sm text-xs" @click="confirmDeleteModal = false">Cancel</button>
            <button class="btn btn-sm text-xs bg-red-600 hover:bg-red-500 text-white border-red-600" @click="confirmDelete">Delete</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import AnalyseSidebar from '../../analyze/components/AnalyseSidebar.vue'

const props = defineProps({
  project: Object,
  analyseEnabled: Boolean,
  activeChatId: { type: Number, default: null },
  analyseSidebar: Object,
  users: Array,
})

const renaming = ref(false)
const renameValue = ref(props.project.name)
const renameInput = ref(null)
const confirmDeleteModal = ref(false)

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? ''

function openChat(id) {
  router.visit(`/analyze/c/${id}`)
}

async function newChat() {
  const res = await fetch('/analyze/chats', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    body: JSON.stringify({ project_id: props.project.id }),
  })
  const data = await res.json()
  if (data.chat) router.visit(`/analyze/c/${data.chat.id}`)
}

async function unpin(chatId) {
  await fetch(`/analyze/projects/${props.project.id}/pin-chat/${chatId}`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': csrfToken() },
  })
  router.reload()
}

function startRename() {
  renameValue.value = props.project.name
  renaming.value = true
  nextTick(() => renameInput.value?.focus())
}

async function commitRename() {
  renaming.value = false
  const name = renameValue.value.trim()
  if (!name || name === props.project.name) return
  await fetch(`/analyze/projects/${props.project.id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    body: JSON.stringify({ name }),
  })
  router.reload()
}

function deleteProject() {
  confirmDeleteModal.value = true
}

async function confirmDelete() {
  confirmDeleteModal.value = false
  await fetch(`/analyze/projects/${props.project.id}`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': csrfToken() },
  })
  router.visit('/analyze')
}

function formatDate(iso) {
  if (!iso) return '\u2014'
  const d = new Date(iso)
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>
