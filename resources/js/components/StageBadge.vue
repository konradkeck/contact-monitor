<template>
  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="cls">
    {{ stage }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  stage: { type: String, default: '' },
})

const STAGE_COLORS = {
  lead:     'bg-blue-100 text-blue-700',
  prospect: 'bg-purple-100 text-purple-700',
  trial:    'bg-yellow-100 text-yellow-700',
  active:   'bg-green-100 text-green-700',
  churned:  'bg-red-100 text-red-700',
}

const cls = computed(() => STAGE_COLORS[(props.stage || '').toLowerCase()] || 'bg-gray-100 text-gray-700')
</script>
