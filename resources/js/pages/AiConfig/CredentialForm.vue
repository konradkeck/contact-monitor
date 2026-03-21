<template>
  <AppLayout>
    <Head :title="credential ? 'Edit Credential' : 'Add Credential'" />

    <div class="max-w-xl">
      <div class="page-header">
        <div>
          <nav aria-label="Breadcrumb" class="page-breadcrumb">
            <Link href="/configuration/ai?tab=credentials">Connect AI</Link>
            <span class="sep">/</span>
            <span class="cur" aria-current="page">{{ credential ? 'Edit Credential' : 'Add Credential' }}</span>
          </nav>
          <h1 class="page-title mt-1">{{ credential ? 'Edit Credential' : 'Add Credential' }}</h1>
        </div>
      </div>

      <div class="card p-6">
        <form @submit.prevent="submit">
          <div class="space-y-4">
            <div>
              <label class="label">Label</label>
              <input type="text" v-model="form.name" class="input w-full" placeholder="e.g. My Claude Key" required>
              <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
            </div>
            <div>
              <label class="label">Provider</label>
              <select v-model="form.provider" class="input w-full" required>
                <option value="">&mdash; Select provider &mdash;</option>
                <option v-for="(label, key) in providers" :key="key" :value="key">{{ label }}</option>
              </select>
              <p v-if="form.errors.provider" class="text-xs text-red-500 mt-1">{{ form.errors.provider }}</p>
            </div>
            <div>
              <label class="label">API Key</label>
              <input type="password" v-model="form.api_key" class="input w-full font-mono text-xs"
                     :placeholder="credential ? '(unchanged — enter new key to replace)' : 'sk-...'"
                     :required="!credential" autocomplete="new-password">
              <p v-if="form.errors.api_key" class="text-xs text-red-500 mt-1">{{ form.errors.api_key }}</p>
            </div>
          </div>

          <div v-if="testResult" :class="['mt-4 text-xs rounded-lg px-3 py-2', testResultClass]">
            {{ testResult }}
          </div>

          <div class="flex gap-2 mt-6">
            <button type="submit" class="btn btn-primary" :disabled="form.processing">
              {{ credential ? 'Save Changes' : 'Add Credential' }}
            </button>
            <button type="button" @click="testConnection" class="btn btn-secondary" :disabled="testing">
              {{ testing ? 'Testing...' : 'Test Connection' }}
            </button>
            <Link href="/configuration/ai?tab=credentials" class="btn btn-secondary">Cancel</Link>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({
  credential: { type: Object, default: null },
  providers: { type: Object, required: true },
})

const form = useForm({
  name: props.credential?.name || '',
  provider: props.credential?.provider || '',
  api_key: '',
})

const testing = ref(false)
const testResult = ref('')
const testResultClass = ref('')

function submit() {
  if (props.credential) {
    form.put(`/configuration/ai/credentials/${props.credential.id}`)
  } else {
    form.post('/configuration/ai/credentials')
  }
}

async function testConnection() {
  testResult.value = ''

  // For existing credentials without new key, test saved key
  if (props.credential && !form.api_key) {
    if (!form.provider) {
      testResult.value = 'Select a provider first.'
      testResultClass.value = 'text-amber-700 bg-amber-50 border border-amber-200'
      return
    }
    testing.value = true
    try {
      const res = await fetch(`/configuration/ai/credentials/${props.credential.id}/test`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
      })
      const data = await res.json()
      if (data.ok) {
        testResult.value = 'Connection successful.'
        testResultClass.value = 'text-green-700 bg-green-50 border border-green-200'
      } else {
        testResult.value = 'Connection failed: ' + (data.error || 'Unknown error')
        testResultClass.value = 'text-red-700 bg-red-50 border border-red-200'
      }
    } catch (e) {
      testResult.value = 'Request failed: ' + e.message
      testResultClass.value = 'text-red-700 bg-red-50 border border-red-200'
    } finally {
      testing.value = false
    }
    return
  }

  // Test with raw provider + key
  if (!form.provider || !form.api_key) {
    testResult.value = 'Select a provider and enter an API key first.'
    testResultClass.value = 'text-amber-700 bg-amber-50 border border-amber-200'
    return
  }

  testing.value = true
  try {
    const res = await fetch('/configuration/ai/credentials/test', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      },
      body: JSON.stringify({ provider: form.provider, api_key: form.api_key }),
    })
    const data = await res.json()
    if (data.ok) {
      testResult.value = 'Connection successful.'
      testResultClass.value = 'text-green-700 bg-green-50 border border-green-200'
    } else {
      testResult.value = 'Connection failed: ' + (data.error || 'Unknown error')
      testResultClass.value = 'text-red-700 bg-red-50 border border-red-200'
    }
  } catch (e) {
    testResult.value = 'Request failed: ' + e.message
    testResultClass.value = 'text-red-700 bg-red-50 border border-red-200'
  } finally {
    testing.value = false
  }
}
</script>
