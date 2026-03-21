<template>
  <Modal :show="show" @close="$emit('close')">
    <div class="p-5 max-w-md mx-auto">
      <h3 class="text-lg font-semibold text-gray-800 mb-4">Assign Company</h3>

      <!-- Mode toggle -->
      <div class="flex gap-2 mb-4">
        <button type="button" @click="mode = 'existing'"
                :class="['btn btn-sm flex-1', mode === 'existing' ? 'btn-primary' : 'btn-secondary']">
          Existing Company
        </button>
        <button type="button" @click="mode = 'new'"
                :class="['btn btn-sm flex-1', mode === 'new' ? 'btn-primary' : 'btn-secondary']">
          Create New
        </button>
      </div>

      <!-- Existing company search -->
      <div v-if="mode === 'existing'" class="space-y-3">
        <div class="relative">
          <input v-model="searchQuery" type="text" placeholder="Search companies..."
                 class="input w-full" @input="debounceSearch">
          <div v-if="searchResults.length" class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg max-h-48 overflow-y-auto">
            <button v-for="c in searchResults" :key="c.id" type="button"
                    @click="pickCompany(c)"
                    class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 transition flex items-center gap-2">
              <span class="font-medium text-gray-800">{{ c.name }}</span>
              <span v-if="c.primary_domain" class="text-xs text-gray-400">{{ c.primary_domain }}</span>
            </button>
          </div>
        </div>
        <div v-if="selectedCompany" class="flex items-center gap-2 bg-brand-50 border border-brand-200 rounded-lg px-3 py-2">
          <svg class="w-4 h-4 text-brand-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
          <span class="text-sm font-medium text-brand-700">{{ selectedCompany.name }}</span>
          <button type="button" @click="selectedCompany = null" class="ml-auto text-brand-400 hover:text-brand-600">&times;</button>
        </div>
      </div>

      <!-- New company -->
      <div v-if="mode === 'new'">
        <input v-model="newCompanyName" type="text" placeholder="Company name..."
               class="input w-full" @keydown.enter.prevent="submit">
      </div>

      <!-- Error -->
      <p v-if="error" class="text-sm text-red-500 mt-2">{{ error }}</p>

      <!-- Actions -->
      <div class="flex gap-2 mt-6 justify-end">
        <button type="button" @click="$emit('close')" class="btn btn-secondary">Cancel</button>
        <button type="button" @click="submit" :disabled="submitting || (!selectedCompany && mode === 'existing') || (!newCompanyName.trim() && mode === 'new')"
                class="btn btn-primary">
          {{ submitting ? 'Assigning...' : 'Assign' }}
        </button>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from './Modal.vue'

const props = defineProps({
  show: Boolean,
  personIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])

const mode = ref('existing')
const searchQuery = ref('')
const searchResults = ref([])
const selectedCompany = ref(null)
const newCompanyName = ref('')
const submitting = ref(false)
const error = ref('')
let searchTimer = null

watch(() => props.show, (v) => {
  if (v) {
    mode.value = 'existing'
    searchQuery.value = ''
    searchResults.value = []
    selectedCompany.value = null
    newCompanyName.value = ''
    error.value = ''
  }
})

function debounceSearch() {
  clearTimeout(searchTimer)
  selectedCompany.value = null
  if (searchQuery.value.trim().length < 1) {
    searchResults.value = []
    return
  }
  searchTimer = setTimeout(doSearch, 220)
}

async function doSearch() {
  try {
    const resp = await fetch('/companies/search?q=' + encodeURIComponent(searchQuery.value), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    searchResults.value = await resp.json()
  } catch (e) {
    searchResults.value = []
  }
}

function pickCompany(c) {
  selectedCompany.value = c
  searchQuery.value = c.name
  searchResults.value = []
}

async function submit() {
  submitting.value = true
  error.value = ''
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || ''

  const body = {
    ids: props.personIds,
    mode: mode.value,
  }
  if (mode.value === 'existing' && selectedCompany.value) {
    body.company_id = selectedCompany.value.id
  } else if (mode.value === 'new') {
    body.name = newCompanyName.value.trim()
  }

  try {
    const resp = await fetch('/people/bulk-assign-company', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(body),
    })
    const data = await resp.json()
    if (data.ok) {
      emit('close')
      router.reload()
    } else {
      error.value = data.error || 'Failed to assign company.'
    }
  } catch (e) {
    error.value = 'An error occurred.'
  } finally {
    submitting.value = false
  }
}
</script>
