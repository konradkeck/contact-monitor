<template>
  <div v-if="!messages.length" class="bg-white rounded-lg border border-gray-200 px-4 py-10 text-center text-gray-400 italic text-sm">
    No messages imported yet.
  </div>

  <!-- Slack / Discord / other: chat layout -->
  <div v-else-if="!isEmail && !isTicket" class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
      {{ channelLabel }} thread
    </div>
    <div class="divide-y divide-gray-50">
      <template v-for="msg in topLevelMessages" :key="msg.id">
        <!-- System message -->
        <div v-if="msg.is_system_message" class="flex justify-center py-2">
          <span class="text-xs bg-gray-100 text-gray-500 rounded-full px-3 py-1">{{ decodeEntities(msg.body_text) }}</span>
        </div>
        <!-- Normal message -->
        <div v-else class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition group">
          <img v-if="msg.avatar_url" :src="msg.avatar_url" :alt="msg.author_name"
               class="w-8 h-8 rounded-full object-cover shrink-0 mt-0.5">
          <div v-else class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-0.5"
               :class="msg.is_team ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-600'">
            {{ initials(msg.author_name) }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-baseline gap-2 mb-1">
              <span class="text-sm font-semibold" :class="msg.is_team ? 'text-brand-700' : 'text-gray-800'">
                {{ msg.author_name }}
              </span>
              <!-- Our Org toggle -->
              <OurOrgButton v-if="msg.person_id" :personId="msg.person_id" :isOurOrg="msg.person_is_our_org" :canWrite="canWrite" />
              <span class="text-xs text-gray-400">{{ formatDateTime(msg.occurred_at) }}</span>
              <span v-if="msg.edited_at" class="text-xs text-gray-300 italic">(edited)</span>
              <a v-if="msg.source_url" :href="msg.source_url" target="_blank"
                 class="opacity-0 group-hover:opacity-100 transition ml-auto text-gray-300 hover:text-gray-500">
                <ExternalLinkIcon />
              </a>
            </div>
            <MessageBody
              :bodyHtml="msg.body_html"
              :bodyText="resolveText(msg.body_text)"
              :usesMarkdown="usesMarkdown"
              className="text-sm text-gray-700 leading-relaxed" />
            <!-- Attachments -->
            <div v-if="msg.attachments?.length" class="flex flex-wrap gap-1.5 mt-2">
              <a v-for="att in msg.attachments" :key="att.url || att.name" :href="att.url || '#'" target="_blank"
                 class="flex items-center gap-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded px-2 py-1 transition">
                {{ att.name || 'Attachment' }}
              </a>
            </div>
            <!-- Thread replies -->
            <div v-if="(msg.thread_count > 0 || msgReplies(msg.id).length)" class="mt-2 border-l-2 border-gray-200 pl-3 space-y-2">
              <p v-if="!msgReplies(msg.id).length" class="text-xs text-gray-400 italic">{{ msg.thread_count }} thread replies (not loaded)</p>
              <template v-else>
                <div v-for="reply in msgReplies(msg.id)" :key="reply.id" class="flex items-start gap-2">
                  <img v-if="reply.avatar_url" :src="reply.avatar_url" :alt="reply.author_name"
                       class="w-6 h-6 rounded-full object-cover shrink-0">
                  <div v-else class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                       :class="reply.is_team ? 'bg-brand-50 text-brand-600' : 'bg-gray-100 text-gray-500'">
                    {{ initials(reply.author_name) }}
                  </div>
                  <div>
                    <span class="text-xs font-semibold text-gray-700">{{ reply.author_name }}</span>
                    <span class="text-xs text-gray-400 ml-1">{{ formatTime(reply.occurred_at) }}</span>
                    <MessageBody
                      :bodyHtml="reply.body_html"
                      :bodyText="resolveText(reply.body_text)"
                      :usesMarkdown="usesMarkdown"
                      className="text-xs text-gray-600 leading-relaxed" />
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>

  <!-- Email / Ticket: bubble layout -->
  <template v-else>
    <!-- Ticket info bar -->
    <div v-if="isTicket && ticketDisplay?.hasTicketInfo" class="bg-white rounded-lg border border-gray-200 px-4 py-2.5 mb-3 flex items-center gap-2 flex-wrap">
      <span v-if="ticketDisplay.ticketHeading" class="text-sm font-semibold text-gray-800">{{ ticketDisplay.ticketHeading }}</span>
      <Badge v-if="ticketDisplay.ticketStatus" :color="ticketDisplay.statusColor">{{ ticketDisplay.ticketStatus }}</Badge>
      <span v-if="ticketDisplay.ticketDept" class="text-xs text-gray-500">{{ ticketDisplay.ticketDept }}</span>
      <Badge v-if="ticketDisplay.priority" :color="ticketDisplay.priorityColor">{{ ticketDisplay.priority }}</Badge>
    </div>

    <div class="space-y-3">
      <template v-for="msg in messages" :key="msg.id">
        <div v-if="msg.is_system_message" class="flex justify-center">
          <span class="text-xs bg-amber-50 border border-amber-200 text-amber-700 rounded-full px-4 py-1">{{ decodeEntities(msg.body_text) }}</span>
        </div>
        <div v-else class="flex" :class="msg.is_team ? 'justify-end' : 'justify-start'">
          <div class="max-w-[70%] flex flex-col gap-1" :class="msg.is_team ? 'items-end' : 'items-start'">
            <!-- Author + time -->
            <div class="flex items-center gap-2 px-1" :class="msg.is_team ? 'flex-row-reverse' : ''">
              <img v-if="isEmail && msg.gravatar_hash"
                   :src="`https://www.gravatar.com/avatar/${msg.gravatar_hash}?d=identicon&s=48`"
                   class="w-6 h-6 rounded-full object-cover shrink-0" :alt="msg.author_name">
              <div v-else class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                   :class="msg.is_team ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-600'">
                {{ initials(msg.author_name) }}
              </div>
              <div :class="msg.is_team ? 'text-right' : ''">
                <div class="flex items-center gap-1.5" :class="msg.is_team ? 'flex-row-reverse' : ''">
                  <span class="text-xs text-gray-500 font-medium">{{ msg.author_name }}</span>
                  <span v-if="isEmail && msg.identity_value" class="text-xs text-gray-400">&lt;{{ msg.identity_value }}&gt;</span>
                  <span class="text-xs text-gray-300">{{ formatDateShort(msg.occurred_at) }}</span>
                  <OurOrgButton v-if="msg.person_id" :personId="msg.person_id" :isOurOrg="msg.person_is_our_org" :canWrite="canWrite" />
                </div>
                <div v-if="isEmail && msg.meta_to" class="text-xs text-gray-400 mt-0.5">To: {{ msg.meta_to }}</div>
              </div>
            </div>
            <!-- Bubble -->
            <component :is="msg.source_url ? 'a' : 'div'" :href="msg.source_url || undefined" :target="msg.source_url ? '_blank' : undefined"
                       :class="msg.source_url ? 'group' : ''">
              <div class="rounded-2xl px-4 py-2.5 text-sm leading-relaxed shadow-sm"
                   :class="msg.is_team ? 'bg-gray-100 text-gray-800 rounded-tr-none' : 'bg-white border border-gray-200 text-gray-800 rounded-tl-none'">
                <MessageBody :bodyHtml="msg.body_html" :bodyText="msg.body_text" :usesMarkdown="usesMarkdown" />
                <svg v-if="msg.source_url" class="inline w-3 h-3 opacity-40 ml-1 group-hover:opacity-70 transition"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
              </div>
            </component>
            <!-- Attachments -->
            <div v-if="msg.attachments?.length" class="flex flex-wrap gap-1" :class="msg.is_team ? 'justify-end' : ''">
              <a v-for="att in msg.attachments" :key="att.url || att.name" :href="att.url || '#'" target="_blank"
                 class="flex items-center gap-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded px-2 py-1 transition">
                {{ att.name || 'Attachment' }}
              </a>
            </div>
          </div>
        </div>
      </template>
    </div>
  </template>
</template>

<script setup>
import { computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import MessageBody from './MessageBody.vue'
import Badge from './Badge.vue'

const props = defineProps({
  messages: { type: Array, default: () => [] },
  replies: { type: Object, default: () => ({}) },
  discordMentionMap: { type: Object, default: () => ({}) },
  slackMentionMap: { type: Object, default: () => ({}) },
  channelType: { type: String, default: '' },
  channelLabel: { type: String, default: '' },
  usesMarkdown: { type: Boolean, default: false },
  ticketDisplay: { type: Object, default: null },
  canWrite: { type: Boolean, default: false },
})

const isEmail = computed(() => props.channelType === 'email')
const isTicket = computed(() => props.channelType === 'ticket')

const topLevelMessages = computed(() => props.messages.filter(m => !m.thread_key))

function msgReplies(msgId) {
  return props.replies[msgId] || []
}

function resolveText(text) {
  if (!text) return ''
  // Discord: <@ID> numeric
  let resolved = text.replace(/<@(\d+)>/g, (match, id) => {
    const name = props.discordMentionMap[id]
    return name ? `@${name}` : match
  })
  // Slack: <@USERID> uppercase
  resolved = resolved.replace(/<@([A-Z0-9]+)>/g, (match, id) => {
    const name = props.slackMentionMap[id.toLowerCase()]
    return name ? `@${name}` : match
  })
  return resolved
}

function decodeEntities(text) {
  if (!text) return ''
  const el = document.createElement('textarea')
  el.innerHTML = text
  return el.value
}

function initials(name) {
  return (name || '').substring(0, 2).toUpperCase()
}

function formatDateTime(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ' · ' +
         d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

function formatTime(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

function formatDateShort(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) + ' · ' +
         d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

// Our Org button sub-component
const OurOrgButton = {
  props: {
    personId: Number,
    isOurOrg: Boolean,
    canWrite: Boolean,
  },
  template: `
    <span v-if="canWrite && !isOurOrg" class="relative group/ourorg opacity-0 group-hover:opacity-100 transition shrink-0">
      <button type="button" @click.stop="markOurOrg"
              class="text-xs text-brand-500 hover:text-brand-700 underline decoration-dotted underline-offset-2 cursor-pointer">
        Set as Our Org
      </button>
      <span class="pointer-events-none absolute bottom-full left-0 mb-1.5 hidden group-hover/ourorg:block bg-gray-800 text-white text-xs rounded-lg px-2.5 py-1.5 w-52 z-20 leading-snug shadow-lg">
        Mark as part of your organization. Their messages will appear on the internal side.
      </span>
    </span>
    <span v-else-if="canWrite && isOurOrg" class="relative group/ourorg shrink-0">
      <button type="button" @click.stop="unmarkOurOrg"
              class="text-xs bg-brand-50 text-brand-600 border border-brand-200 rounded px-1.5 py-0.5 leading-none hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition">
        Our Org
      </button>
      <span class="pointer-events-none absolute bottom-full left-0 mb-1.5 hidden group-hover/ourorg:block bg-gray-800 text-white text-xs rounded-lg px-2.5 py-1.5 w-44 z-20 leading-snug shadow-lg">
        Click to unmark as Our Organization.
      </span>
    </span>
  `,
  methods: {
    markOurOrg() {
      fetch('/people/' + this.personId + '/mark-our-org', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json',
        }
      }).then(() => window.location.reload())
    },
    unmarkOurOrg() {
      fetch('/people/' + this.personId + '/unmark-our-org', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json',
        }
      }).then(() => window.location.reload())
    },
  },
}

const ExternalLinkIcon = {
  template: `
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
    </svg>
  `,
}
</script>
