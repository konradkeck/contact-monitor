<template>
  <Modal :show="show" @close="$emit('close')">
    <div class="p-5 max-w-md mx-auto">
      <h3 class="text-lg font-semibold text-gray-800 mb-1">Add Filter Rule</h3>
      <p v-if="name" class="text-sm text-gray-500 mb-4">For: <strong>{{ name }}</strong></p>

      <div v-if="loading" class="flex justify-center py-8">
        <div class="w-5 h-5 border-2 border-gray-200 border-t-brand-600 rounded-full animate-spin" />
      </div>

      <template v-else-if="data">
        <!-- Rule type tabs -->
        <div class="flex gap-1 mb-4">
          <button v-for="(label, key) in data.tabs" :key="key" type="button"
                  @click="setType(key)"
                  :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition',
                           activeType === key
                             ? 'bg-brand-600 text-white'
                             : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
            {{ label }}
          </button>
        </div>

        <div v-if="activeType === 'none'" class="text-sm text-gray-500 py-4">
          No filtering rule will be applied.
        </div>

        <!-- Domain -->
        <div v-if="activeType === 'domain'" class="space-y-3">
          <div v-if="data.domains?.length" class="flex flex-wrap gap-1.5">
            <button v-for="d in data.domains" :key="d" type="button"
                    @click="ruleValue = d"
                    :class="['text-xs px-2 py-1 rounded-lg border transition',
                             ruleValue === d ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50']">
              {{ d }}
            </button>
          </div>
          <input v-model="ruleValue" type="text" placeholder="Domain..." class="input w-full">
        </div>

        <!-- Email -->
        <div v-if="activeType === 'email'" class="space-y-3">
          <div v-if="data.emails?.length" class="flex flex-wrap gap-1.5">
            <button v-for="e in data.emails" :key="e" type="button"
                    @click="ruleValue = e"
                    :class="['text-xs px-2 py-1 rounded-lg border transition',
                             ruleValue === e ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50']">
              {{ e }}
            </button>
          </div>
          <input v-model="ruleValue" type="text" placeholder="Email..." class="input w-full">
        </div>

        <!-- Preview -->
        <div v-if="activeType !== 'none' && ruleValue" class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-sm text-amber-800 mt-3">
          Rule: <strong>{{ activeType }}: {{ ruleValue }}</strong>
        </div>

        <div class="flex gap-2 mt-6 justify-end">
          <button type="button" @click="$emit('close')" class="btn btn-secondary">Cancel</button>
          <button type="button" @click="submit" :disabled="submitting || (activeType !== 'none' && !ruleValue)"
                  class="btn btn-primary">
            {{ submitting ? 'Applying...' : 'Apply' }}
          </button>
        </div>
      </template>
    </div>
  </Modal>
</template>

<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from './Modal.vue'

const props = defineProps({
  show: Boolean,
  fetchUrl: String,
})

const emit = defineEmits(['close', 'applied'])

const loading = ref(false)
const submitting = ref(false)
const data = ref(null)
const name = ref('')
const activeType = ref('none')
const ruleValue = ref('')

watch(() => props.fetchUrl, async (url) => {
  if (!url) return
  loading.value = true
  data.value = null
  activeType.value = 'none'
  ruleValue.value = ''
  name.value = ''

  try {
    const sep = url.includes('?') ? '&' : '?'
    const resp = await fetch(url + sep + 'json=1', {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const d = await resp.json()
    data.value = d
    name.value = d.name || ''
    // Auto-select first available type
    if (d.domains?.length) {
      activeType.value = 'domain'
      ruleValue.value = d.domains[0]
    } else if (d.emails?.length) {
      activeType.value = 'email'
      ruleValue.value = d.emails[0]
    }
  } catch (e) {
    console.error('IdentityFilterModal load error:', e)
  } finally {
    loading.value = false
  }
})

function setType(key) {
  activeType.value = key
  if (key === 'domain' && data.value?.domains?.length) ruleValue.value = data.value.domains[0]
  else if (key === 'email' && data.value?.emails?.length) ruleValue.value = data.value.emails[0]
  else ruleValue.value = ''
}

async function submit() {
  submitting.value = true
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || ''

  try {
    const resp = await fetch('/data-relations/filtering/apply-rule', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        rule_type: activeType.value,
        rule_values: ruleValue.value ? [ruleValue.value] : [],
      }),
    })
    const result = await resp.json()
    if (result.ok) {
      emit('applied', result.message)
      emit('close')
      router.reload()
    }
  } catch (e) {
    console.error('IdentityFilterModal submit error:', e)
  } finally {
    submitting.value = false
  }
}
</script>
