<template>
  <AppLayout>
    <Head title="Synchronizer Servers" />

    <div class="page-header">
      <div>
        <h1 class="page-title">Synchronizer Servers</h1>
        <p class="text-xs text-gray-400 mt-0.5">Register the external Synchronizer services that pull data from your integrations and push it here.</p>
      </div>
      <Link href="/configuration/synchronizer-servers/wizard" class="btn btn-primary">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        New Server
      </Link>
    </div>

    <template v-if="servers.length === 0">
      <div class="card p-12 text-center max-w-lg mx-auto mt-8">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-5 bg-slate-100">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
          </svg>
        </div>
        <h2 class="font-semibold text-gray-800 text-base mb-2">No synchronizer servers yet</h2>
        <p class="text-sm text-gray-500 mb-6 leading-relaxed">
          To start importing data, you need to connect at least one Synchronizer server.
        </p>
        <Link href="/configuration/synchronizer-servers/wizard" class="btn btn-primary">Add your first server</Link>
      </div>
    </template>

    <template v-else>
      <div class="card overflow-hidden">
        <table class="w-full text-sm">
          <thead class="tbl-header">
            <tr>
              <th class="px-4 py-2.5 text-left">Name</th>
              <th class="col-mobile-hidden px-4 py-2.5 text-left">URL</th>
              <th class="px-4 py-2.5 text-left">Status</th>
              <th class="px-4 py-2.5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="server in servers" :key="server.id" class="tbl-row">
              <td class="px-4 py-3 font-medium text-gray-900">
                {{ server.name }}
                <span class="md:hidden block text-xs text-gray-400 font-mono truncate mt-0.5">{{ server.url }}</span>
              </td>
              <td class="col-mobile-hidden px-4 py-3 text-gray-500 text-xs font-mono">{{ server.url }}</td>
              <td class="px-4 py-3">
                <span class="text-xs text-gray-400" v-if="pingStatus[server.id] === undefined">
                  <span class="inline-block w-1.5 h-1.5 rounded-full bg-gray-300 mr-1"></span>
                  Checking...
                </span>
                <span v-else-if="pingStatus[server.id]?.ok" class="text-xs">
                  <span class="inline-block w-1.5 h-1.5 rounded-full mr-1 bg-green-500"></span>
                  <span class="text-green-700">Online</span>
                  <span class="text-gray-400 ml-1.5">{{ pingStatus[server.id].connections }} integration(s)</span>
                </span>
                <span v-else class="text-xs">
                  <span class="inline-block w-1.5 h-1.5 rounded-full mr-1 bg-red-500"></span>
                  <span class="text-red-600">{{ pingStatus[server.id]?.error || 'Offline' }}</span>
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="row-actions-desktop items-center justify-end gap-1.5">
                  <Link :href="`/configuration/synchronizer/connections?server=${server.id}`" class="btn btn-muted btn-sm">Connections</Link>
                  <Link :href="`/configuration/synchronizer-servers/${server.id}/edit`" class="btn btn-muted btn-sm">Edit</Link>
                  <button type="button" class="btn btn-danger btn-sm" @click="openDelete(server)">
                    <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Delete confirmation modal -->
      <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay" @click.self="deleteTarget = null">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
          <div class="flex items-start gap-3 mb-4">
            <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 bg-red-100">
              <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
            </div>
            <div>
              <h3 class="font-semibold text-gray-900 mb-1">Remove server <span class="text-red-600">{{ deleteTarget.name }}</span>?</h3>
              <p class="text-sm text-gray-600 leading-relaxed">
                This only removes the connection from Contact Monitor. <strong>The synchronizer process itself will keep running</strong> on the remote server.
              </p>
            </div>
          </div>

          <div class="rounded-lg p-3 mb-5 text-sm alert-warning space-y-3">
            <p class="font-medium text-amber-800">To fully uninstall the synchronizer, run on the remote server:</p>
            <div>
              <p class="text-xs text-amber-700 mb-1">Stop containers and remove database volumes:</p>
              <div class="flex items-center gap-2 bg-amber-100 rounded px-2 py-1.5">
                <code class="text-xs text-amber-900 font-mono flex-1 break-all">cd {{ deleteDir }} && docker compose down -v</code>
                <button type="button" @click="copyText(`cd ${deleteDir} && docker compose down -v`)" class="shrink-0 text-amber-600 hover:text-amber-900 transition" title="Copy">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </button>
              </div>
            </div>
            <div>
              <p class="text-xs text-amber-700 mb-1">Delete all synchronizer files from disk:</p>
              <div class="flex items-center gap-2 bg-amber-100 rounded px-2 py-1.5">
                <code class="text-xs text-amber-900 font-mono flex-1 break-all">sudo rm -rf {{ deleteDir }}</code>
                <button type="button" @click="copyText(`sudo rm -rf ${deleteDir}`)" class="shrink-0 text-amber-600 hover:text-amber-900 transition" title="Copy">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </button>
              </div>
            </div>
          </div>

          <p class="text-sm text-gray-500 mb-5">
            Once removed, this server's ingest credentials will be revoked.
          </p>

          <div class="flex justify-end gap-2">
            <button type="button" @click="deleteTarget = null" class="btn btn-muted btn-sm">Cancel</button>
            <button type="button" @click="confirmDelete" class="btn btn-danger btn-sm">Delete</button>
          </div>
        </div>
      </div>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../../../layouts/AppLayout.vue'

const props = defineProps({
  servers: { type: Array, default: () => [] },
})

const pingStatus = ref({})
const deleteTarget = ref(null)

const deleteDir = computed(() => {
  if (!deleteTarget.value) return '~/contact-monitor-synchronizer'
  return deleteTarget.value.install_dir || '~/contact-monitor-synchronizer'
})

onMounted(() => {
  props.servers.forEach(async (server) => {
    try {
      const res = await fetch(`/configuration/synchronizer-servers/${server.id}/ping`)
      const data = await res.json()
      pingStatus.value[server.id] = data
    } catch (e) {
      pingStatus.value[server.id] = { ok: false, error: e.message }
    }
  })
})

function openDelete(server) {
  deleteTarget.value = server
}

function confirmDelete() {
  router.delete(`/configuration/synchronizer-servers/${deleteTarget.value.id}`, {
    onSuccess: () => { deleteTarget.value = null },
  })
}

function copyText(text) {
  navigator.clipboard.writeText(text)
}
</script>
