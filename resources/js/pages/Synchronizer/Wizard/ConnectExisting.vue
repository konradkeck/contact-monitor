<template>
  <AppLayout>
    <Head title="Connect to Existing Server" />

    <div class="page-header">
      <h1 class="page-title">Connect to Existing Server</h1>
      <Link href="/configuration/synchronizer-servers/wizard" class="btn btn-secondary btn-sm">Back</Link>
    </div>

    <div class="max-w-lg">
      <!-- Step 1: form -->
      <div v-if="step === 1">
        <div class="card p-5 space-y-4">
          <div>
            <label class="label">Name</label>
            <input type="text" v-model="name" class="input" placeholder="e.g. Production Synchronizer">
          </div>
          <div>
            <label class="label">URL</label>
            <input type="url" v-model="url" class="input" placeholder="http://localhost:8080">
          </div>
          <div>
            <label class="label">API Token</label>
            <input type="text" v-model="apiToken" class="input font-mono text-xs" placeholder="Bearer token">
          </div>
        </div>

        <div class="mt-4 flex items-center gap-3">
          <button @click="testAndInspect" :disabled="testing" class="btn btn-primary">
            Test &amp; Continue &rarr;
          </button>
          <span v-if="testError" class="text-xs text-red-600">{{ testError }}</span>
          <span v-if="testing" class="text-xs text-gray-400">Testing connection...</span>
        </div>
      </div>

      <!-- Step 2: confirm -->
      <div v-if="step === 2">
        <!-- Points elsewhere warning -->
        <div v-if="inspectData?.points_elsewhere" class="card p-4 mb-4 text-sm alert-warning">
          <div class="flex gap-2 items-start">
            <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-amber-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <div>
              <strong class="text-amber-800">This synchronizer is currently sending data elsewhere.</strong>
              <p class="mt-1 text-xs text-amber-900">
                Currently connected to: <code class="font-mono">{{ inspectData.current_ingest }}</code><br>
                Connecting it here will redirect all data to <strong>this Contact Monitor</strong> and break the existing connection.
              </p>
            </div>
          </div>
          <div class="mt-3 flex gap-2">
            <button @click="confirmConnect" class="btn btn-primary btn-sm">Yes, connect here</button>
            <button @click="step = 1" class="btn btn-secondary btn-sm">Cancel</button>
          </div>
        </div>

        <!-- All good -->
        <div v-else class="card p-4 mb-4 text-sm alert-success">
          <div class="flex gap-2 items-start">
            <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-green-800">
              <strong>Ready to connect.</strong> This synchronizer is already configured to send data to this Contact Monitor instance.
            </div>
          </div>
          <div class="mt-3">
            <button @click="confirmConnect" class="btn btn-primary btn-sm">Add server</button>
          </div>
        </div>

        <p v-if="saveError" class="text-xs text-red-600 mt-2">{{ saveError }}</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../../../layouts/AppLayout.vue'

const csrf = document.querySelector('meta[name="csrf-token"]')?.content || ''

const step = ref(1)
const name = ref('')
const url = ref('')
const apiToken = ref('')
const testing = ref(false)
const testError = ref('')
const inspectData = ref(null)
const saveError = ref('')

async function testAndInspect() {
  if (!url.value || !apiToken.value || !name.value) {
    testError.value = 'Fill in all fields first.'
    return
  }

  testing.value = true
  testError.value = ''

  try {
    const res = await fetch('/configuration/synchronizer-servers/wizard/inspect', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ url: url.value, api_token: apiToken.value, name: name.value }),
    })
    const data = await res.json()

    if (!data.ok) {
      testError.value = '\u2717 ' + data.error
      return
    }

    inspectData.value = data
    step.value = 2
  } catch (e) {
    testError.value = '\u2717 ' + e.message
  } finally {
    testing.value = false
  }
}

async function confirmConnect() {
  saveError.value = 'Saving\u2026'

  try {
    const res = await fetch('/configuration/synchronizer-servers/wizard/connect-save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ url: url.value, api_token: apiToken.value, name: name.value }),
    })
    const data = await res.json()

    if (data.ok) {
      router.visit(data.redirect)
    } else {
      saveError.value = '\u2717 ' + data.error
    }
  } catch (e) {
    saveError.value = '\u2717 ' + e.message
  }
}
</script>
