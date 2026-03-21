<template>
  <span class="inline-flex items-center gap-1 text-xs">
    <span class="w-2 h-2 rounded-full shrink-0" :class="dotClass" />
    <span v-if="label">{{ typeLabel }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  type: { type: String, required: true },
  label: { type: Boolean, default: true },
})

const dotClass = computed(() => {
  const map = {
    whmcs: 'bg-orange-400',
    metricscube: 'bg-blue-400',
    slack: 'bg-green-400',
    discord: 'bg-indigo-400',
    imap: 'bg-purple-400',
    gmail: 'bg-red-400',
    email: 'bg-purple-400',
    ticket: 'bg-yellow-400',
  }
  return map[props.type] || 'bg-gray-400'
})

const typeLabel = computed(() => {
  const map = {
    whmcs: 'WHMCS',
    metricscube: 'MetricsCube',
    slack: 'Slack',
    discord: 'Discord',
    imap: 'IMAP',
    gmail: 'Gmail',
    email: 'Email',
    ticket: 'Ticket',
  }
  return map[props.type] || props.type
})
</script>
