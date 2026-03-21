<template>
  <div class="group/row relative flex items-center">
    <a
      :href="`/analyse/c/${chat.id}`"
      class="sidebar-link flex-1 min-w-0 pr-6"
      :class="{ 'is-active': isActive }"
      :title="chat.title || 'New Conversation'"
    >
      <svg class="sidebar-icon w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
      </svg>
      <span class="flex-1 min-w-0">
        <span class="block truncate text-xs">{{ chat.title || 'New Conversation' }}</span>
        <span class="block text-gray-600 text-xs truncate">{{ chat.owner_name }}</span>
      </span>
    </a>

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
      <button class="ctx-item" @click="doAction('add-to-project')">Add to my project</button>
      <button class="ctx-item" @click="doAction('branch')">Branch to private</button>
      <div class="border-t border-gray-700 my-1" />
      <button class="ctx-item text-red-400 hover:text-red-300" @click="doAction('leave')">Remove from sidebar</button>
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
