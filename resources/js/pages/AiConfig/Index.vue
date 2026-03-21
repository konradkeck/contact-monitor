<template>
  <AppLayout>
    <Head title="Connect AI" />

    <div class="page-header">
      <h1 class="page-title">Connect AI</h1>
      <Link v-if="activeTab === 'credentials'" href="/configuration/ai/credentials/create" class="btn btn-primary btn-sm">Add Credential</Link>
    </div>

    <!-- Tabs -->
    <div class="flex gap-0 border-b border-gray-200 mb-5">
      <Link href="/configuration/ai?tab=credentials"
            :class="['px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition',
                     activeTab === 'credentials' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
        Credentials ({{ credentials.length }})
      </Link>
      <Link :href="credentials.length > 0 ? '/configuration/ai?tab=models' : '#'"
            :class="['px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition',
                     activeTab === 'models' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                     credentials.length === 0 ? 'opacity-40 pointer-events-none' : '']"
            :title="credentials.length === 0 ? 'Add an AI credential first to configure model assignments' : undefined">
        Model Assignment
      </Link>
    </div>

    <!-- Credentials Tab -->
    <div v-if="activeTab === 'credentials'" class="card overflow-hidden max-w-2xl">
      <table v-if="credentials.length > 0" class="w-full text-sm">
        <thead class="tbl-header">
          <tr>
            <th class="px-4 py-2.5 text-left font-medium">Name</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-left font-medium">Provider</th>
            <th class="px-4 py-2.5"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="cred in credentials" :key="cred.id" class="tbl-row">
            <td class="px-4 py-2.5">
              <span class="font-medium text-gray-800">{{ cred.name }}</span>
            </td>
            <td class="col-mobile-hidden px-4 py-2.5 text-gray-500 text-xs">{{ cred.provider_label || cred.provider }}</td>
            <td class="px-4 py-2.5 text-right">
              <div class="row-actions-desktop items-center justify-end gap-1.5">
                <Link :href="`/configuration/ai/credentials/${cred.id}/edit`" class="row-action text-xs">Edit</Link>
                <button @click="deleteCredential(cred)" class="row-action-danger text-xs">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-else class="px-5 py-12 text-center text-sm empty-state italic">
        No credentials configured.
        <Link href="/configuration/ai/credentials/create" class="text-brand-600 hover:underline not-italic">Add one</Link> to enable AI features.
      </div>
    </div>

    <!-- Model Assignment Tab -->
    <div v-if="activeTab === 'models' && credentials.length > 0" class="max-w-2xl">
      <p class="text-xs text-gray-400 mb-4">Assign a credential and model to each AI action. Leave blank to disable.</p>

      <form @submit.prevent="saveModels">
        <div class="card overflow-hidden">
          <table class="w-full text-sm">
            <thead class="tbl-header">
              <tr>
                <th class="px-4 py-2.5 text-left font-medium w-44">Action</th>
                <th class="px-4 py-2.5 text-left font-medium">Credential</th>
                <th class="px-4 py-2.5 text-left font-medium">Model</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="(label, actionKey) in actionTypes" :key="actionKey" class="tbl-row">
                <td class="px-4 py-3 font-medium text-gray-700 truncate" :title="label">{{ label }}</td>
                <td class="px-4 py-3">
                  <select v-model="configs[actionKey].credential_id"
                          @change="onCredentialChange(actionKey)"
                          class="input text-xs py-1.5 w-full">
                    <option value="">&mdash; None &mdash;</option>
                    <option v-for="cred in credentials" :key="cred.id" :value="cred.id">{{ cred.name }}</option>
                  </select>
                </td>
                <td class="px-4 py-3">
                  <select v-model="configs[actionKey].model_name" class="input text-xs py-1.5 w-full">
                    <option value="">&mdash; model &mdash;</option>
                    <option v-for="m in modelOptions[actionKey]" :key="m" :value="m">{{ m }}</option>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <button type="submit" class="btn btn-primary btn-sm mt-4" :disabled="saving">Save Assignments</button>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({
  credentials: { type: Array, default: () => [] },
  providers: { type: Object, default: () => ({}) },
  modelConfigs: { type: Object, default: () => ({}) },
  actionTypes: { type: Object, default: () => ({}) },
  activeTab: { type: String, default: 'credentials' },
})

const saving = ref(false)
const modelOptionsCache = {}
const modelOptions = reactive({})

// Initialize configs for each action
const configs = reactive({})
for (const actionKey of Object.keys(props.actionTypes)) {
  const existing = props.modelConfigs[actionKey]
  configs[actionKey] = {
    credential_id: existing?.credential_id || '',
    model_name: existing?.model_name || '',
  }
  modelOptions[actionKey] = existing?.model_name ? [existing.model_name] : []
}

async function onCredentialChange(actionKey) {
  const credId = configs[actionKey].credential_id
  configs[actionKey].model_name = ''
  modelOptions[actionKey] = []

  if (!credId) return

  if (modelOptionsCache[credId]) {
    modelOptions[actionKey] = modelOptionsCache[credId]
    return
  }

  try {
    const res = await fetch(`/configuration/ai/credentials/${credId}/models`)
    const data = await res.json()
    modelOptionsCache[credId] = data.models || []
    modelOptions[actionKey] = modelOptionsCache[credId]
  } catch {
    modelOptions[actionKey] = []
  }
}

// Load models for pre-selected credentials on mount
onMounted(() => {
  for (const actionKey of Object.keys(configs)) {
    const credId = configs[actionKey].credential_id
    if (credId) {
      const currentModel = configs[actionKey].model_name
      fetch(`/configuration/ai/credentials/${credId}/models`)
        .then(r => r.json())
        .then(data => {
          modelOptionsCache[credId] = data.models || []
          modelOptions[actionKey] = modelOptionsCache[credId]
          // Re-set model if it was in the list
          if (currentModel && modelOptionsCache[credId].includes(currentModel)) {
            configs[actionKey].model_name = currentModel
          }
        })
        .catch(() => {})
    }
  }
})

function saveModels() {
  saving.value = true
  router.post('/configuration/ai/model-configs', { configs }, {
    onFinish: () => { saving.value = false },
  })
}

function deleteCredential(cred) {
  if (confirm(`Delete credential ${cred.name}?`)) {
    router.delete(`/configuration/ai/credentials/${cred.id}`)
  }
}
</script>
