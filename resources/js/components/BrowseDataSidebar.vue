<template>
  <template v-for="item in items" :key="item.label">
    <span v-if="item.disabled" class="sidebar-link is-disabled select-none"
          :title="item.disabledMsg" aria-disabled="true" tabindex="-1" role="link">
      <SidebarIcon :name="item.icon" />
      <span class="flex-1">{{ item.label }}</span>
      <img v-if="item.ai" :src="'/ai-icon.svg'" class="w-3 h-3 shrink-0 opacity-30" alt="">
    </span>
    <a v-else :href="item.href"
       :class="['sidebar-link', item.active ? 'is-active' : '']"
       :aria-current="item.active ? 'page' : undefined">
      <SidebarIcon :name="item.icon" />
      <span class="flex-1">{{ item.label }}</span>
      <span v-if="item.count" class="text-xs opacity-50 shrink-0">{{ formatCount(item.count) }}</span>
      <img v-if="item.ai" :src="'/ai-icon.svg'" class="w-3 h-3 shrink-0 opacity-50" alt="">
    </a>
  </template>
</template>

<script setup>
import SidebarIcon from './SidebarIcon.vue'

defineProps({
  items: { type: Array, default: () => [] },
})

function formatCount(n) {
  return n >= 1000 ? n.toLocaleString() : n
}
</script>
