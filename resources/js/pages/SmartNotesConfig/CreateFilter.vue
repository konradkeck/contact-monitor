<template>
  <AppLayout>
    <Head title="Add Filter — Smart Notes" />

    <div class="page-header">
      <div>
        <nav aria-label="Breadcrumb" class="page-breadcrumb">
          <Link href="/configuration/smart-notes">Smart Notes</Link>
          <span class="sep">/</span>
          <span class="cur" aria-current="page">Add Filter</span>
        </nav>
        <h1 class="page-title mt-1">Add Filter</h1>
      </div>
    </div>

    <div class="card p-5 max-w-lg">
      <form @submit.prevent="submit">
        <div class="space-y-4">
          <div>
            <label class="label">Filter Type</label>
            <select v-model="form.type" class="input w-full">
              <option value="email_message">Email Message</option>
              <option value="email_subject">Email Subject Keyword</option>
              <option value="discord_any">Discord</option>
              <option value="slack_any">Slack</option>
            </select>
          </div>

          <!-- email_message criteria -->
          <div v-if="form.type === 'email_message'" class="space-y-3">
            <div>
              <label class="label">Mailboxes <span class="text-gray-400 font-normal">(leave all unchecked to match any mailbox)</span></label>
              <p v-if="emailMailboxes.length === 0" class="text-sm text-gray-400 italic mt-1">No email connections found — filter will apply to all email messages.</p>
              <div v-else class="mt-1.5 space-y-1.5 border border-gray-200 rounded-lg p-3">
                <label v-for="mailbox in emailMailboxes" :key="mailbox.system_slug"
                       class="flex items-center gap-2.5 cursor-pointer">
                  <input type="checkbox" v-model="form.mailbox_slugs" :value="mailbox.system_slug"
                         class="rounded border-gray-300">
                  <span class="text-sm text-gray-700">
                    {{ mailbox.system_slug }}
                    <span class="text-gray-400 text-xs">({{ mailbox.system_type }})</span>
                  </span>
                </label>
              </div>
            </div>
            <div>
              <label class="label">Email Address</label>
              <input type="text" v-model="form.address" class="input w-full" placeholder="notes@example.com">
              <p class="text-xs text-gray-400 mt-1">Messages where this address appears as sender or recipient will be captured.</p>
              <p v-if="form.errors.address" class="text-xs text-red-500 mt-1">{{ form.errors.address }}</p>
            </div>
            <div>
              <label class="label">Direction</label>
              <select v-model="form.direction" class="input w-full">
                <option value="any">Any (to or from)</option>
                <option value="from">From this address</option>
                <option value="to">To this address</option>
              </select>
            </div>
          </div>

          <!-- email_subject criteria -->
          <div v-if="form.type === 'email_subject'">
            <label class="label">Subject Keyword</label>
            <input type="text" v-model="form.keyword" class="input w-full" placeholder="NOTES">
            <p v-if="form.errors.keyword" class="text-xs text-red-500 mt-1">{{ form.errors.keyword }}</p>
          </div>

          <!-- discord_any criteria -->
          <div v-if="form.type === 'discord_any'">
            <label class="label">Discord Connection <span class="text-gray-400 font-normal">(leave blank for all)</span></label>
            <p v-if="discordConnections.length === 0" class="text-sm text-gray-400 italic mt-1">No Discord connections found — filter will apply to all Discord messages when available.</p>
            <select v-else v-model="form.connection_slug" class="input w-full">
              <option value="">All Discord connections</option>
              <option v-for="slug in discordConnections" :key="slug" :value="slug">{{ slug }}</option>
            </select>
          </div>

          <!-- slack_any criteria -->
          <div v-if="form.type === 'slack_any'">
            <label class="label">Slack Workspace <span class="text-gray-400 font-normal">(leave blank for all)</span></label>
            <p v-if="slackWorkspaces.length === 0" class="text-sm text-gray-400 italic mt-1">No Slack workspaces found — filter will apply to all Slack messages when available.</p>
            <select v-else v-model="form.connection_slug" class="input w-full">
              <option value="">All Slack workspaces</option>
              <option v-for="slug in slackWorkspaces" :key="slug" :value="slug">{{ slug }}</option>
            </select>
          </div>

          <div class="flex items-center gap-2">
            <input type="checkbox" v-model="form.as_internal_note" :true-value="1" :false-value="0"
                   id="f-internal" class="rounded border-gray-300">
            <label for="f-internal" class="text-sm text-gray-700 cursor-pointer">Tag matched notes as <strong>Internal Notes</strong></label>
          </div>
        </div>

        <div class="flex gap-2 mt-6">
          <button type="submit" class="btn btn-primary" :disabled="form.processing">Add Filter</button>
          <Link href="/configuration/smart-notes" class="btn btn-secondary">Cancel</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

defineProps({
  emailMailboxes: { type: Array, default: () => [] },
  discordConnections: { type: Array, default: () => [] },
  slackWorkspaces: { type: Array, default: () => [] },
})

const form = useForm({
  type: 'email_message',
  mailbox_slugs: [],
  address: '',
  direction: 'any',
  keyword: '',
  connection_slug: '',
  as_internal_note: 0,
})

function submit() {
  form.post('/configuration/smart-notes/filters')
}
</script>
