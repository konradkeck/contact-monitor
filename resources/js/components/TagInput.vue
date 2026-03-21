<template>
  <div class="flex flex-wrap gap-1.5 p-2 border border-gray-200 rounded-lg bg-white min-h-[2.5rem] focus-within:ring-2 focus-within:ring-brand-300 cursor-text"
       @click="$refs.input.focus()">
    <span v-for="(tag, idx) in tags" :key="idx"
          class="inline-flex items-center gap-1 bg-gray-100 text-gray-700 text-xs rounded px-2 py-1">
      {{ tag }}
      <button type="button" @click="removeTag(idx)" class="text-gray-400 hover:text-red-500 leading-none">&times;</button>
    </span>
    <input
      ref="input"
      v-model="inputValue"
      :placeholder="tags.length ? '' : placeholder"
      class="flex-1 min-w-[100px] text-sm border-none outline-none bg-transparent p-0"
      @keydown.enter.prevent="addTag"
      @keydown.comma.prevent="splitOnComma && addTag()"
      @keydown.backspace="!inputValue && removeLastTag()"
    />
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Type and press Enter...' },
  splitOnComma: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue'])

const tags = ref([...props.modelValue])
const inputValue = ref('')

function addTag() {
  const val = inputValue.value.trim().toLowerCase()
  if (val && !tags.value.includes(val)) {
    tags.value.push(val)
    emit('update:modelValue', [...tags.value])
  }
  inputValue.value = ''
}

function removeTag(idx) {
  tags.value.splice(idx, 1)
  emit('update:modelValue', [...tags.value])
}

function removeLastTag() {
  if (tags.value.length) {
    tags.value.pop()
    emit('update:modelValue', [...tags.value])
  }
}

watch(() => props.modelValue, (newVal) => {
  tags.value = [...newVal]
})
</script>
