<template>
  <AppLayout>
    <Head :title="server ? 'Edit Server' : 'New Server'" />

    <div class="page-header">
      <div>
        <nav aria-label="Breadcrumb" class="page-breadcrumb">
          <Link href="/configuration/synchronizer-servers">Servers</Link>
          <span class="sep">/</span>
          <span class="cur" aria-current="page">{{ server ? 'Edit Server' : 'New Server' }}</span>
        </nav>
        <h1 class="page-title mt-1">{{ server ? 'Edit Server' : 'New Server' }}</h1>
      </div>
    </div>

    <div class="card p-5 max-w-lg">
      <form @submit.prevent="submit">
        <div class="space-y-4">
          <div>
            <label class="label">Name</label>
            <input type="text" v-model="form.name" class="input" placeholder="e.g. Production Synchronizer" required>
            <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="label">URL</label>
            <input type="url" v-model="form.url" class="input" placeholder="http://contact-monitor-synchronizer:8000" required>
            <p v-if="form.errors.url" class="text-xs text-red-500 mt-1">{{ form.errors.url }}</p>
          </div>
          <div>
            <label class="label">API Token</label>
            <input type="text" v-model="form.api_token" class="input font-mono text-xs" placeholder="Bearer token" required>
            <p v-if="form.errors.api_token" class="text-xs text-red-500 mt-1">{{ form.errors.api_token }}</p>
          </div>
        </div>

        <!-- Test connection -->
        <div class="mt-4 flex items-center gap-3">
          <button type="button" @click="testConnection" class="btn btn-secondary btn-sm" :disabled="testing">
            <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Test connection
          </button>
          <span v-if="testResult" class="text-xs" :class="testOk ? 'text-green-700' : 'text-red-600'">
            {{ testResult }}
          </span>
        </div>

        <div class="mt-5 pt-4 border-t border-gray-100 flex items-center gap-2">
          <button type="submit" class="btn btn-primary" :disabled="form.processing">
            {{ server ? 'Save changes' : 'Add server' }}
          </button>
          <Link href="/configuration/synchronizer-servers" class="btn btn-secondary">Cancel</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '../../../layouts/AppLayout.vue'

const props = defineProps({
  server: { type: Object, default: null },
})

const form = useForm({
  name: props.server?.name || '',
  url: props.server?.url || '',
  api_token: props.server?.api_token || '',
})

const testing = ref(false)
const testResult = ref('')
const testOk = ref(false)

function submit() {
  if (props.server) {
    form.put(`/configuration/synchronizer-servers/${props.server.id}`)
  } else {
    form.post('/configuration/synchronizer-servers')
  }
}

async function testConnection() {
  if (!form.url || !form.api_token) {
    testResult.value = 'Fill in URL and API Token first.'
    testOk.value = false
    return
  }

  testing.value = true
  testResult.value = 'Testing...'

  try {
    const res = await fetch('/configuration/synchronizer-servers/test', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      },
      body: JSON.stringify({ url: form.url, api_token: form.api_token }),
    })
    const data = await res.json()
    testOk.value = data.ok
    testResult.value = data.ok ? `\u2713 ${data.message}` : `\u2717 ${data.error}`
  } catch (e) {
    testOk.value = false
    testResult.value = `\u2717 ${e.message}`
  } finally {
    testing.value = false
  }
}
</script>
