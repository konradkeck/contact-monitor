<template>
  <span v-if="item.disabled" class="sidebar-link is-disabled select-none"
        :title="item.disabledMsg" aria-disabled="true" tabindex="-1" role="link">
    <SidebarIcon :name="item.icon" />
    <span class="flex-1">{{ item.label }}</span>
  </span>
  <a v-else :href="item.href"
     :class="['sidebar-link', item.active ? 'is-active' : '']"
     :aria-current="item.active ? 'page' : undefined">
    <SidebarIcon :name="item.icon" />
    <span class="flex-1">{{ item.label }}</span>
    <span v-if="item.dot === 'red'" class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0" />
    <span v-else-if="item.dot === 'amber'" class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0" />
    <span v-else-if="item.dot === 'green'" class="w-1.5 h-1.5 rounded-full bg-green-500 shrink-0" />
  </a>
</template>

<script setup>
import SidebarIcon from './SidebarIcon.vue'

defineProps({
  item: { type: Object, required: true },
})
</script>
