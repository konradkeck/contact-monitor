<template>
  <AppLayout>
    <Head title="Configure New Server" />

    <div class="page-header">
      <h1 class="page-title">Configure New Server</h1>
      <Link href="/configuration/synchronizer-servers/wizard" class="btn btn-secondary btn-sm">Back</Link>
    </div>

    <div class="max-w-2xl space-y-5">
      <div class="card p-5">
        <h2 class="font-semibold text-gray-800 mb-1">1. Run this command on your server</h2>
        <p class="text-xs text-gray-500 mb-3">This downloads and sets up the synchronizer, then connects it to this Contact Monitor instance automatically.</p>

        <div class="relative">
          <pre ref="cmdEl" class="code-block rounded-lg text-xs p-4 overflow-x-auto leading-relaxed select-all">SYNC_APP_PORT=8080 SYNC_DB_PORT=5433 bash &lt;(curl -sSL {{ installCmd }})</pre>
          <button @click="copyCmd" class="absolute top-2 right-2 btn btn-secondary btn-sm">{{ copied ? 'Copied!' : 'Copy' }}</button>
        </div>
        <p class="text-xs text-gray-400 mt-2">Change <code class="font-mono">SYNC_APP_PORT</code> and <code class="font-mono">SYNC_DB_PORT</code> if the defaults are already in use.</p>
      </div>

      <div class="card p-5">
        <h2 class="font-semibold text-gray-800 mb-1">2. Waiting for connection</h2>
        <p class="text-xs text-gray-500 mb-4">After running the command, the synchronizer will automatically connect here. This page will update when it's detected.</p>

        <div class="flex items-center gap-3">
          <span class="inline-block w-2.5 h-2.5 rounded-full"
                :class="[statusColor, status === 'pending' ? 'animate-pulse' : '']"></span>
          <span class="text-sm text-gray-600" v-html="statusText"></span>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../../../layouts/AppLayout.vue'

const props = defineProps({
  installCmd: { type: String, required: true },
  pollUrl: { type: String, required: true },
})

const cmdEl = ref(null)
const copied = ref(false)
const status = ref('pending')
const statusColor = ref('bg-amber-400')
const statusText = ref('Waiting for synchronizer to connect\u2026')
let pollTimerId = null

function copyCmd() {
  const text = cmdEl.value?.textContent?.trim()
  if (text) {
    navigator.clipboard.writeText(text).then(() => {
      copied.value = true
      setTimeout(() => { copied.value = false }, 2000)
    })
  }
}

async function poll() {
  try {
    const res = await fetch(props.pollUrl)
    const data = await res.json()

    if (data.status === 'registered') {
      status.value = 'registered'
      statusColor.value = 'bg-green-500'
      statusText.value = '<strong class="text-green-700">Connected!</strong> Redirecting\u2026'
      setTimeout(() => router.visit('/configuration/synchronizer-servers'), 1500)
      return
    }

    if (data.status === 'expired') {
      status.value = 'expired'
      statusColor.value = 'bg-red-500'
      statusText.value = 'Registration token expired. Please go back and try again.'
      return
    }
  } catch {}

  pollTimerId = setTimeout(poll, 3000)
}

onMounted(() => {
  pollTimerId = setTimeout(poll, 3000)
})

onUnmounted(() => {
  if (pollTimerId) clearTimeout(pollTimerId)
})
</script>
