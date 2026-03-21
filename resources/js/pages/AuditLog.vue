<template>
  <AppLayout>
    <Head title="Audit Log" />

    <div class="page-header">
      <div>
        <h1 class="page-title">Audit Log</h1>
        <p class="text-xs text-gray-400 mt-0.5">Immutable record of system actions.</p>
      </div>
    </div>

    <div class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="tbl-header">
          <tr>
            <th class="px-4 py-2.5 text-left">When</th>
            <th class="px-4 py-2.5 text-left">Action</th>
            <th class="px-4 py-2.5 text-left">Entity</th>
            <th class="px-4 py-2.5 text-left">Message</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs.data" :key="log.id" class="tbl-row">
            <td class="px-4 py-2 text-xs text-gray-400 whitespace-nowrap">
              {{ formatDate(log.created_at) }}
            </td>
            <td class="px-4 py-2">
              <span :class="['badge', badgeColor(log.action)]">{{ log.action }}</span>
            </td>
            <td class="px-4 py-2 text-xs">
              <span class="text-gray-600 font-medium">{{ entityBasename(log.entity_type) }}</span>
              <span class="text-gray-400 font-mono ml-0.5">#{{ log.entity_id }}</span>
            </td>
            <td class="px-4 py-2 text-gray-700">{{ log.message }}</td>
          </tr>
          <tr v-if="logs.data.length === 0">
            <td colspan="4" class="px-4 py-8 text-center text-gray-400 italic">No audit logs yet.</td>
          </tr>
        </tbody>
      </table>
      <div v-if="logs.links && logs.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex gap-1 flex-wrap">
        <template v-for="link in logs.links" :key="link.label">
          <Link v-if="link.url" :href="link.url" class="px-2.5 py-1 text-xs rounded"
                :class="link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
                v-html="link.label" />
          <span v-else class="px-2.5 py-1 text-xs text-gray-300" v-html="link.label" />
        </template>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '../layouts/AppLayout.vue'

defineProps({
  logs: { type: Object, required: true },
})

const actionColors = {
  created: 'badge-green',
  updated: 'badge-blue',
  deleted: 'badge-red',
  added_domain: 'badge-purple',
  added_alias: 'badge-purple',
  added_identity: 'badge-purple',
  added_note: 'badge-yellow',
}

function badgeColor(action) {
  return actionColors[action] || 'badge-gray'
}

function entityBasename(type) {
  if (!type) return ''
  return type.split('\\').pop()
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toISOString().replace('T', ' ').substring(0, 19)
}
</script>
