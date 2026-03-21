<template>
  <AppLayout>
    <Head :title="conn.name + ' — Synchronizer'" />

    <!-- Run mode modal -->
    <div v-if="showRunModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay" @click.self="showRunModal = false">
      <div class="bg-white rounded-xl shadow-xl w-80" @click.stop>
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <span class="font-semibold text-gray-800 text-sm">Start sync</span>
          <button @click="showRunModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div class="p-4 flex flex-col gap-3">
          <button @click="doRun('partial')" class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-brand-400 hover:bg-brand-50 text-left transition group">
            <span class="mt-0.5 text-brand-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg></span>
            <div><div class="font-semibold text-gray-800 text-sm">Partial sync</div><div class="text-xs text-gray-400 mt-0.5">Fetches only new data since the last run.</div></div>
          </button>
          <button @click="doRun('full')" class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-gray-400 hover:bg-gray-50 text-left transition group">
            <span class="mt-0.5 text-gray-500"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0l-4-4m4 4l-4 4"/></svg></span>
            <div><div class="font-semibold text-gray-800 text-sm">Full sync</div><div class="text-xs text-gray-400 mt-0.5">Re-imports everything from scratch.</div></div>
          </button>
        </div>
      </div>
    </div>

    <!-- Header -->
    <div class="page-header">
      <div class="flex items-center gap-3">
        <Link href="/configuration/synchronizer/connections" class="text-gray-400 hover:text-gray-600 text-sm">&larr; Connections</Link>
        <span class="text-gray-300">/</span>
        <h1 class="page-title">{{ conn.name }}</h1>
        <span :class="'badge badge-sync-' + conn.type">{{ conn.type }}</span>
      </div>
      <div class="flex items-center gap-2">
        <button v-if="runStatus === 'running' || runStatus === 'pending'" @click="stopConnection" class="btn btn-danger btn-sm">Stop</button>
        <button v-else @click="showRunModal = true" class="btn btn-secondary btn-sm">
          <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3l14 9-14 9V3z"/></svg>
          Run
        </button>
        <Link :href="`/configuration/synchronizer/connections/${conn.id}/edit`" class="btn btn-muted btn-sm">Edit</Link>
      </div>
    </div>

    <!-- Connection info -->
    <div class="card p-4 mb-4 flex items-start gap-6 flex-wrap text-sm">
      <div>
        <div class="text-xs text-gray-400 mb-0.5">System slug</div>
        <div class="font-mono text-gray-700">{{ conn.system_slug }}</div>
      </div>
      <div>
        <div class="text-xs text-gray-400 mb-0.5">Schedule</div>
        <div class="text-gray-700">{{ conn.schedule_label }}</div>
        <div v-if="conn.next_run_at" class="text-xs text-gray-400">next: {{ timeAgo(conn.next_run_at) }}</div>
      </div>
      <div v-if="runs.length > 0">
        <div class="text-xs text-gray-400 mb-0.5">Last run</div>
        <div class="text-gray-700">{{ timeAgo(runs[0].created_at) }}</div>
        <div v-if="runs[0].duration_seconds" class="text-xs text-gray-400">{{ runs[0].duration_seconds }}s</div>
      </div>
      <div>
        <div class="text-xs text-gray-400 mb-0.5">Status</div>
        <div class="flex items-center gap-2">
          <span class="badge" :class="'badge-status-' + (runStatus || 'pending')">
            <span v-if="runStatus === 'running' || runStatus === 'pending'" class="inline-block w-1.5 h-1.5 rounded-full mr-0.5 animate-pulse bg-current"></span>
            {{ runStatus || '\u2014' }}
          </span>
          <span v-if="runStatus === 'running' || runStatus === 'pending'" class="flex items-center gap-1 text-blue-500 text-xs font-medium">
            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
              <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            {{ runStatus === 'pending' ? 'queued...' : 'syncing...' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Layout: run history + log viewer -->
    <div class="flex gap-4 items-start">
      <!-- Run history (desktop) -->
      <div class="card overflow-hidden flex-shrink-0 hidden md:block w-80">
        <div class="px-4 py-2.5 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
          Run history
        </div>
        <div class="overflow-y-auto max-h-[560px]">
          <button v-for="run in runs" :key="run.id"
                  @click="selectRun(run.id)"
                  :class="activeRunId === run.id ? 'bg-brand-50 border-l-2 border-brand-500' : 'border-l-2 border-transparent hover:bg-gray-50'"
                  class="w-full text-left px-4 py-2.5 border-b border-gray-50 transition text-sm">
            <div class="flex items-center justify-between gap-2">
              <span class="font-mono text-xs text-gray-400">#{{ run.id }}</span>
              <span :class="'badge text-xs badge-status-' + (run.status || 'pending')">{{ run.status }}</span>
            </div>
            <div class="text-xs text-gray-500 mt-0.5">
              {{ formatDate(run.created_at) }}
              <span v-if="run.duration_seconds" class="text-gray-400"> &middot; {{ run.duration_seconds }}s</span>
            </div>
            <div v-if="run.triggered_by" class="text-xs text-gray-300 mt-0.5">{{ run.triggered_by }}</div>
          </button>
          <div v-if="runs.length === 0" class="px-4 py-8 text-center text-sm text-gray-300">No runs yet.</div>
        </div>
      </div>

      <!-- Log viewer -->
      <div class="card flex flex-col flex-1 overflow-hidden min-h-[400px]">
        <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
            Logs
            <span v-if="activeRunId" class="font-mono font-normal normal-case text-gray-400 ml-1">#{{ activeRunId }}</span>
          </div>
          <div class="flex items-center gap-2 text-xs">
            <span class="text-gray-400">{{ logs.length }} lines</span>
            <span v-if="runStatus === 'running' || runStatus === 'pending'" class="inline-flex items-center gap-1 text-blue-500">
              <span class="inline-block w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span> live
            </span>
            <span v-else-if="runStatus === 'completed'" class="text-green-500">completed</span>
            <span v-else-if="runStatus === 'failed'" class="text-red-500">failed</span>
          </div>
        </div>
        <div ref="logEl" class="flex-1 overflow-y-auto font-mono text-xs p-4 space-y-0.5 bg-gray-50 min-h-[360px]">
          <div v-if="loading" class="text-gray-400">Loading...</div>
          <div v-else-if="logs.length === 0" class="text-gray-400">
            {{ activeRunId ? 'No log output yet.' : 'Select a run to view logs.' }}
          </div>
          <div v-for="(line, i) in logs" :key="i" class="leading-5"
               :class="{
                 'text-red-600': line.level === 'error',
                 'text-amber-600': line.level === 'warning',
                 'text-gray-600': !line.level || line.level === 'info',
               }">
            <span class="text-gray-300 select-none mr-2">{{ formatTime(line.t) }}</span>
            <span>{{ line.msg }}</span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({
  conn: { type: Object, required: true },
  runs: { type: Array, default: () => [] },
})

const csrf = document.querySelector('meta[name="csrf-token"]')?.content || ''
const showRunModal = ref(false)

const initialRunId = new URLSearchParams(window.location.search).get('run_id') || props.runs[0]?.id || null
const activeRunId = ref(initialRunId ? Number(initialRunId) : null)
const runStatus = ref(props.runs[0]?.status || null)
const logs = ref([])
const loading = ref(false)
const logEl = ref(null)

let polling = false
let pollTimerId = null
let seenCount = 0

function timeAgo(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  const now = new Date()
  const diff = Math.floor((now - d) / 1000)
  if (diff < 60) return 'just now'
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago'
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago'
  return Math.floor(diff / 86400) + 'd ago'
}

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatTime(ts) {
  return new Date(ts).toTimeString().slice(0, 8)
}

function scrollBottom() {
  nextTick(() => {
    if (logEl.value) logEl.value.scrollTop = logEl.value.scrollHeight
  })
}

async function loadLogs(runId, initial = false) {
  if (initial) loading.value = true
  try {
    const r = await fetch(`/configuration/synchronizer/runs/${runId}/logs`)
    const d = await r.json()
    const lines = d.log_lines || []
    if (lines.length > seenCount) {
      logs.value.push(...lines.slice(seenCount))
      seenCount = lines.length
      scrollBottom()
    }
    runStatus.value = d.status
    return d.status
  } catch (e) {
    if (initial) logs.value = [{ t: Date.now(), level: 'error', msg: 'Failed to load logs: ' + e.message }]
    return null
  } finally {
    if (initial) loading.value = false
  }
}

function startPolling(runId) {
  if (polling) return
  polling = true
  const tick = async () => {
    if (!polling || activeRunId.value !== runId) return
    const status = await loadLogs(runId)
    if (polling && (status === 'pending' || status === 'running')) {
      pollTimerId = setTimeout(tick, 400)
    } else {
      polling = false
    }
  }
  pollTimerId = setTimeout(tick, 400)
}

function stopPolling() {
  polling = false
  if (pollTimerId) { clearTimeout(pollTimerId); pollTimerId = null }
}

function selectRun(runId) {
  if (activeRunId.value === runId) return
  stopPolling()
  activeRunId.value = runId
  logs.value = []
  seenCount = 0
  runStatus.value = null
  window.history.pushState({}, '', `?run_id=${runId}`)
  loadLogs(runId, true).then(status => {
    if (status === 'pending' || status === 'running') startPolling(runId)
  })
}

async function doRun(mode) {
  showRunModal.value = false
  try {
    const res = await fetch(`/configuration/synchronizer/connections/${props.conn.id}/run`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ mode }),
    })
    const data = await res.json()
    if (data.run_id) {
      router.visit(`/configuration/synchronizer/connections/${props.conn.id}?run_id=${data.run_id}`)
    }
  } catch (e) {
    alert('Error: ' + e.message)
  }
}

async function stopConnection() {
  await fetch(`/configuration/synchronizer/connections/${props.conn.id}/stop`, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': csrf },
  })
  setTimeout(() => router.reload(), 800)
}

onMounted(() => {
  if (activeRunId.value) {
    loadLogs(activeRunId.value, true).then(status => {
      if (status === 'pending' || status === 'running') startPolling(activeRunId.value)
    })
  }
})

onUnmounted(() => {
  stopPolling()
})
</script>
