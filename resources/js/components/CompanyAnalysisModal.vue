<template>
  <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay" @click.self="$emit('close')">
    <div class="bg-white rounded-lg shadow-xl modal-panel">
      <div class="p-5">
        <h2 class="text-lg font-bold mb-1">Analyse Company</h2>
        <p class="text-sm text-gray-500 mb-4">Select steps and optionally edit prompts before running.</p>

        <div v-if="loading" class="text-center py-8 text-gray-400">Loading steps...</div>

        <div v-else-if="error" class="alert-danger mb-4">{{ error }}</div>

        <form v-else @submit.prevent="runAnalysis">
          <!-- Context summary -->
          <div class="mb-4 p-3 bg-gray-50 rounded border border-gray-200">
            <p class="text-xs font-semibold text-gray-500 mb-1">Input Context</p>
            <div class="grid grid-cols-2 gap-x-4 gap-y-0.5 text-xs">
              <div v-for="(val, key) in contextSummary" :key="key">
                <span class="text-gray-400">{{ key }}:</span> <span class="text-gray-700">{{ val || '—' }}</span>
              </div>
            </div>
          </div>

          <!-- Steps -->
          <div class="space-y-3 mb-4">
            <div v-for="step in steps" :key="step.id" class="border border-gray-200 rounded-lg overflow-hidden">
              <div class="flex items-center gap-3 px-4 py-2.5 bg-gray-50">
                <input type="checkbox" :value="step.id" v-model="selectedIds" class="rounded border-gray-300">
                <div class="flex-1">
                  <p class="text-sm font-medium">{{ step.name }}</p>
                  <p v-if="step.description" class="text-xs text-gray-400">{{ step.description }}</p>
                </div>
                <button type="button" @click="toggleExpand(step.id)" class="text-xs text-brand-600 hover:underline">
                  {{ expanded[step.id] ? 'Hide Prompt' : 'Show Prompt' }}
                </button>
              </div>
              <div v-if="expanded[step.id]" class="px-4 py-3 border-t border-gray-100">
                <div class="flex justify-between items-center mb-1">
                  <label class="text-xs font-medium text-gray-500">Prompt (editable for this run)</label>
                  <button type="button" @click="promptOverrides[step.id] = step.prompt_template" class="text-[10px] text-gray-400 hover:text-gray-600">Reset</button>
                </div>
                <textarea v-model="promptOverrides[step.id]" class="input w-full font-mono text-xs" rows="10"></textarea>
                <div class="mt-1">
                  <p class="text-[10px] text-gray-400">Variables used: <span v-for="v in step.variables" :key="v" class="font-mono">{{ wrapVar(v) }} </span></p>
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2">
            <button type="button" @click="$emit('close')" class="btn btn-secondary btn-sm">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm" :disabled="running || !selectedIds.length">
              {{ running ? 'Running...' : 'Run Analysis' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({ visible: Boolean, companyId: Number })
const emit = defineEmits(['close'])

function wrapVar(v) { return '{' + '{' + v + '}' + '}' }
const loading = ref(false)
const error = ref(null)
const running = ref(false)
const steps = ref([])
const context = ref({})
const selectedIds = ref([])
const expanded = reactive({})
const promptOverrides = reactive({})

watch(() => props.visible, async (val) => {
  if (!val) return
  loading.value = true
  error.value = null
  try {
    const res = await fetch(`/companies/${props.companyId}/analysis/preview`)
    const data = await res.json()
    steps.value = data.steps
    context.value = data.context
    selectedIds.value = data.steps.map(s => s.id)
    data.steps.forEach(s => { promptOverrides[s.id] = s.prompt_template })
  } catch (e) {
    error.value = 'Failed to load analysis steps.'
  }
  loading.value = false
})

const contextSummary = computed(() => {
  const c = context.value
  return {
    company: c.company_name, domain: c.primary_domain, email: c.email,
    'free email': c.is_free_email_domain, 'disposable': c.is_disposable_email_domain,
    channels: c.channel_types,
  }
})

function toggleExpand(id) { expanded[id] = !expanded[id] }

function runAnalysis() {
  running.value = true
  // Build overrides only for steps where template was changed
  const overrides = {}
  steps.value.forEach(s => {
    if (promptOverrides[s.id] !== s.prompt_template) {
      overrides[s.id] = promptOverrides[s.id]
    }
  })

  router.post(`/companies/${props.companyId}/analysis/run`, {
    step_ids: selectedIds.value,
    prompt_overrides: overrides,
  }, {
    onFinish: () => { running.value = false; emit('close') },
  })
}
</script>
