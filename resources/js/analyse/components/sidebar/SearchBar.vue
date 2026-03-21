<template>
  <div class="px-3 py-2 border-b border-gray-800">
    <input
      v-model="searchQ"
      type="text"
      placeholder="Search conversations..."
      class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-1.5 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-brand-600"
      @input="doSearch"
    />
    <div v-if="searchResults.length" class="mt-1 bg-gray-800 border border-gray-700 rounded shadow-lg max-h-60 overflow-y-auto">
      <a
        v-for="r in searchResults"
        :key="r.chat_id"
        :href="`/analyse/c/${r.chat_id}`"
        class="block px-3 py-2 hover:bg-gray-700 border-b border-gray-700 last:border-0"
        @click="searchQ = ''; searchResults = []"
      >
        <div class="text-xs text-gray-200 font-medium truncate">{{ r.title }}</div>
        <div v-if="r.snippet" class="text-xs text-gray-500 truncate mt-0.5" v-html="r.snippet" />
        <div v-if="r.owner_name" class="text-xs text-gray-600 mt-0.5">by {{ r.owner_name }}</div>
        <div v-if="r.project_name" class="text-xs text-gray-600">in {{ r.project_name }}</div>
      </a>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

let debounceTimer = null
const searchQ = ref('')
const searchResults = ref([])

function doSearch() {
  clearTimeout(debounceTimer)
  const q = searchQ.value.trim()
  if (q.length < 2) { searchResults.value = []; return }
  debounceTimer = setTimeout(async () => {
    const res = await fetch(`/analyse/search?q=${encodeURIComponent(q)}`)
    const data = await res.json()
    searchResults.value = data.results ?? []
  }, 200)
}
</script>
