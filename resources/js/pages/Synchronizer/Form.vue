<template>
  <AppLayout>
    <Head :title="isEdit ? 'Edit — ' + conn.name : 'New Connection'" />

    <div class="page-header">
      <div>
        <nav aria-label="Breadcrumb" class="page-breadcrumb">
          <Link href="/configuration/synchronizer/connections">Connections</Link>
          <template v-if="isEdit">
            <span class="sep">/</span>
            <Link :href="`/configuration/synchronizer/connections/${conn.id}`">{{ conn.name }}</Link>
            <span class="sep">/</span>
            <span class="cur" aria-current="page">Edit</span>
          </template>
          <template v-else>
            <span class="sep">/</span>
            <span class="cur" aria-current="page">New Connection</span>
          </template>
        </nav>
        <h1 class="page-title mt-1">{{ isEdit ? 'Edit Connection' : 'New Connection' }}</h1>
      </div>
    </div>

    <form @submit.prevent="submit">
      <div class="grid grid-cols-1 gap-4 max-w-2xl">

        <!-- Integration type -->
        <div class="card p-5">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Integration</div>
          <div v-if="isEdit" class="flex items-center gap-3">
            <span class="font-medium text-gray-800">{{ (integrations[conn.type] || conn.type).toUpperCase() }}</span>
            <span class="text-xs text-gray-400">Type cannot be changed after creation.</span>
          </div>
          <div v-else class="grid grid-cols-3 gap-3">
            <button v-for="(label, key) in integrations" :key="key" type="button"
                    @click="form.type = key"
                    :class="form.type === key ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-400' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                    class="flex flex-col items-center gap-2 p-4 rounded-lg border-2 transition cursor-pointer">
              <span class="text-xs font-medium text-gray-700">{{ label }}</span>
            </button>
          </div>
        </div>

        <!-- Basic -->
        <div class="card p-5 space-y-4">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Basic</div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">Name</label>
              <input type="text" v-model="form.name" required class="input"
                     @input="autoSlug">
            </div>
            <div>
              <label class="label">System slug</label>
              <input type="text" v-model="form.system_slug" required pattern="[a-z][a-z0-9_-]*"
                     class="input font-mono" @input="slugEdited = true">
              <p class="text-xs text-gray-400 mt-0.5">Lowercase letters, numbers, - _</p>
            </div>
          </div>
          <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input type="checkbox" v-model="form.is_active" :true-value="true" :false-value="false" class="rounded">
              Active
            </label>
          </div>
        </div>

        <!-- Schedule -->
        <div v-if="form.type !== 'metricscube'" class="card p-5 space-y-3">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Schedule</div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="flex items-center gap-2 text-sm mb-2 cursor-pointer">
                <input type="checkbox" v-model="form.schedule_enabled" :true-value="true" :false-value="false" class="rounded">
                Partial sync enabled
              </label>
              <input type="text" v-model="form.schedule_cron" class="input font-mono text-xs" placeholder="*/30 * * * *">
            </div>
            <div>
              <label class="flex items-center gap-2 text-sm mb-2 cursor-pointer">
                <input type="checkbox" v-model="form.schedule_full_enabled" :true-value="true" :false-value="false" class="rounded">
                Full sync enabled
              </label>
              <input type="text" v-model="form.schedule_full_cron" class="input font-mono text-xs" placeholder="0 3 * * 0">
            </div>
          </div>
        </div>

        <!-- WHMCS settings -->
        <div v-if="form.type === 'whmcs'" class="card p-5 space-y-3">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">WHMCS Settings</div>
          <div>
            <label class="label">Base URL</label>
            <input type="url" v-model="form.settings.base_url" class="input" placeholder="https://whmcs.example.com">
          </div>
          <div>
            <label class="label">Admin Directory</label>
            <input type="text" v-model="form.settings.admin_dir" class="input font-mono" placeholder="admin">
          </div>
          <div>
            <label class="label">API Token {{ isEdit ? '(leave blank to keep current)' : '' }}</label>
            <div class="flex gap-2">
              <input :type="whmcsTokenVisible ? 'text' : 'password'" v-model="form.settings.token" class="input font-mono flex-1" autocomplete="off">
              <button type="button" @click="generateToken" class="btn btn-secondary btn-sm shrink-0">Generate</button>
              <button type="button" @click="copyToken" class="btn btn-secondary btn-sm shrink-0">{{ tokenCopied ? 'Copied!' : 'Copy' }}</button>
            </div>
          </div>
          <div>
            <label class="label">Entities (one per line)</label>
            <textarea v-model="form.settings.entities" rows="4" class="input font-mono text-xs"></textarea>
          </div>
        </div>

        <!-- Gmail settings -->
        <div v-if="form.type === 'gmail'" class="card p-5 space-y-3">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Gmail Settings</div>
          <div class="grid grid-cols-2 gap-4">
            <div><label class="label">Client ID</label><input type="text" v-model="form.settings.client_id" class="input font-mono text-xs"></div>
            <div><label class="label">Client Secret {{ isEdit ? '(blank = keep)' : '' }}</label><input type="password" v-model="form.settings.client_secret" class="input font-mono text-xs" autocomplete="off"></div>
          </div>
          <div><label class="label">Subject email</label><input type="email" v-model="form.settings.subject_email" class="input"></div>
          <div><label class="label">Query filter</label><input type="text" v-model="form.settings.query" class="input font-mono text-xs" placeholder="in:inbox"></div>
          <div><label class="label">Excluded labels (one per line)</label><textarea v-model="form.settings.excluded_labels" rows="3" class="input font-mono text-xs"></textarea></div>
          <div class="grid grid-cols-3 gap-4">
            <div><label class="label">Page size</label><input type="number" v-model="form.settings.page_size" class="input" min="1"></div>
            <div><label class="label">Max pages (0=all)</label><input type="number" v-model="form.settings.max_pages" class="input" min="0"></div>
            <div><label class="label">Concurrent requests</label><input type="number" v-model="form.settings.concurrent_requests" class="input" min="1" max="100"></div>
          </div>
        </div>

        <!-- IMAP settings -->
        <div v-if="form.type === 'imap'" class="card p-5 space-y-3">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">IMAP Settings</div>
          <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2"><label class="label">Host</label><input type="text" v-model="form.settings.host" class="input" placeholder="imap.example.com"></div>
            <div><label class="label">Port</label><input type="number" v-model="form.settings.port" class="input"></div>
          </div>
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="label">Encryption</label>
              <select v-model="form.settings.encryption" class="input">
                <option value="ssl">SSL</option><option value="tls">TLS</option><option value="none">NONE</option>
              </select>
            </div>
            <div><label class="label">Username</label><input type="text" v-model="form.settings.username" class="input"></div>
            <div><label class="label">Password {{ isEdit ? '(blank = keep)' : '' }}</label><input type="password" v-model="form.settings.password" class="input" autocomplete="off"></div>
          </div>
          <div><label class="label">Excluded mailboxes (one per line)</label><textarea v-model="form.settings.excluded_mailboxes" rows="3" class="input font-mono text-xs"></textarea></div>
          <div class="grid grid-cols-2 gap-4">
            <div><label class="label">Batch size</label><input type="number" v-model="form.settings.batch_size" class="input" min="1"></div>
            <div><label class="label">Max batches (0=all)</label><input type="number" v-model="form.settings.max_batches" class="input" min="0"></div>
          </div>
        </div>

        <!-- MetricsCube settings -->
        <div v-if="form.type === 'metricscube'" class="card p-5 space-y-3">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">MetricsCube Settings</div>
          <div class="grid grid-cols-2 gap-4">
            <div><label class="label">App key</label><input type="text" v-model="form.settings.app_key" class="input font-mono text-xs"></div>
            <div><label class="label">Connector key {{ isEdit ? '(blank = keep)' : '' }}</label><input type="password" v-model="form.settings.connector_key" class="input font-mono text-xs" autocomplete="off"></div>
          </div>
          <div>
            <label class="label">Linked WHMCS connection</label>
            <select v-model="form.settings.whmcs_connection_id" class="input">
              <option value="0">&mdash; None &mdash;</option>
              <option v-for="wc in whmcsConnections" :key="wc.id" :value="wc.id">{{ wc.name }} ({{ wc.system_slug }})</option>
            </select>
          </div>
        </div>

        <!-- Discord settings -->
        <div v-if="form.type === 'discord'" class="card p-5 space-y-3">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Discord Settings</div>
          <div><label class="label">Bot token {{ isEdit ? '(blank = keep)' : '' }}</label><input type="password" v-model="form.settings.bot_token" class="input font-mono text-xs" autocomplete="off"></div>
          <div class="grid grid-cols-2 gap-4">
            <div><label class="label">Guild allowlist (one per line, blank = all)</label><textarea v-model="form.settings.guild_allowlist" rows="3" class="input font-mono text-xs"></textarea></div>
            <div><label class="label">Channel allowlist (one per line, blank = all)</label><textarea v-model="form.settings.channel_allowlist" rows="3" class="input font-mono text-xs"></textarea></div>
          </div>
          <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input type="checkbox" v-model="form.settings.include_threads" :true-value="true" :false-value="false" class="rounded">
              Include threads
            </label>
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-gray-600">Max messages/run (0=all)</label>
              <input type="number" v-model="form.settings.max_messages_per_run" class="input w-24" min="0">
            </div>
          </div>
        </div>

        <!-- Slack settings -->
        <div v-if="form.type === 'slack'" class="card p-5 space-y-3">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Slack Settings</div>
          <div><label class="label">Bot token {{ isEdit ? '(blank = keep)' : '' }}</label><input type="password" v-model="form.settings.bot_token" class="input font-mono text-xs" autocomplete="off"></div>
          <div><label class="label">Channel allowlist (one per line, blank = all joined channels)</label><textarea v-model="form.settings.channel_allowlist" rows="3" class="input font-mono text-xs"></textarea></div>
          <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input type="checkbox" v-model="form.settings.include_threads" :true-value="true" :false-value="false" class="rounded">
              Include threads
            </label>
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-gray-600">Max messages/run (0=all)</label>
              <input type="number" v-model="form.settings.max_messages_per_run" class="input w-24" min="0">
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3">
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            {{ isEdit ? 'Save changes' : 'Create connection' }}
          </button>
          <button v-if="canTest" type="button" class="btn btn-secondary" @click="testConnection" :disabled="testing">
            <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Test connection
          </button>
          <span v-if="testStatus === 'testing'" class="text-xs text-gray-400">Testing...</span>
          <span v-else-if="testStatus === 'ok'" class="text-xs text-green-700">{{ testMsg || 'Connected' }}</span>
          <span v-else-if="testStatus === 'fail'" class="text-xs text-red-600">{{ testMsg || 'Failed' }}</span>
          <Link :href="isEdit ? `/configuration/synchronizer/connections/${conn.id}` : '/configuration/synchronizer/connections'" class="btn btn-muted">Cancel</Link>
        </div>
      </div>
    </form>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({
  conn: { type: Object, default: null },
  isEdit: { type: Boolean, default: false },
  type: { type: String, default: 'whmcs' },
  integrations: { type: Object, default: () => ({}) },
  whmcsConnections: { type: Array, default: () => [] },
})

const s = props.conn?.settings || {}
const arrToStr = (v) => Array.isArray(v) ? v.join('\n') : (v || '')

const form = reactive({
  type: props.isEdit ? props.conn.type : props.type,
  name: props.conn?.name || '',
  system_slug: props.conn?.system_slug || '',
  is_active: props.conn?.is_active ?? true,
  schedule_enabled: props.conn?.schedule_enabled ?? false,
  schedule_full_enabled: props.conn?.schedule_full_enabled ?? false,
  schedule_cron: props.conn?.schedule_cron || '*/30 * * * *',
  schedule_full_cron: props.conn?.schedule_full_cron || '0 3 * * 0',
  settings: {
    // WHMCS
    base_url: s.base_url || '',
    admin_dir: s.admin_dir || 'admin',
    token: '',
    entities: arrToStr(s.entities) || "clients\ncontacts\nservices\ntickets",
    // Gmail
    client_id: s.client_id || '',
    client_secret: '',
    subject_email: s.subject_email || '',
    query: s.query || '',
    excluded_labels: arrToStr(s.excluded_labels),
    page_size: s.page_size ?? 100,
    max_pages: s.max_pages ?? 0,
    concurrent_requests: s.concurrent_requests ?? 10,
    // IMAP
    host: s.host || '',
    port: s.port ?? 993,
    encryption: s.encryption || 'ssl',
    username: s.username || '',
    password: '',
    excluded_mailboxes: arrToStr(s.excluded_mailboxes),
    batch_size: s.batch_size ?? 100,
    max_batches: s.max_batches ?? 0,
    // MetricsCube
    app_key: s.app_key || '',
    connector_key: '',
    whmcs_connection_id: s.whmcs_connection_id ?? 0,
    // Discord/Slack
    bot_token: '',
    guild_allowlist: arrToStr(s.guild_allowlist),
    channel_allowlist: arrToStr(s.channel_allowlist),
    include_threads: s.include_threads ?? true,
    max_messages_per_run: s.max_messages_per_run ?? 0,
  },
})

const slugEdited = ref(props.isEdit)
const submitting = ref(false)
const testing = ref(false)
const testStatus = ref(null)
const testMsg = ref('')
const whmcsTokenVisible = ref(false)
const tokenCopied = ref(false)

const canTest = computed(() => ['whmcs', 'imap', 'discord', 'slack', 'metricscube'].includes(form.type))

const csrf = document.querySelector('meta[name="csrf-token"]')?.content || ''

function autoSlug(e) {
  if (!slugEdited.value) {
    form.system_slug = e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 50)
  }
}

function generateToken() {
  const arr = new Uint8Array(32)
  crypto.getRandomValues(arr)
  form.settings.token = Array.from(arr).map(b => b.toString(16).padStart(2, '0')).join('')
  whmcsTokenVisible.value = true
}

function copyToken() {
  if (!form.settings.token) return
  navigator.clipboard.writeText(form.settings.token).then(() => {
    tokenCopied.value = true
    setTimeout(() => { tokenCopied.value = false }, 1500)
  })
}

function submit() {
  submitting.value = true
  const url = props.isEdit
    ? `/configuration/synchronizer/connections/${props.conn.id}`
    : '/configuration/synchronizer/connections'

  router[props.isEdit ? 'put' : 'post'](url, form, {
    onFinish: () => { submitting.value = false },
  })
}

async function testConnection() {
  testing.value = true
  testStatus.value = 'testing'
  testMsg.value = ''

  const serverParam = new URLSearchParams(window.location.search).get('server')

  try {
    const formData = new FormData()
    formData.append('type', form.type)
    formData.append('name', form.name)
    formData.append('system_slug', form.system_slug)
    for (const [k, v] of Object.entries(form.settings)) {
      formData.append(`settings[${k}]`, v)
    }

    const testUrl = '/configuration/synchronizer/connections/test' + (serverParam ? `?server=${serverParam}` : '')
    const res = await fetch(testUrl, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf },
      body: formData,
    })
    const data = await res.json()
    testStatus.value = data.ok ? 'ok' : 'fail'
    testMsg.value = data.ok ? ('\u2713 ' + (data.message || 'Connected')) : ('\u2717 ' + (data.error || 'Failed'))
  } catch (e) {
    testStatus.value = 'fail'
    testMsg.value = '\u2717 ' + e.message
  } finally {
    testing.value = false
  }
}
</script>
