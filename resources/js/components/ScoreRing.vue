<template>
  <div class="relative w-16 h-16">
    <svg width="64" height="64" viewBox="0 0 64 64" style="transform:rotate(-90deg)">
      <circle cx="32" cy="32" :r="r" fill="none" stroke="#e5e7eb" stroke-width="5"/>
      <circle cx="32" cy="32" :r="r" fill="none"
              :stroke="color" stroke-width="5"
              stroke-linecap="round"
              :style="{ strokeDasharray: circ.toFixed(3), strokeDashoffset: offset.toFixed(3) }"/>
    </svg>
    <div class="absolute inset-0 flex items-center justify-center">
      <span class="text-xl font-bold text-gray-900">{{ score }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  score: { type: Number, required: true },
})

const r = 26
const circ = computed(() => 2 * Math.PI * r)
const offset = computed(() => circ.value * (1 - props.score / 10))

const COLORS = {
  1: '#ef4444', 2: '#f97316', 3: '#f59e0b', 4: '#eab308',
  5: '#84cc16', 6: '#4ade80', 7: '#22c55e', 8: '#16a34a',
  9: '#15803d', 10: '#166534',
}
const color = computed(() => COLORS[props.score] || '#e5e7eb')
</script>
