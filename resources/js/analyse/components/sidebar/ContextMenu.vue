<template>
  <Teleport to="body">
    <div
      v-if="visible"
      class="fixed inset-0 z-50"
      @click="close"
      @contextmenu.prevent="close"
    >
      <div
        class="fixed bg-gray-800 border border-gray-700 rounded-lg shadow-xl py-1 min-w-[160px] z-50"
        :style="{ top: y + 'px', left: x + 'px' }"
        @click.stop
      >
        <slot />
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref } from 'vue'

const visible = ref(false)
const x = ref(0)
const y = ref(0)

function open(event) {
  // Position the menu near the click/button
  const rect = event.currentTarget?.getBoundingClientRect?.()
  if (rect) {
    x.value = Math.min(rect.left, window.innerWidth - 180)
    y.value = rect.bottom + 4
  } else {
    x.value = event.clientX ?? 0
    y.value = event.clientY ?? 0
  }
  visible.value = true
}

function close() {
  visible.value = false
}

defineExpose({ open, close, visible })
</script>
