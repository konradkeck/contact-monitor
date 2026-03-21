<template>
  <Modal :show="show" @close="$emit('close')">
    <div class="p-5 max-w-xl mx-auto">
      <h3 class="text-lg font-semibold text-gray-800 mb-1">{{ title }}</h3>
      <p class="text-sm text-gray-500 mb-4">Select which {{ entityLabel }} will be the primary. Others will be merged into it.</p>

      <div v-if="loading" class="flex justify-center py-8">
        <div class="w-5 h-5 border-2 border-gray-200 border-t-brand-600 rounded-full animate-spin" />
      </div>

      <template v-else-if="items.length">
        <div class="space-y-2 mb-4 max-h-[50vh] overflow-y-auto">
          <button v-for="item in items" :key="item.id" type="button"
                  @click="selectedId = item.id"
                  :class="['w-full text-left p-3 rounded-lg border-2 transition',
                           selectedId === item.id
                             ? 'border-brand-500 bg-brand-50/50'
                             : 'border-gray-200 hover:border-gray-300']">
            <div class="flex items-center gap-2 mb-1">
              <input type="radio" :checked="selectedId === item.id" class="accent-brand-600 pointer-events-none">
              <span class="font-medium text-gray-800">{{ item.name }}</span>
              <span v-if="item.badge" class="text-xs bg-indigo-100 text-indigo-700 rounded px-1.5 py-0.5">{{ item.badge }}</span>
            </div>
            <div class="text-xs text-gray-500 ml-6">
              <slot name="item-details" :item="item" />
            </div>
          </button>
        </div>

        <!-- Warning -->
        <div v-if="selectedId" class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-sm text-amber-800 mb-4">
          <strong>{{ items.find(i => i.id === selectedId)?.name }}</strong> will be the primary. All other {{ entityLabel }}s will be merged into it.
        </div>

        <div class="flex gap-2 justify-end">
          <button type="button" @click="$emit('close')" class="btn btn-secondary">Cancel</button>
          <button type="button" @click="submit" :disabled="!selectedId || submitting" class="btn btn-primary">
            {{ submitting ? 'Merging...' : 'Merge' }}
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
  submitUrl: String,
  title: { type: String, default: 'Merge' },
  entityLabel: { type: String, default: 'record' },
  items: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'loaded'])

const loading = ref(false)
const items = ref([])
const selectedId = ref(null)
const submitting = ref(false)

watch(() => props.fetchUrl, async (url) => {
  if (!url) return
  loading.value = true
  items.value = []
  selectedId.value = null

  try {
    const sep = url.includes('?') ? '&' : '?'
    const resp = await fetch(url + sep + 'json=1', {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const data = await resp.json()
    emit('loaded', data)
    // The parent component transforms the data into a flat items array
  } catch (e) {
    console.error('MergeModal load error:', e)
  } finally {
    loading.value = false
  }
})

// Allow parent to set items via prop
watch(() => props.items, (v) => { items.value = v }, { immediate: true })

async function submit() {
  if (!selectedId.value) return
  submitting.value = true
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || ''

  const mergeIds = items.value.filter(i => i.id !== selectedId.value).map(i => i.id)

  try {
    const resp = await fetch(props.submitUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        primary_id: selectedId.value,
        merge_ids: mergeIds,
      }),
    })
    const data = await resp.json()
    if (data.ok && data.redirect) {
      router.visit(data.redirect)
    }
  } catch (e) {
    console.error('MergeModal submit error:', e)
  } finally {
    submitting.value = false
  }
}
</script>
