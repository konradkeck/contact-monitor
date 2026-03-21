<template>
  <div v-if="success" class="flash-msg mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm flex items-center gap-3">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
      <path class="flash-check" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
    </svg>
    <span class="flex-1">{{ success }}</span>
    <button @click="dismissSuccess" aria-label="Dismiss"
            class="opacity-40 hover:opacity-70 transition-opacity text-lg leading-none shrink-0">&times;</button>
  </div>
  <div v-if="error" class="flash-msg mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm flex items-center gap-3">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008zm9.303-3.376c-.866 1.5.217 3.374 1.948 3.374H2.749c-1.73 0-2.813-1.874-1.948-3.374L10.052 3.378c.866-1.5 3.032-1.5 3.898 0L21.303 13.374z"/>
    </svg>
    <span class="flex-1">{{ error }}</span>
    <button @click="dismissError" aria-label="Dismiss"
            class="opacity-40 hover:opacity-70 transition-opacity text-lg leading-none shrink-0">&times;</button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useFlash } from '../composables/useFlash.js'

const { success: flashSuccess, error: flashError } = useFlash()

const success = ref(flashSuccess.value)
const error = ref(flashError.value)

watch(flashSuccess, (v) => { success.value = v })
watch(flashError, (v) => { error.value = v })

function dismissSuccess() { success.value = null }
function dismissError() { error.value = null }
</script>
