<template>
  <!-- General -->
  <span class="sidebar-section pt-1">General</span>
  <template v-for="item in items.general" :key="item.label">
    <SidebarItem :item="item" />
  </template>

  <div class="sidebar-divider" />

  <!-- Synchronization -->
  <span class="sidebar-section">Synchronization</span>
  <template v-for="item in items.synchronization" :key="item.label">
    <SidebarItem :item="item" />
  </template>

  <div class="sidebar-divider" />

  <!-- Data Relations -->
  <span class="sidebar-section">Data Relations</span>
  <template v-for="item in items.data_relations" :key="item.label">
    <SidebarItem :item="item" />
  </template>

  <div class="sidebar-divider" />

  <!-- AI Functionality -->
  <span class="sidebar-section sidebar-section-ai">AI Functionality</span>
  <template v-for="item in items.ai_functionality" :key="item.label">
    <SidebarItem :item="item" />
  </template>

  <div class="sidebar-divider" />

  <!-- Segmentation -->
  <span class="sidebar-section">Segmentation</span>
  <template v-for="item in items.segmentation" :key="item.label">
    <SidebarItem :item="item" />
  </template>
</template>

<script setup>
import SidebarItem from './SidebarItem.vue'

defineProps({
  items: { type: Object, default: () => ({}) },
})
</script>
