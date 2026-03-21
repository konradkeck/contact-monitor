<template>
  <div
    class="group/row relative flex items-center"
    :class="{ 'is-active': isActive }"
  >
    <a
      :href="`/analyze/c/${chat.id}`"
      class="sidebar-link flex-1 min-w-0 pr-6"
      :class="{ 'is-active': isActive }"
      :title="chat.title || 'New Conversation'"
    >
      <svg class="sidebar-icon w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
      </svg>
      <span class="flex-1 truncate text-xs">{{ chat.title || 'New Conversation' }}</span>
      <span v-if="chat.is_shared" class="w-1.5 h-1.5 rounded-full bg-blue-400 shrink-0" title="Shared" />
    </a>

    <!-- Three-dot menu button (hover only) -->
    <button
      class="absolute right-1 top-1/2 -translate-y-1/2 opacity-0 group-hover/row:opacity-100 p-1 text-gray-600 hover:text-gray-300 transition-opacity"
      @click.prevent.stop="openMenu"
      title="Actions"
    >
      <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
      </svg>
    </button>

    <ContextMenu ref="menuRef">
      <button class="ctx-item" @click="doAction('rename')">
        <span>Rename</span>
      </button>
      <button class="ctx-item" @click="doAction('move')">
        <span>Move to project</span>
      </button>
      <button class="ctx-item" @click="doAction('share')">
        <span>Share with user</span>
      </button>
      <button class="ctx-item" @click="doAction('archive')">
        <span>{{ chat.is_archived ? 'Unarchive' : 'Archive' }}</span>
      </button>
      <div class="border-t border-gray-700 my-1" />
      <button class="ctx-item text-red-400 hover:text-red-300" @click="doAction('delete')">
        <span>Delete</span>
      </button>
    </ContextMenu>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import ContextMenu from './ContextMenu.vue'

const props = defineProps({
  chat: Object,
  isActive: Boolean,
})

const emit = defineEmits(['action'])
const menuRef = ref(null)

function openMenu(e) {
  menuRef.value?.open(e)
}

function doAction(action) {
  menuRef.value?.close()
  emit('action', { action, chat: props.chat })
}
</script>

<style scoped>
.ctx-item {
  width: 100%;
  text-align: left;
  padding: 0.375rem 0.75rem;
  font-size: 0.75rem;
  color: #d1d5db;
  cursor: pointer;
}
.ctx-item:hover {
  background: #374151;
}
</style>
