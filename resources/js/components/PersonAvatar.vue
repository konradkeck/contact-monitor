<template>
  <div :class="[sizeClass, 'rounded-full flex items-center justify-center shrink-0 bg-gray-100 border border-gray-200 text-gray-500 font-medium']"
       :style="{ fontSize: fontSize }"
       :title="person.full_name">
    <img v-if="person.avatar_url"
         :src="person.avatar_url"
         :alt="person.full_name"
         :class="[sizeClass, 'rounded-full object-cover']">
    <span v-else>{{ initials }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  person: { type: Object, required: true },
  size: { type: String, default: '8' },
})

const sizeClass = computed(() => `w-${props.size} h-${props.size}`)
const fontSize = computed(() => {
  const s = parseInt(props.size)
  return s <= 6 ? '0.5rem' : s <= 8 ? '0.625rem' : '0.75rem'
})

const initials = computed(() => {
  const first = (props.person.first_name || '')[0] || ''
  const last = (props.person.last_name || '')[0] || ''
  return (first + last).toUpperCase() || '?'
})
</script>
