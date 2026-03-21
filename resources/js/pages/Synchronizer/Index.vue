<template>
  <AppLayout>
    <Head title="Connections" />

    <!-- Run All Modal -->
    <div v-if="showRunAllModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay" @click.self="showRunAllModal = false">
      <div class="bg-white rounded-xl shadow-xl w-80" @click.stop>
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <span class="font-semibold text-gray-800 text-sm">Run all connections</span>
          <button @click="showRunAllModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div class="p-4 flex flex-col gap-3">
          <button @click="doRunAll('partial')" class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-brand-400 hover:bg-brand-50 text-left transition group">
            <span class="mt-0.5 text-brand-600 group-hover:text-brand-700">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </span>
            <div>
              <div class="font-semibold text-gray-800 text-sm">Partial sync</div>
              <div class="text-xs text-gray-400 mt-0.5">Fetches only new data since the last run.</div>
            </div>
          </button>
          <button @click="doRunAll('full')" class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-gray-400 hover:bg-gray-50 text-left transition group">
            <span class="mt-0.5 text-gray-500 group-hover:text-gray-700">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0l-4-4m4 4l-4 4"/></svg>
            </span>
            <div>
              <div class="font-semibold text-gray-800 text-sm">Full sync</div>
              <div class="text-xs text-gray-400 mt-0.5">Re-imports everything from scratch. Slower.</div>
            </div>
          </button>
        </div>
      </div>
    </div>

    <!-- Run Single Modal -->
    <div v-if="runModalConnId" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay" @click.self="runModalConnId = null">
      <div class="bg-white rounded-xl shadow-xl w-80" @click.stop>
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <span class="font-semibold text-gray-800 text-sm">Start sync</span>
          <button @click="runModalConnId = null" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div class="p-4 flex flex-col gap-3">
          <button @click="doRun('partial')" class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-brand-400 hover:bg-brand-50 text-left transition group">
            <span class="mt-0.5 text-brand-600 group-hover:text-brand-700">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </span>
            <div>
              <div class="font-semibold text-gray-800 text-sm">Partial sync</div>
              <div class="text-xs text-gray-400 mt-0.5">Fetches only new data since the last run.</div>
            </div>
          </button>
          <button @click="doRun('full')" class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-gray-400 hover:bg-gray-50 text-left transition group">
            <span class="mt-0.5 text-gray-500 group-hover:text-gray-700">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0l-4-4m4 4l-4 4"/></svg>
            </span>
            <div>
              <div class="font-semibold text-gray-800 text-sm">Full sync</div>
              <div class="text-xs text-gray-400 mt-0.5">Re-imports everything from scratch.</div>
            </div>
          </button>
        </div>
      </div>
    </div>

    <div class="page-header">
      <div>
        <div class="flex items-center gap-4">
          <h1 class="page-title">Connections</h1>
          <template v-if="servers.length > 1">
            <select class="input text-xs py-1 px-2 w-auto" :value="activeServer?.id" @change="switchServer($event.target.value)">
              <option v-for="srv in servers" :key="srv.id" :value="srv.id">{{ srv.name }}</option>
            </select>
          </template>
          <span v-else-if="activeServer" class="text-xs text-gray-400">{{ activeServer.name }}</span>
        </div>
        <p class="text-xs text-gray-400 mt-0.5">Manage the integrations that feed data into Contact Monitor.</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="showRunAllModal = true" class="btn btn-secondary btn-sm">
          <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3l14 9-14 9V3z"/></svg>
          Run All
        </button>
        <button @click="killAll" class="btn btn-danger btn-sm">
          <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
          Kill all runs
        </button>
        <Link :href="refreshUrl" class="btn btn-secondary btn-sm">
          <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          Refresh
        </Link>
        <Link :href="createUrl" class="btn btn-primary">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          New Connection
        </Link>
      </div>
    </div>

    <div v-if="error" class="card p-4 mb-4 text-sm alert-danger">
      <strong>Connection error:</strong> {{ error }}
    </div>

    <div class="card-xl-overflow">
      <table class="w-full text-sm">
        <thead class="tbl-header">
          <tr>
            <th class="px-4 py-2.5 text-left">Connection</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-left">Integration</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-left">Schedule</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-left">Last run</th>
            <th class="px-4 py-2.5 text-left">Status</th>
            <th class="px-4 py-2.5 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="conn in connections" :key="conn.id" class="tbl-row">
            <td class="px-4 py-3">
              <Link :href="`/configuration/synchronizer/connections/${conn.id}`" class="font-medium text-gray-900 hover:text-brand-700 transition">
                {{ conn.name }}
              </Link>
              <div class="text-xs text-gray-400 mt-0.5">{{ conn.system_slug }}</div>
            </td>
            <td class="col-mobile-hidden px-4 py-3">
              <span :class="'badge badge-sync-' + conn.type">
                {{ typeLabels[conn.type] || conn.type }}
              </span>
            </td>
            <td class="col-mobile-hidden px-4 py-3 text-xs text-gray-500">
              <template v-if="conn.type === 'metricscube'">
                <span v-if="conn.settings?.whmcs_connection_id" class="text-gray-400">Runs with WHMCS</span>
                <span v-else class="text-red-600">Missing linked WHMCS</span>
              </template>
              <template v-else>
                {{ conn.schedule_label }}
                <div v-if="conn.next_run_at" class="text-gray-400">next: {{ timeAgo(conn.next_run_at) }}</div>
              </template>
            </td>
            <td class="col-mobile-hidden px-4 py-3 text-xs text-gray-500">
              <template v-if="conn.latest_run">
                <span :title="conn.latest_run.created_at">{{ timeAgo(conn.latest_run.created_at) }}</span>
                <span v-if="conn.latest_run.duration_seconds" class="text-gray-400"> &middot; {{ conn.latest_run.duration_seconds }}s</span>
              </template>
              <span v-else class="text-gray-300">Never</span>
            </td>
            <td class="px-4 py-3">
              <span v-if="connStatus(conn)" :class="'badge badge-status-' + connStatus(conn)">
                <span v-if="connStatus(conn) === 'running'" class="inline-block w-1.5 h-1.5 rounded-full mr-0.5 animate-pulse bg-current"></span>
                {{ connStatus(conn) }}
              </span>
              <span v-else class="text-gray-300 text-xs">&mdash;</span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="row-actions-desktop items-center justify-end gap-1.5">
                <template v-if="conn.type !== 'metricscube'">
                  <button v-if="isActive(conn)" @click="stopRun(conn.id)" class="btn btn-danger btn-sm">Stop</button>
                  <button v-else @click="runModalConnId = conn.id" class="btn btn-secondary btn-sm">
                    <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3l14 9-14 9V3z"/></svg>
                    Run
                  </button>
                </template>
                <Link :href="`/configuration/synchronizer/connections/${conn.id}`" class="btn btn-muted btn-sm">Logs</Link>
                <Link :href="`/configuration/synchronizer/connections/${conn.id}/edit`" class="btn btn-muted btn-sm">Edit</Link>
                <button @click="duplicateConn(conn.id)" class="btn btn-muted btn-sm">Copy</button>
                <button @click="deleteConn(conn)" class="btn btn-danger btn-sm">
                  <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="connections.length === 0">
            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">No connections found.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({
  connections: { type: Array, default: () => [] },
  error: { type: String, default: null },
  servers: { type: Array, default: () => [] },
  activeServer: { type: Object, default: null },
})

const typeLabels = {
  whmcs: 'WHMCS', gmail: 'Gmail', imap: 'IMAP',
  metricscube: 'MetricsCube', discord: 'Discord', slack: 'Slack',
}

const showRunAllModal = ref(false)
const runModalConnId = ref(null)
const liveStatuses = ref({})
let pollTimer = null

const serverParam = computed(() => props.activeServer ? `?server=${props.activeServer.id}` : '')
const refreshUrl = computed(() => `/configuration/synchronizer/connections${serverParam.value}`)
const createUrl = computed(() => `/configuration/synchronizer/connections/create${serverParam.value}`)

const csrf = document.querySelector('meta[name="csrf-token"]')?.content || ''

function connStatus(conn) {
  if (liveStatuses.value[conn.id]) return liveStatuses.value[conn.id]
  return conn.latest_run?.status || null
}

function isActive(conn) {
  const s = connStatus(conn)
  return s === 'pending' || s === 'running'
}

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

function switchServer(serverId) {
  router.get('/configuration/synchronizer/connections', { server: serverId })
}

async function pollStatuses() {
  try {
    const res = await fetch(`/configuration/synchronizer/connections/statuses${serverParam.value}`)
    const data = await res.json()
    if (!data.statuses) return
    let hasActive = false
    for (const [connId, info] of Object.entries(data.statuses)) {
      liveStatuses.value[connId] = info.status
      if (info.status === 'pending' || info.status === 'running') hasActive = true
    }
    if (hasActive) {
      pollTimer = setTimeout(pollStatuses, 3000)
    } else {
      pollTimer = null
    }
  } catch {
    pollTimer = setTimeout(pollStatuses, 5000)
  }
}

function startPolling() {
  if (pollTimer) return
  pollTimer = setTimeout(pollStatuses, 1500)
}

async function doRunAll(mode) {
  showRunAllModal.value = false
  await fetch(`/configuration/synchronizer/run-all${serverParam.value}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
    body: JSON.stringify({ mode }),
  })
  startPolling()
}

async function doRun(mode) {
  const connId = runModalConnId.value
  runModalConnId.value = null
  if (!connId) return
  try {
    const res = await fetch(`/configuration/synchronizer/connections/${connId}/run`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ mode }),
    })
    const data = await res.json()
    if (data.run_id) {
      router.visit(`/configuration/synchronizer/connections/${connId}?run_id=${data.run_id}`)
    }
  } catch (e) {
    alert('Error: ' + e.message)
  }
}

async function stopRun(connId) {
  await fetch(`/configuration/synchronizer/connections/${connId}/stop`, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': csrf },
  })
  startPolling()
}

async function killAll() {
  if (!confirm('Kill all active runs?')) return
  await fetch('/configuration/synchronizer/kill-all', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': csrf },
  })
  startPolling()
}

function duplicateConn(connId) {
  router.post(`/configuration/synchronizer/connections/${connId}/duplicate`)
}

function deleteConn(conn) {
  if (confirm(`Delete ${conn.name}?`)) {
    router.delete(`/configuration/synchronizer/connections/${conn.id}`)
  }
}

onMounted(() => {
  const hasActive = props.connections.some(c => {
    const s = c.latest_run?.status
    return s === 'running' || s === 'pending'
  })
  if (hasActive) startPolling()
})

onUnmounted(() => {
  if (pollTimer) clearTimeout(pollTimer)
})
</script>
