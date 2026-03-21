<template>
  <AppLayout>
    <div class="w-full md:max-w-[80%]">

      <!-- Page header -->
      <div class="page-header">
        <div class="flex items-center gap-3">
          <span class="inline-flex items-center gap-1 shrink-0">
            <span v-html="conversation.channel_icon" />
            <span v-if="conversation.system_icon" v-html="conversation.system_icon" />
          </span>
          <div>
            <nav aria-label="Breadcrumb" class="page-breadcrumb">
              <template v-if="backLink">
                <a :href="backLink.url">{{ backLink.label }}</a>
                <span class="sep">/</span>
              </template>
              <a href="/conversations">Conversations</a>
              <span class="sep">/</span>
              <span class="cur" aria-current="page">{{ conversation.system_slug }}</span>
            </nav>
            <h1 class="page-title mt-1 leading-tight">
              <template v-if="conversation.company">
                <a :href="`/companies/${conversation.company.id}`" class="link">{{ conversation.company.name }}</a>
                <span v-if="conversation.subject" class="text-gray-300 font-normal mx-1">—</span>
              </template>
              <span v-if="conversation.subject" class="text-gray-800 font-semibold">{{ conversation.subject }}</span>
              <span v-else-if="!conversation.company" class="text-gray-400 font-normal italic">No subject</span>
            </h1>
          </div>
        </div>

        <!-- Debug button -->
        <div class="relative shrink-0">
          <button @click="debugOpen = !debugOpen"
                  class="text-xs text-gray-300 hover:text-gray-500 px-2 py-1 rounded transition font-mono"
                  title="Debug info">···</button>
          <div v-show="debugOpen" class="absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-30 p-4 w-80">
            <div v-for="(v, k) in debugInfo" :key="k" class="flex gap-2 py-0.5">
              <span class="text-xs text-gray-400 font-mono shrink-0 w-36">{{ k }}</span>
              <span class="text-xs text-gray-700 font-mono break-all">{{ v }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Meta row -->
      <div class="bg-white rounded-lg border border-gray-200 p-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-5">
        <div>
          <p class="text-xs text-gray-500 mb-0.5">Primary Contact</p>
          <p class="font-medium">
            <a v-if="conversation.primary_person" :href="`/people/${conversation.primary_person.id}`" class="link">
              {{ conversation.primary_person.full_name }}
            </a>
            <span v-else class="text-gray-400">—</span>
          </p>
        </div>
        <div>
          <p class="text-xs text-gray-500 mb-0.5">Messages</p>
          <p class="font-medium">{{ conversation.message_count }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500 mb-0.5">Started</p>
          <p class="font-medium">{{ conversation.started_at || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500 mb-0.5">Last message</p>
          <p class="font-medium">{{ conversation.last_message_at || '—' }}</p>
        </div>
      </div>

      <!-- Messages -->
      <ConversationMessages
        :messages="messages"
        :replies="replies"
        :discordMentionMap="discordMentionMap"
        :slackMentionMap="slackMentionMap"
        :channelType="conversation.channel_type"
        :channelLabel="channelLabel"
        :usesMarkdown="usesMarkdown"
        :ticketDisplay="ticketDisplay"
      />

      <!-- Notes -->
      <div class="mt-6">
        <NotesSection
          :notes="notes"
          linkableType="App\Models\Conversation"
          :linkableId="conversation.id"
        />
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import AppLayout from '../../layouts/AppLayout.vue'
import ConversationMessages from '../../components/ConversationMessages.vue'
import NotesSection from '../../components/NotesSection.vue'

const props = defineProps({
  conversation: Object,
  messages: Array,
  replies: Object,
  discordMentionMap: Object,
  slackMentionMap: Object,
  isEmail: Boolean,
  isTicket: Boolean,
  usesMarkdown: Boolean,
  channelLabel: String,
  ticketDisplay: Object,
  notes: Array,
  backLink: Object,
  debugInfo: Object,
})

const debugOpen = ref(false)
</script>
