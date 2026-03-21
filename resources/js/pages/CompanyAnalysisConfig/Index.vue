<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Company Analysis</h1>
    </div>

    <!-- Tabs -->
    <div class="flex gap-4 mb-5 border-b border-gray-200">
      <button @click="tab = 'steps'" :class="['pb-2 px-1 text-sm font-medium border-b-2 transition', tab === 'steps' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700']">
        Analysis Steps
      </button>
      <button @click="tab = 'domains'" :class="['pb-2 px-1 text-sm font-medium border-b-2 transition', tab === 'domains' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700']">
        Domain Classification
      </button>
    </div>

    <!-- Steps Tab -->
    <div v-if="tab === 'steps'">
      <div class="flex justify-between items-center mb-4">
        <p class="text-sm text-gray-500">Configure the steps that run during company analysis. Steps execute in order.</p>
        <button @click="openCreate" class="btn btn-primary btn-sm">+ Add Step</button>
      </div>

      <div class="card-xl-overflow">
        <table class="w-full text-sm">
          <thead class="tbl-header">
            <tr>
              <th class="px-4 py-2 text-left w-10">Order</th>
              <th class="px-4 py-2 text-left">Key</th>
              <th class="px-4 py-2 text-left">Name</th>
              <th class="px-4 py-2 text-left">Status</th>
              <th class="px-4 py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(step, idx) in localSteps" :key="step.id" class="tbl-row">
              <td class="px-4 py-2">
                <div class="flex gap-1">
                  <button v-if="idx > 0" @click="moveUp(idx)" class="text-gray-400 hover:text-gray-600 text-xs">▲</button>
                  <button v-if="idx < localSteps.length - 1" @click="moveDown(idx)" class="text-gray-400 hover:text-gray-600 text-xs">▼</button>
                </div>
              </td>
              <td class="px-4 py-2 font-mono text-xs text-gray-500">{{ step.key }}</td>
              <td class="px-4 py-2">
                <p class="font-medium">{{ step.name }}</p>
                <p v-if="step.description" class="text-xs text-gray-400 mt-0.5">{{ step.description }}</p>
              </td>
              <td class="px-4 py-2">
                <span :class="['badge', step.is_enabled ? 'badge-green' : 'badge-gray']">
                  {{ step.is_enabled ? 'Enabled' : 'Disabled' }}
                </span>
              </td>
              <td class="px-4 py-2 text-right">
                <button @click="openEdit(step)" class="row-action mr-2">Edit</button>
                <button @click="toggleEnabled(step)" class="row-action mr-2">{{ step.is_enabled ? 'Disable' : 'Enable' }}</button>
                <button @click="deleteStep(step)" class="row-action-danger">Delete</button>
              </td>
            </tr>
            <tr v-if="!localSteps.length">
              <td colspan="5" class="px-4 py-8 text-center text-gray-400 italic">No analysis steps configured.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Domains Tab -->
    <div v-if="tab === 'domains'">
      <div class="card-xl">
        <div class="card-header">
          <h3 class="text-sm font-semibold">Domain Classification</h3>
          <form @submit.prevent="syncDomains">
            <button type="submit" class="btn btn-secondary btn-sm" :disabled="syncing">
              {{ syncing ? 'Syncing...' : 'Sync Now' }}
            </button>
          </form>
        </div>
        <div class="p-4 space-y-3">
          <div class="flex gap-6 text-sm">
            <div><span class="font-medium">Free email domains:</span> {{ domainSync.free_email_count }}</div>
            <div><span class="font-medium">Disposable domains:</span> {{ domainSync.disposable_count }}</div>
            <div><span class="font-medium">Last synced:</span> {{ domainSync.last_synced_at ? new Date(domainSync.last_synced_at).toLocaleString() : 'Never' }}</div>
          </div>

          <form @submit.prevent="saveDomainSettings" class="space-y-3 pt-3 border-t border-gray-100">
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" v-model="domainAutoEnabled" class="rounded border-gray-300">
              Enable automatic daily sync (lazy — checks on config page load and before analysis runs)
            </label>
            <div>
              <label class="label">Disposable domains source URL</label>
              <input v-model="domainSources.disposable" class="input w-full" placeholder="https://...">
            </div>
            <div>
              <label class="label">Free email domains source URL</label>
              <input v-model="domainSources.free_email" class="input w-full" placeholder="https://...">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save Settings</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Step Form Modal -->
    <div v-if="showForm" class="modal-overlay" @click.self="showForm = false">
      <div class="modal-center" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
        <div class="p-5">
          <h2 class="text-lg font-bold mb-4">{{ editing ? 'Edit Step' : 'Create Step' }}</h2>
          <form @submit.prevent="saveStep" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">Internal Key</label>
                <input v-model="form.key" class="input w-full" placeholder="e.g. company_identity_resolution" :disabled="!!editing">
              </div>
              <div>
                <label class="label">Name</label>
                <input v-model="form.name" class="input w-full" placeholder="e.g. Company Identity Resolution">
              </div>
            </div>
            <div>
              <label class="label">Description</label>
              <textarea v-model="form.description" class="input w-full" rows="2" placeholder="Short description of what this step does"></textarea>
            </div>
            <div>
              <label class="label">Prompt Template</label>
              <textarea v-model="form.prompt_template" class="input w-full font-mono text-xs" rows="14" placeholder="Use {{variable}} for template variables"></textarea>
              <div class="mt-2 p-3 bg-gray-50 rounded border border-gray-200">
                <p class="text-xs font-semibold text-gray-500 mb-1">Available variables:</p>
                <div class="flex flex-wrap gap-1">
                  <span v-for="v in availableVars" :key="v" class="text-xs font-mono bg-white border border-gray-200 rounded px-1.5 py-0.5 text-gray-600 cursor-pointer hover:bg-brand-50" @click="form.prompt_template += wrapVar(v)">{{ wrapVar(v) }}</span>
                </div>
                <p class="text-xs text-gray-400 mt-2">For previous step outputs: <code class="bg-white px-1 rounded">{{ wrapVar('previous.step_key.field') }}</code> or <code class="bg-white px-1 rounded">{{ wrapVar('previous.step_key') }}</code></p>
              </div>
            </div>
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" v-model="form.is_enabled" class="rounded border-gray-300">
              Enabled
            </label>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="showForm = false" class="btn btn-secondary btn-sm">Cancel</button>
              <button type="submit" class="btn btn-primary btn-sm">{{ editing ? 'Update' : 'Create' }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({ steps: Array, domainSync: Object })
const tab = ref('steps')
const showForm = ref(false)
const editing = ref(null)
const syncing = ref(false)
const localSteps = ref([...props.steps])
const domainAutoEnabled = ref(props.domainSync.auto_enabled)
const domainSources = reactive({ ...props.domainSync.sources })

const availableVars = [
  'company_name', 'primary_domain', 'all_domains', 'person_name', 'email', 'email_domain',
  'is_free_email_domain', 'is_disposable_email_domain', 'contact_names', 'contact_emails',
  'domain_from_last_message', 'last_message_excerpt', 'address', 'channel_types',
  'services_summary', 'brand_statuses',
]

const form = reactive({ key: '', name: '', description: '', prompt_template: '', is_enabled: true })

function wrapVar(v) { return '{' + '{' + v + '}' + '}' }

function openCreate() {
  editing.value = null
  Object.assign(form, { key: '', name: '', description: '', prompt_template: '', is_enabled: true })
  showForm.value = true
}

function openEdit(step) {
  editing.value = step
  Object.assign(form, { key: step.key, name: step.name, description: step.description || '', prompt_template: step.prompt_template, is_enabled: step.is_enabled })
  showForm.value = true
}

function saveStep() {
  if (editing.value) {
    router.put(`/configuration/company-analysis/steps/${editing.value.id}`, { ...form }, {
      onSuccess: () => { showForm.value = false },
    })
  } else {
    router.post('/configuration/company-analysis/steps', { ...form }, {
      onSuccess: () => { showForm.value = false },
    })
  }
}

function deleteStep(step) {
  if (!confirm(`Delete step "${step.name}"?`)) return
  router.delete(`/configuration/company-analysis/steps/${step.id}`)
}

function toggleEnabled(step) {
  router.put(`/configuration/company-analysis/steps/${step.id}`, {
    key: step.key, name: step.name, description: step.description,
    prompt_template: step.prompt_template, is_enabled: !step.is_enabled,
  })
}

function moveUp(idx) {
  if (idx === 0) return
  const items = [...localSteps.value];
  [items[idx], items[idx - 1]] = [items[idx - 1], items[idx]]
  localSteps.value = items
  saveOrder(items)
}

function moveDown(idx) {
  if (idx >= localSteps.value.length - 1) return
  const items = [...localSteps.value];
  [items[idx], items[idx + 1]] = [items[idx + 1], items[idx]]
  localSteps.value = items
  saveOrder(items)
}

function saveOrder(items) {
  const steps = items.map((s, i) => ({ id: s.id, sort_order: (i + 1) * 10 }))
  router.post('/configuration/company-analysis/steps/reorder', { steps })
}

function syncDomains() {
  syncing.value = true
  router.post('/configuration/company-analysis/domain-sync', {}, {
    onFinish: () => { syncing.value = false },
  })
}

function saveDomainSettings() {
  router.post('/configuration/company-analysis/domain-settings', {
    auto_enabled: domainAutoEnabled.value,
    sources: domainSources,
  })
}
</script>
