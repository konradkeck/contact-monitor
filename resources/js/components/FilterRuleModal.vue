<template>
  <Modal :show="show" @close="$emit('close')">
    <div class="p-5 max-w-lg mx-auto">
      <h3 class="text-lg font-semibold text-gray-800 mb-4">{{ title }}</h3>

      <div v-if="loading" class="flex justify-center py-8">
        <div class="w-5 h-5 border-2 border-gray-200 border-t-brand-600 rounded-full animate-spin" />
      </div>

      <template v-else-if="modalData">
        <!-- Rule type tabs -->
        <div class="flex gap-1 mb-4 flex-wrap">
          <button v-for="(label, key) in modalData.tabs" :key="key" type="button"
                  @click="activeType = key"
                  :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition',
                           activeType === key
                             ? 'bg-brand-600 text-white'
                             : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
            {{ label }}
          </button>
        </div>

        <!-- No rule -->
        <div v-if="activeType === 'none'" class="text-sm text-gray-500 py-4">
          {{ archiveOnly ? 'Archive selected conversations without adding a filter rule.' : 'No filtering rule will be applied.' }}
        </div>

        <!-- Domain -->
        <div v-if="activeType === 'domain'" class="space-y-3">
          <div v-if="modalData.domains?.length" class="flex flex-wrap gap-1.5">
            <button v-for="d in modalData.domains" :key="d" type="button"
                    @click="addTag(d, domainTags)"
                    :class="['text-xs px-2 py-1 rounded-lg border transition',
                             domainTags.includes(d) ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50']">
              {{ d }}
            </button>
          </div>
          <TagInput v-model="domainTags" placeholder="Add domain..." splitOnComma />
        </div>

        <!-- Email -->
        <div v-if="activeType === 'email'" class="space-y-3">
          <div v-if="modalData.emails?.length" class="flex flex-wrap gap-1.5">
            <button v-for="e in modalData.emails" :key="e" type="button"
                    @click="addTag(e, emailTags)"
                    :class="['text-xs px-2 py-1 rounded-lg border transition',
                             emailTags.includes(e) ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50']">
              {{ e }}
            </button>
          </div>
          <TagInput v-model="emailTags" placeholder="Add email..." splitOnComma />
        </div>

        <!-- Subject -->
        <div v-if="activeType === 'subject'" class="space-y-3">
          <div v-if="modalData.subjects?.length" class="flex flex-wrap gap-1.5">
            <button v-for="s in modalData.subjects" :key="s" type="button"
                    @click="addTag(s, subjectTags)"
                    :class="['text-xs px-2 py-1 rounded-lg border transition truncate max-w-full',
                             subjectTags.includes(s) ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50']">
              {{ s }}
            </button>
          </div>
          <TagInput v-model="subjectTags" placeholder="Add subject..." />
        </div>

        <!-- Contact -->
        <div v-if="activeType === 'contact' && modalData.contacts" class="space-y-2">
          <label v-for="(name, id) in modalData.contacts" :key="id"
                 class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-gray-50">
            <input type="radio" v-model="selectedContact" :value="String(id)" class="accent-brand-600">
            <span class="text-sm text-gray-700">{{ name }}</span>
          </label>
        </div>

        <!-- Actions -->
        <div class="flex gap-2 mt-6 justify-end">
          <button type="button" @click="$emit('close')" class="btn btn-secondary">Cancel</button>
          <button type="button" @click="submit" :disabled="submitting"
                  :class="['btn', archiveOnly ? 'btn-danger' : 'btn-primary']">
            {{ submitting ? 'Applying...' : (archiveOnly ? 'Filter' : 'Apply Rule') }}
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
import TagInput from './TagInput.vue'

const props = defineProps({
  show: Boolean,
  fetchUrl: String,
  submitUrl: String,
  title: { type: String, default: 'Apply Filter Rule' },
  archiveOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'applied'])

const loading = ref(false)
const submitting = ref(false)
const modalData = ref(null)
const activeType = ref('none')
const domainTags = ref([])
const emailTags = ref([])
const subjectTags = ref([])
const selectedContact = ref(null)

watch(() => props.fetchUrl, async (url) => {
  if (!url) return
  loading.value = true
  modalData.value = null
  activeType.value = 'none'
  domainTags.value = []
  emailTags.value = []
  subjectTags.value = []
  selectedContact.value = null

  try {
    const sep = url.includes('?') ? '&' : '?'
    const resp = await fetch(url + sep + 'json=1', {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    modalData.value = await resp.json()
  } catch (e) {
    console.error('FilterRuleModal load error:', e)
  } finally {
    loading.value = false
  }
})

function addTag(val, arr) {
  const idx = arr.indexOf(val)
  if (idx === -1) arr.push(val)
  else arr.splice(idx, 1)
}

async function submit() {
  let ruleType = activeType.value
  let ruleValues = []

  if (ruleType === 'domain') ruleValues = domainTags.value
  else if (ruleType === 'email') ruleValues = emailTags.value
  else if (ruleType === 'subject') ruleValues = subjectTags.value
  else if (ruleType === 'contact' && selectedContact.value) ruleValues = [selectedContact.value]

  submitting.value = true
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || ''

  try {
    const body = {
      rule_type: ruleType,
      rule_values: ruleValues,
    }
    if (modalData.value?.ids) {
      body.ids = modalData.value.ids
    }

    const resp = await fetch(props.submitUrl, {
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
      emit('applied', data.message)
      emit('close')
      router.reload()
    }
  } catch (e) {
    console.error('FilterRuleModal submit error:', e)
  } finally {
    submitting.value = false
  }
}
</script>
