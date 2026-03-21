<template>
  <AppLayout>
    <Head title="MCP Server" />

    <div class="page-header">
      <h1 class="page-title">MCP Server</h1>
    </div>

    <!-- API key flash -->
    <div v-if="apiKeyPlain" class="alert-warning mb-4">
      <p class="font-semibold text-sm mb-1">New API Key (copy now — will not be shown again):</p>
      <code class="font-mono text-sm break-all select-all">{{ apiKeyPlain }}</code>
    </div>

    <!-- Tabs -->
    <div class="flex gap-0 border-b border-gray-200 mb-5">
      <Link href="/configuration/mcp-server"
            :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition-colors',
                     tab === 'settings' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700']">
        Settings
      </Link>
      <Link href="/configuration/mcp-log"
            :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition-colors',
                     tab === 'log' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700']">
        Log
      </Link>
    </div>

    <!-- Settings Tab -->
    <template v-if="tab === 'settings'">
      <!-- Enable / Disable -->
      <div class="card p-5 mb-5 max-w-2xl">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="font-medium text-sm text-gray-800">MCP Server</p>
            <p class="text-xs text-gray-500 mt-0.5">Enable the Model Context Protocol server for AI integration (JSON-RPC 2.0).</p>
          </div>
          <button @click="toggleEnabled" :class="['btn btn-sm', enabled ? 'btn-danger' : 'btn-primary']">
            {{ enabled ? 'Disable' : 'Enable' }}
          </button>
        </div>
        <div v-if="enabled" class="mt-3 flex items-center gap-2 text-xs text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
          <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          MCP Server is active. Localhost access is always allowed without authentication.
        </div>
      </div>

      <template v-if="enabled">
        <!-- Endpoint URL -->
        <div class="card p-5 mb-5 max-w-2xl">
          <p class="font-medium text-sm text-gray-800 mb-2">Endpoint URL</p>
          <div class="flex items-center gap-2">
            <input type="text" readonly :value="endpointUrl"
                   class="input w-full font-mono text-xs bg-gray-50 cursor-text select-all"
                   @click="$event.target.select()">
            <button type="button" @click="copyEndpoint" class="btn btn-secondary btn-sm shrink-0">
              {{ copyText }}
            </button>
          </div>
          <p class="text-xs text-gray-400 mt-2">Send JSON-RPC 2.0 requests via <code class="font-mono">POST</code> to this URL.</p>
        </div>

        <!-- External Access -->
        <div class="card p-5 mb-5 max-w-2xl">
          <div class="flex items-center justify-between gap-4 mb-3">
            <div>
              <p class="font-medium text-sm text-gray-800">External Access</p>
              <p class="text-xs text-gray-500 mt-0.5">Allow connections from outside localhost. Requires an API key.</p>
            </div>
            <button @click="toggleExternal" :class="['btn btn-sm', externalEnabled ? 'btn-danger' : 'btn-secondary']">
              {{ externalEnabled ? 'Disable External' : 'Enable External' }}
            </button>
          </div>

          <div v-if="externalEnabled" class="border-t border-gray-100 pt-3 mt-1">
            <p class="text-xs font-medium text-gray-700 mb-2">API Key</p>
            <p v-if="hasApiKey" class="text-xs text-gray-500 mb-2">An API key is configured. To rotate it, generate a new one below.</p>
            <p v-else class="text-xs text-amber-600 mb-2">No API key configured. External connections will be rejected until you generate one.</p>
            <button @click="regenerateKey" class="btn btn-secondary btn-sm">
              {{ hasApiKey ? 'Regenerate Key' : 'Generate Key' }}
            </button>
            <p class="text-xs text-gray-400 mt-2">Use <code class="font-mono">Authorization: Bearer &lt;key&gt;</code> header in requests.</p>
          </div>
        </div>
      </template>
    </template>

    <!-- Log Tab -->
    <template v-if="tab === 'log'">
      <div class="card-xl-overflow">
        <table class="w-full text-sm">
          <thead class="tbl-header">
            <tr>
              <th class="px-4 py-2.5 text-left">Tool</th>
              <th class="px-4 py-2.5 text-left">Context</th>
              <th class="px-4 py-2.5 text-left">Entity</th>
              <th class="px-4 py-2.5 text-left">User</th>
              <th class="px-4 py-2.5 text-left">IP</th>
              <th class="px-4 py-2.5 text-left">Time</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in logs?.data" :key="log.id" class="tbl-row">
              <td class="px-4 py-3 font-mono text-xs font-medium text-gray-700">{{ log.tool_name }}</td>
              <td class="px-4 py-3">
                <span :class="['badge', contextBadge(log.context)]">{{ log.context }}</span>
              </td>
              <td class="px-4 py-3 text-xs text-gray-500">
                <template v-if="log.entity_type && log.entity_id">
                  {{ entityBasename(log.entity_type) }} #{{ log.entity_id }}
                </template>
                <span v-else class="text-gray-300">&mdash;</span>
              </td>
              <td class="px-4 py-3 text-xs text-gray-600">{{ log.user?.name || 'API' }}</td>
              <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ log.ip_address }}</td>
              <td class="px-4 py-3 text-xs text-gray-500">{{ log.created_at_human }}</td>
            </tr>
            <tr v-if="!logs?.data?.length">
              <td colspan="6" class="px-4 py-8 text-center empty-state italic">No MCP actions recorded yet.</td>
            </tr>
          </tbody>
        </table>
        <div v-if="logs?.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex gap-1 flex-wrap">
          <template v-for="link in logs.links" :key="link.label">
            <Link v-if="link.url" :href="link.url" class="px-2.5 py-1 text-xs rounded"
                  :class="link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
                  v-html="link.label" />
            <span v-else class="px-2.5 py-1 text-xs text-gray-300" v-html="link.label" />
          </template>
        </div>
      </div>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../layouts/AppLayout.vue'

const props = defineProps({
  enabled: Boolean,
  externalEnabled: Boolean,
  hasApiKey: Boolean,
  endpointUrl: String,
  tab: { type: String, default: 'settings' },
  logs: { type: Object, default: null },
})

const page = usePage()
const apiKeyPlain = computed(() => page.props.flash?.api_key_plain)

const copyText = ref('Copy')

function copyEndpoint() {
  navigator.clipboard.writeText(props.endpointUrl).then(() => {
    copyText.value = 'Copied!'
    setTimeout(() => { copyText.value = 'Copy' }, 2000)
  })
}

function toggleEnabled() {
  router.post('/configuration/mcp-server/settings', {
    mcp_enabled: props.enabled ? 0 : 1,
    mcp_external_enabled: props.externalEnabled ? 1 : 0,
  })
}

function toggleExternal() {
  router.post('/configuration/mcp-server/settings', {
    mcp_enabled: 1,
    mcp_external_enabled: props.externalEnabled ? 0 : 1,
  })
}

function regenerateKey() {
  if (confirm('This will invalidate the current API key. Continue?')) {
    router.post('/configuration/mcp-server/regenerate-key')
  }
}

function contextBadge(context) {
  if (context === 'chat') return 'badge-blue'
  if (context === 'automated') return 'badge-green'
  return 'badge-gray'
}

function entityBasename(type) {
  if (!type) return ''
  return type.split('\\').pop()
}
</script>
