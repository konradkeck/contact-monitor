<template>
  <header class="topbar flex-shrink-0 z-20 sticky top-0 border-b">
    <div class="flex items-center h-16 px-5 gap-6">
      <!-- Hamburger (mobile only) -->
      <button @click="$emit('toggle-sidebar')"
              class="md:hidden flex items-center justify-center w-9 h-9 -ml-2 rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition shrink-0"
              aria-label="Toggle navigation">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>

      <a href="/" class="flex items-center gap-2.5 flex-shrink-0">
        <img :src="'/logo.svg'" alt="" class="w-6 h-6">
        <span class="font-medium text-base tracking-tight text-white hidden sm:inline">Contact Monitor</span>
      </a>

      <!-- Desktop nav -->
      <nav class="hidden md:flex items-center gap-0.5 ml-8" aria-label="Primary">
        <template v-for="section in visibleSections" :key="section.label">
          <a :href="section.disabled ? '#' : section.href"
             :title="section.disabled ? section.disabledMsg : undefined"
             :aria-current="section.isActive ? 'page' : undefined"
             @click="section.disabled ? $event.preventDefault() : null"
             :class="[
               'flex items-center gap-1.5 px-4 py-2 rounded text-sm font-medium transition',
               section.isActive ? 'bg-white/12 text-white' : 'text-slate-300 hover:text-white hover:bg-white/10',
               section.disabled ? 'opacity-40 cursor-not-allowed' : '',
             ]">
            <img v-if="section.type === 'ai'" :src="'/ai-icon.svg'" class="w-5 h-5 shrink-0" alt="">
            {{ section.label }}
            <span v-if="section.dot" class="ml-0.5 w-1.5 h-1.5 rounded-full bg-red-500 inline-block shrink-0" />
          </a>
        </template>
      </nav>

      <!-- User dropdown -->
      <div class="ml-auto relative">
        <button @click="userMenuOpen = !userMenuOpen"
                class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-slate-200 hover:text-white hover:bg-white/10 transition">
          <img :src="gravatarUrl" class="w-6 h-6 rounded-full" alt="">
          <span class="hidden sm:inline">{{ user.name }}</span>
          <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>
        <div v-show="userMenuOpen" v-click-outside="() => userMenuOpen = false"
             class="absolute right-0 top-full mt-1 w-48 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50 text-sm">
          <div class="px-3 py-2 border-b border-gray-100">
            <p class="font-medium text-gray-800 truncate">{{ user.name }}</p>
            <p class="text-xs text-gray-400 truncate">{{ user.email }}</p>
          </div>
          <a href="/change-password"
             class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-50 transition">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
            Change Password
          </a>
          <form method="POST" action="/logout">
            <input type="hidden" name="_token" :value="csrfToken">
            <button type="submit"
                    class="flex items-center gap-2 w-full px-3 py-2 text-red-600 hover:bg-red-50 transition text-left">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
              </svg>
              Sign out
            </button>
          </form>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, computed } from 'vue'
import md5 from '../utils/md5.js'

const props = defineProps({
  topSections: { type: Array, default: () => [] },
  user: { type: Object, required: true },
  permissions: { type: Object, default: () => ({}) },
})

defineEmits(['toggle-sidebar'])

const userMenuOpen = ref(false)
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? ''

const gravatarUrl = computed(() => {
  const hash = md5((props.user.email ?? '').trim().toLowerCase())
  return `https://www.gravatar.com/avatar/${hash}?s=32&d=mp`
})

const visibleSections = computed(() => {
  return props.topSections.filter(s => {
    return props.permissions[s.permKey] ?? false
  })
})

// v-click-outside directive
const vClickOutside = {
  mounted(el, binding) {
    el._clickOutside = (e) => {
      if (!el.contains(e.target)) binding.value()
    }
    document.addEventListener('click', el._clickOutside)
  },
  unmounted(el) {
    document.removeEventListener('click', el._clickOutside)
  },
}
</script>
