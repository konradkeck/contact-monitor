<template>
  <AppLayout>
    <div class="page-header">
      <div>
        <nav aria-label="Breadcrumb" class="page-breadcrumb">
          <a href="/companies">Companies</a>
          <span class="sep">/</span>
          <a :href="`/companies/${company.id}`">{{ company.name }}</a>
          <span class="sep">/</span>
          <span class="cur" aria-current="page">Analysis Run #{{ run.id }}</span>
        </nav>
        <h1 class="page-title mt-1">Analysis Results</h1>
      </div>
      <span :class="['badge', statusBadge]">{{ run.status }}</span>
    </div>

    <!-- Run meta -->
    <div class="card-xl mb-5">
      <div class="px-5 py-3 flex flex-wrap gap-6 text-sm">
        <div><span class="text-gray-400">Run by:</span> {{ run.user_name || 'Unknown' }}</div>
        <div><span class="text-gray-400">Started:</span> {{ run.started_at ? new Date(run.started_at).toLocaleString() : '—' }}</div>
        <div><span class="text-gray-400">Completed:</span> {{ run.completed_at ? new Date(run.completed_at).toLocaleString() : '—' }}</div>
        <div><span class="text-gray-400">Total tokens:</span> {{ totalTokens.toLocaleString() }}</div>
      </div>
    </div>

    <!-- Structured Fields -->
    <div v-if="run.fields.length" class="card-xl mb-5">
      <div class="card-header"><h3 class="text-sm font-semibold">Analysis Fields</h3></div>
      <table class="w-full text-sm">
        <thead class="tbl-header">
          <tr>
            <th class="px-4 py-2 text-left">Group</th>
            <th class="px-4 py-2 text-left">Field</th>
            <th class="px-4 py-2 text-left">Value</th>
            <th class="px-4 py-2 text-left">Confidence</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in run.fields" :key="f.field_key" class="tbl-row">
            <td class="px-4 py-2 text-xs text-gray-400">{{ f.field_group }}</td>
            <td class="px-4 py-2 font-medium">{{ formatKey(f.field_key) }}</td>
            <td class="px-4 py-2">{{ f.field_value }}</td>
            <td class="px-4 py-2">
              <span v-if="f.confidence" :class="['badge text-xs', cBadge(f.confidence)]">{{ f.confidence }}</span>
              <span v-if="f.is_inferred" class="badge badge-yellow text-xs ml-1">inferred</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Entities -->
    <div v-if="run.entities.length" class="card-xl mb-5">
      <div class="card-header"><h3 class="text-sm font-semibold">Entities</h3></div>
      <div v-for="(group, type) in groupedEntities" :key="type" class="card-inner px-5 py-3">
        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">{{ type }}s ({{ group.length }})</p>
        <div class="flex flex-wrap gap-2">
          <div v-for="e in group" :key="e.display_name" class="bg-gray-50 border border-gray-200 rounded px-3 py-2 text-sm">
            <p class="font-medium">{{ e.display_name || 'Unknown' }}</p>
            <div v-for="(val, key) in e.data_json" :key="key" class="text-xs text-gray-500">
              <span v-if="key !== 'name' && key !== 'display_name'">{{ key }}: {{ typeof val === 'object' ? JSON.stringify(val) : val }}</span>
            </div>
            <span v-if="e.confidence" :class="['badge text-[10px]', cBadge(e.confidence)]">{{ e.confidence }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Step Runs -->
    <div class="space-y-4">
      <div v-for="sr in run.step_runs" :key="sr.id" class="card-xl">
        <div class="card-header">
          <div class="flex items-center gap-2">
            <h3 class="text-sm font-semibold">{{ formatKey(sr.step_key) }}</h3>
            <span :class="['badge text-xs', sr.status === 'completed' ? 'badge-green' : sr.status === 'failed' ? 'badge-red' : 'badge-gray']">{{ sr.status }}</span>
          </div>
          <span class="text-xs text-gray-400">{{ sr.model_name }} &middot; {{ sr.input_tokens + sr.output_tokens }} tokens</span>
        </div>
        <div class="px-5 py-3 space-y-3">
          <div v-if="sr.error_message" class="alert-danger text-sm">{{ sr.error_message }}</div>
          <details>
            <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">Rendered Prompt</summary>
            <pre class="mt-2 text-xs bg-gray-50 p-3 rounded border border-gray-200 overflow-x-auto whitespace-pre-wrap">{{ sr.rendered_prompt }}</pre>
          </details>
          <details v-if="sr.raw_response">
            <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">Raw Response</summary>
            <pre class="mt-2 text-xs bg-gray-50 p-3 rounded border border-gray-200 overflow-x-auto whitespace-pre-wrap">{{ sr.raw_response }}</pre>
          </details>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({ company: Object, run: Object })

const statusBadge = computed(() => ({
  completed: 'badge-green', failed: 'badge-red', running: 'badge-blue', pending: 'badge-gray'
}[props.run.status] || 'badge-gray'))

const totalTokens = computed(() =>
  props.run.step_runs.reduce((s, sr) => s + (sr.input_tokens || 0) + (sr.output_tokens || 0), 0)
)

const groupedEntities = computed(() => {
  const groups = {}
  props.run.entities.forEach(e => {
    if (!groups[e.entity_type]) groups[e.entity_type] = []
    groups[e.entity_type].push(e)
  })
  return groups
})

function formatKey(key) { return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }
function cBadge(c) { return c === 'high' ? 'badge-green' : c === 'medium' ? 'badge-yellow' : 'badge-gray' }
</script>
