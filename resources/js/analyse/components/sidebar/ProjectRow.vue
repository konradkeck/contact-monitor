<template>
  <div class="group/row relative flex items-center">
    <a
      :href="`/analyse/p/${project.id}`"
      class="sidebar-link flex-1 min-w-0 pr-6"
      :title="project.name"
    >
      <svg class="sidebar-icon w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
      </svg>
      <span class="flex-1 truncate text-xs">{{ project.name }}</span>
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
      <button class="ctx-item" @click="doAction('rename')">Rename</button>
      <div class="border-t border-gray-700 my-1" />
      <button class="ctx-item text-red-400 hover:text-red-300" @click="doAction('delete')">Delete</button>
    </ContextMenu>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import ContextMenu from './ContextMenu.vue'

const props = defineProps({
  project: Object,
})

const emit = defineEmits(['action'])
const menuRef = ref(null)

function openMenu(e) {
  menuRef.value?.open(e)
}

function doAction(action) {
  menuRef.value?.close()
  emit('action', { action, project: props.project })
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
