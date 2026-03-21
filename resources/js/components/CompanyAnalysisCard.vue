<template>
  <div class="-mt-4 mx-4 mb-4 bg-white rounded-lg border border-brand-100 shadow-sm px-4 py-3">
    <div class="flex items-center justify-between mb-1">
      <p class="text-xs font-semibold text-brand-400 uppercase tracking-wide">Company Analysis</p>
      <div class="flex gap-1">
        <a v-if="latestRun" :href="`/companies/${companyId}/analysis/${latestRun.id}`" class="text-xs text-brand-600 hover:underline">Full Report</a>
      </div>
    </div>

    <div v-if="!latestRun && !loading" class="flex items-center justify-between">
      <p class="text-xs text-gray-400 italic">No analysis yet.</p>
      <button v-if="canWrite" @click="$emit('openModal')" class="btn btn-primary btn-sm text-xs">Analyse Company</button>
    </div>

    <div v-if="loading" class="text-xs text-gray-400 italic">Loading...</div>

    <div v-if="latestRun">
      <!-- Key fields -->
      <div class="space-y-1 mb-2">
        <div v-for="field in keyFields" :key="field.field_key" class="flex items-baseline gap-2 text-xs">
          <span class="text-gray-400 w-32 shrink-0 truncate">{{ formatKey(field.field_key) }}</span>
          <span class="text-gray-700 font-medium">{{ field.field_value }}</span>
          <span v-if="field.confidence" :class="['badge text-[10px] px-1 py-0', confidenceBadge(field.confidence)]">{{ field.confidence }}</span>
        </div>
      </div>

      <!-- Entities summary -->
      <div v-if="entitySummary.length" class="flex flex-wrap gap-1 mb-2">
        <span v-for="es in entitySummary" :key="es.type" class="badge badge-gray text-[10px]">
          {{ es.count }} {{ es.type }}{{ es.count > 1 ? 's' : '' }}
        </span>
      </div>

      <div class="flex items-center justify-between pt-1 border-t border-gray-100">
        <span class="text-[10px] text-gray-400">
          {{ latestRun.completed_at ? new Date(latestRun.completed_at).toLocaleDateString() : '' }}
          {{ latestRun.user_name ? `by ${latestRun.user_name}` : '' }}
        </span>
        <button v-if="canWrite" @click="$emit('openModal')" class="text-xs text-brand-600 hover:text-brand-800 font-medium">Run Again</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({ companyId: Number, canWrite: Boolean })
defineEmits(['openModal'])

const latestRun = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await fetch(`/companies/${props.companyId}/analysis/latest`)
    const data = await res.json()
    latestRun.value = data.run
  } catch (e) { /* ignore */ }
  loading.value = false
})

const keyFields = computed(() => {
  if (!latestRun.value?.fields) return []
  const priority = ['resolved_company_name', 'official_company_name', 'industry', 'hq_country', 'hq_city', 'founded_year', 'employee_count_range', 'owner_type', 'market_position']
  return latestRun.value.fields
    .filter(f => priority.includes(f.field_key) && f.field_value)
    .sort((a, b) => priority.indexOf(a.field_key) - priority.indexOf(b.field_key))
    .slice(0, 6)
})

const entitySummary = computed(() => {
  if (!latestRun.value?.entities) return []
  const counts = {}
  latestRun.value.entities.forEach(e => { counts[e.entity_type] = (counts[e.entity_type] || 0) + 1 })
  return Object.entries(counts).map(([type, count]) => ({ type, count }))
})

function formatKey(key) {
  return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

function confidenceBadge(c) {
  return c === 'high' ? 'badge-green' : c === 'medium' ? 'badge-yellow' : 'badge-gray'
}
</script>
