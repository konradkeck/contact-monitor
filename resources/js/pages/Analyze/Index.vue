<template>
  <AppLayout>
    <template #sidebar>
      <AnalyseSidebar
        :sidebar="analyseSidebar"
        :active-chat-id="null"
        :users="users"
      />
    </template>

    <div class="flex flex-1 flex-col items-center justify-center gap-6 p-8 h-full">
      <template v-if="!analyseEnabled">
        <div class="text-center max-w-md">
          <div class="mb-4 text-4xl opacity-30">
            <svg class="mx-auto w-16 h-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
            </svg>
          </div>
          <h2 class="text-xl font-semibold text-gray-700 mb-2">Analyze is not configured</h2>
          <p class="text-gray-500 text-sm">
            To use Analyze, configure AI credentials and set up the Analyze model in
            <a href="/configuration/ai" class="text-brand-600 hover:text-brand-500 underline">Configuration &rarr; AI Functionality</a>.
          </p>
        </div>
      </template>
      <template v-else>
        <div class="text-center max-w-sm">
          <h2 class="text-xl font-semibold text-gray-700 mb-4">Start a conversation</h2>
          <button
            class="btn btn-primary"
            @click="newChat"
          >
            New Conversation
          </button>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import AnalyseSidebar from '../../analyze/components/AnalyseSidebar.vue'

const props = defineProps({
  analyseEnabled: Boolean,
  activeChatId: { type: Number, default: null },
  analyseSidebar: Object,
  users: Array,
})

async function newChat() {
  const res = await fetch('/analyze/chats', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    },
    body: JSON.stringify({}),
  })
  const data = await res.json()
  if (data.chat) {
    router.visit(`/analyze/c/${data.chat.id}`)
  }
}
</script>
