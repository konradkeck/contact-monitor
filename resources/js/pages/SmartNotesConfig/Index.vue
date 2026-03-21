<template>
  <AppLayout>
    <Head title="Smart Notes" />

    <div class="page-header">
      <h1 class="page-title">Smart Notes</h1>
    </div>

    <!-- Tabs -->
    <div class="flex gap-0 border-b border-gray-200 mb-5">
      <Link href="/configuration/smart-notes?tab=filtering"
            :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition -mb-px',
                     activeTab === 'filtering' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700']">
        Notes Filtering
      </Link>
      <span title="Coming soon"
            class="px-4 py-2.5 text-sm font-medium border-b-2 border-transparent text-gray-300 cursor-not-allowed select-none -mb-px flex items-center gap-1.5">
        <img :src="'/ai-icon.svg'" class="w-3.5 h-3.5 opacity-40" alt="">
        AI Recognition
        <span class="text-xs bg-gray-100 text-gray-400 border border-gray-200 rounded px-1.5 py-0.5 font-normal">Soon</span>
      </span>
    </div>

    <template v-if="activeTab === 'filtering'">
      <!-- Enable/Disable -->
      <div class="card p-5 mb-5 max-w-2xl">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="font-medium text-sm text-gray-800">Smart Notes Filtering</p>
            <p class="text-xs text-gray-500 mt-0.5">When enabled, messages matching the filters below are automatically captured as Smart Notes.</p>
          </div>
          <button @click="toggleEnabled"
                  :class="['btn btn-sm', enabled ? 'btn-danger' : 'btn-primary']">
            {{ enabled ? 'Disable' : 'Enable' }}
          </button>
        </div>
        <div v-if="enabled" class="mt-3 flex items-center gap-2 text-xs text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
          <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          Smart Notes Filtering is active.
        </div>
      </div>

      <!-- Filters list -->
      <div class="flex items-center justify-between mb-4 max-w-4xl">
        <h2 class="section-header-title">Active Filters</h2>
        <div class="flex items-center gap-2">
          <button @click="scan" class="btn btn-secondary btn-sm" title="Scan existing messages against these filters">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Scan Existing
          </button>
          <Link href="/configuration/smart-notes/filters/create" class="btn btn-sm btn-primary">Add Filter</Link>
        </div>
      </div>

      <div class="card-xl-overflow mb-5 max-w-4xl">
        <table class="w-full text-sm">
          <thead class="tbl-header">
            <tr>
              <th class="px-4 py-2.5 text-left">Type</th>
              <th class="px-4 py-2.5 text-left">Criteria</th>
              <th class="px-4 py-2.5 text-left">Internal Note</th>
              <th class="px-4 py-2.5 text-left">Status</th>
              <th class="px-4 py-2.5 text-right w-16"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="filter in filters" :key="filter.id" class="tbl-row">
              <td class="px-4 py-3 font-medium text-gray-700">{{ filter.type_label }}</td>
              <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ filter.summary_label }}</td>
              <td class="px-4 py-3">
                <span v-if="filter.as_internal_note" class="badge badge-blue">Internal</span>
                <span v-else class="text-gray-400 text-xs">&mdash;</span>
              </td>
              <td class="px-4 py-3">
                <span v-if="filter.is_active" class="badge badge-green">Active</span>
                <span v-else class="badge badge-gray">Inactive</span>
              </td>
              <td class="px-4 py-3 text-right">
                <button @click="deleteFilter(filter)" class="row-action-danger text-xs">Delete</button>
              </td>
            </tr>
            <tr v-if="filters.length === 0">
              <td colspan="5" class="px-4 py-8 text-center empty-state italic">No filters configured yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </AppLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({
  enabled: { type: Boolean, default: false },
  filters: { type: Array, default: () => [] },
  activeTab: { type: String, default: 'filtering' },
})

function toggleEnabled() {
  router.post('/configuration/smart-notes/settings', {
    enabled: props.enabled ? '0' : '1',
  })
}

function scan() {
  router.post('/configuration/smart-notes/scan')
}

function deleteFilter(filter) {
  if (confirm('Delete this filter?')) {
    router.delete(`/configuration/smart-notes/filters/${filter.id}`)
  }
}
</script>
