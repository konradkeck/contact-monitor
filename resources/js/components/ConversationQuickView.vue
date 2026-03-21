<template>
  <Modal :show="show" @close="$emit('close')" size="full">
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="w-6 h-6 border-2 border-gray-200 border-t-brand-600 rounded-full animate-spin" />
    </div>

    <template v-else-if="data">
      <div class="px-5 pt-4 pb-3 border-b border-gray-100">
        <div class="flex items-center gap-2">
          <span v-html="data.conversation.channel_icon" />
          <h3 class="text-base font-semibold text-gray-800 truncate">
            {{ data.conversation.subject || '(no subject)' }}
          </h3>
          <span v-if="data.conversation.company" class="text-xs text-gray-400 shrink-0">
            {{ data.conversation.company.name }}
          </span>
          <span v-if="data.date" class="text-xs bg-gray-100 text-gray-500 rounded px-2 py-0.5 shrink-0">
            {{ data.date }}
          </span>
        </div>

        <!-- Email headers -->
        <div v-if="data.isEmail && data.emailFrom" class="mt-2 text-xs text-gray-500 space-y-0.5">
          <div><span class="font-medium text-gray-600">From:</span> {{ data.emailFrom }}</div>
          <div v-if="data.emailTo"><span class="font-medium text-gray-600">To:</span> {{ data.emailTo }}</div>
          <div v-if="data.emailCc"><span class="font-medium text-gray-600">CC:</span> {{ data.emailCc }}</div>
        </div>

        <!-- Preview count -->
        <div v-if="data.preview || data.date" class="mt-2 text-xs text-gray-400">
          Showing {{ data.messages.length }} of {{ data.conversation.message_count }} messages
        </div>
      </div>

      <div ref="messagesContainer" class="flex-1 overflow-y-auto p-5">
        <ConversationMessages
          :messages="data.messages"
          :replies="data.replies"
          :discordMentionMap="data.discordMentionMap"
          :slackMentionMap="data.slackMentionMap"
          :channelType="data.conversation.channel_type"
          :channelLabel="data.channelLabel"
          :usesMarkdown="data.usesMarkdown"
          :ticketDisplay="data.ticketDisplay"
          :canWrite="$page.props.auth?.permissions?.data_write"
        />
      </div>

      <div class="px-5 py-3 border-t border-gray-100 flex justify-end">
        <a :href="data.conversation.show_url" class="btn btn-primary btn-sm">
          View full conversation
        </a>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Modal from './Modal.vue'
import ConversationMessages from './ConversationMessages.vue'

const props = defineProps({
  show: Boolean,
  src: String,
})

const emit = defineEmits(['close'])
const page = usePage()

const loading = ref(false)
const data = ref(null)
const messagesContainer = ref(null)

watch(() => props.src, async (newSrc) => {
  if (!newSrc) return
  loading.value = true
  data.value = null

  try {
    const sep = newSrc.includes('?') ? '&' : '?'
    const resp = await fetch(newSrc + sep + 'json=1', {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    data.value = await resp.json()
  } catch (e) {
    console.error('ConversationQuickView load error:', e)
  } finally {
    loading.value = false
    await nextTick()
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  }
})

watch(() => props.show, (v) => {
  if (!v) { data.value = null }
})
</script>
