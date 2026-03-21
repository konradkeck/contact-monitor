<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center" @keydown.escape="$emit('close')">
      <div class="modal-overlay" @click="$emit('close')" />
      <div
        class="relative z-10 bg-white rounded-xl shadow-2xl w-full overflow-hidden"
        :class="sizes[size]"
        :style="maxHeight ? { maxHeight } : {}"
      >
        <div v-if="$slots.header" class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
          <slot name="header" />
          <button type="button" @click="$emit('close')" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
        </div>
        <div class="overflow-y-auto" :style="maxHeight ? { maxHeight: `calc(${maxHeight} - 3.5rem)` } : {}">
          <slot />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
defineProps({
  show: { type: Boolean, default: false },
  size: { type: String, default: 'md' },
  maxHeight: { type: String, default: '85vh' },
})
defineEmits(['close'])

const sizes = {
  sm: 'max-w-sm',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
  xl: 'max-w-4xl',
  full: 'max-w-6xl',
}
</script>
