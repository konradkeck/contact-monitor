<template>
  <AppLayout>
    <Head title="AI Costs" />

    <div class="page-header">
      <div class="flex items-center justify-between w-full">
        <h1 class="page-title">AI Costs</h1>
        <Link href="/configuration/ai-costs/pricing" class="btn btn-secondary btn-sm">Pricing Overrides</Link>
      </div>
    </div>

    <!-- Totals -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
      <div class="card p-4 text-center">
        <p class="text-xs text-gray-500 mb-1">Total Input Tokens</p>
        <p class="text-lg font-semibold text-gray-800">{{ formatNumber(totals.total_input) }}</p>
      </div>
      <div class="card p-4 text-center">
        <p class="text-xs text-gray-500 mb-1">Total Output Tokens</p>
        <p class="text-lg font-semibold text-gray-800">{{ formatNumber(totals.total_output) }}</p>
      </div>
      <div class="card p-4 text-center">
        <p class="text-xs text-gray-500 mb-1">Estimated Total Cost</p>
        <p class="text-lg font-semibold text-gray-800">${{ totals.total_cost.toFixed(4) }}</p>
      </div>
    </div>

    <!-- Filters -->
    <form @submit.prevent="applyFilters" class="card p-4 mb-4">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="label">Action Type</label>
          <select v-model="filterForm.action_type" class="input w-full">
            <option value="">All</option>
            <option v-for="(label, key) in actionTypes" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <div>
          <label class="label">From</label>
          <input type="date" v-model="filterForm.from" class="input w-full">
        </div>
        <div>
          <label class="label">To</label>
          <input type="date" v-model="filterForm.to" class="input w-full">
        </div>
      </div>
      <div class="mt-3 flex justify-end gap-2">
        <Link href="/configuration/ai-costs" class="btn btn-secondary btn-sm">Clear</Link>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      </div>
    </form>

    <!-- Log table -->
    <div class="card-xl-overflow">
      <table class="w-full text-sm">
        <thead class="tbl-header">
          <tr>
            <th class="px-4 py-2.5 text-left">Date</th>
            <th class="px-4 py-2.5 text-left">Action</th>
            <th class="px-4 py-2.5 text-left">Model</th>
            <th class="px-4 py-2.5 text-right">Input</th>
            <th class="px-4 py-2.5 text-right">Output</th>
            <th class="px-4 py-2.5 text-right">Cost</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs.data" :key="log.id" class="tbl-row">
            <td class="px-4 py-3 text-gray-500 text-xs">{{ formatDateTime(log.created_at) }}</td>
            <td class="px-4 py-3">
              <span class="badge badge-gray">{{ actionTypes[log.action_type] || log.action_type }}</span>
            </td>
            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ log.model_name }}</td>
            <td class="px-4 py-3 text-right text-gray-600">{{ formatNumber(log.input_tokens) }}</td>
            <td class="px-4 py-3 text-right text-gray-600">{{ formatNumber(log.output_tokens) }}</td>
            <td class="px-4 py-3 text-right font-medium">${{ ((log.cost_input_usd || 0) + (log.cost_output_usd || 0)).toFixed(6) }}</td>
          </tr>
          <tr v-if="logs.data.length === 0">
            <td colspan="6" class="px-4 py-8 text-center empty-state italic">No usage logged yet.</td>
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
import { reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../layouts/AppLayout.vue'

const props = defineProps({
  logs: { type: Object, required: true },
  totals: { type: Object, required: true },
  actionTypes: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
})

const filterForm = reactive({
  action_type: props.filters.action_type || '',
  from: props.filters.from || '',
  to: props.filters.to || '',
})

function applyFilters() {
  const params = {}
  if (filterForm.action_type) params.action_type = filterForm.action_type
  if (filterForm.from) params.from = filterForm.from
  if (filterForm.to) params.to = filterForm.to
  router.get('/configuration/ai-costs', params, { preserveState: true })
}

function formatNumber(n) {
  return (n || 0).toLocaleString()
}

function formatDateTime(dateStr) {
  if (!dateStr) return ''
  return dateStr.substring(0, 16).replace('T', ' ')
}
</script>
