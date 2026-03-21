<template>
  <div class="flex flex-col min-h-screen bg-gray-50">
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50
              focus:px-4 focus:py-2 focus:bg-white focus:text-brand-700 focus:text-sm focus:font-medium
              focus:rounded focus:border focus:border-brand-300 focus:shadow">
      Skip to content
    </a>

    <!-- Top Bar -->
    <TopBar
      :top-sections="layout.topSections"
      :user="auth.user"
      :permissions="auth.permissions"
      @toggle-sidebar="toggleSidebar"
    />

    <!-- Body: Sidebar + Content -->
    <div class="flex flex-1">
      <!-- Mobile sidebar backdrop -->
      <div v-show="sidebarOpen" @click="sidebarOpen = false"
           class="fixed inset-0 z-30 bg-black/50 md:hidden transition-opacity duration-200" />

      <!-- Left sidebar -->
      <aside
        :class="sidebarOpen ? '!translate-x-0' : ''"
        class="sidebar w-52 flex-shrink-0 flex flex-col fixed top-16 left-0 h-[calc(100vh-4rem)]
               -translate-x-full md:translate-x-0 transition-transform duration-200 ease-out z-40"
        :style="isAnalyze ? '' : 'overflow-y: auto'"
      >
        <!-- Analyze section: custom sidebar slot -->
        <template v-if="isAnalyze">
          <slot name="sidebar" />
        </template>

        <!-- Standard sections -->
        <nav v-else class="flex-1 px-2 py-3 space-y-0.5" aria-label="Sidebar" @click="sidebarOpen = false">
          <ConfigSidebar
            v-if="layout.section === 'configuration'"
            :items="layout.sidebarItems"
          />
          <BrowseDataSidebar
            v-else-if="layout.section === 'browse_data'"
            :items="layout.sidebarItems"
          />
        </nav>
      </aside>

      <!-- Secondary sidebar: Mapping connections -->
      <aside
        v-if="layout.section === 'configuration' && layout.onMapping && layout.mappingSystems.length > 0"
        class="sidebar w-44 flex-shrink-0 flex-col overflow-y-auto fixed top-16 h-[calc(100vh-4rem)] hidden md:flex z-40"
        style="left: 13rem;"
      >
        <div class="px-2 py-3 space-y-0.5">
          <a :href="mappingBackHref" class="sidebar-link text-xs mb-1">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back
          </a>
          <span class="sidebar-section">Connections</span>
          <a
            v-for="sys in layout.mappingSystems" :key="sys.system_type + '/' + sys.system_slug"
            :href="`/data-relations/mapping/${sys.system_type}/${sys.system_slug}`"
            :class="['sidebar-link', layout.currentMapping === sys.system_type + '/' + sys.system_slug ? 'is-active' : '']"
          >
            <ChannelBadge :type="sys.system_type" />
            <span class="truncate text-xs">{{ sys.system_slug }}</span>
          </a>
        </div>
      </aside>

      <!-- Main content -->
      <main id="main-content" class="flex-1 min-w-0" :style="{ marginLeft: mainMargin }"
            :class="isAnalyze ? 'h-[calc(100vh-4rem)] overflow-hidden' : ''">
        <!-- Analyze: full-height, no padding wrapper -->
        <template v-if="isAnalyze">
          <slot />
        </template>

        <!-- Standard pages: padded content -->
        <template v-else>
          <div class="px-6 py-5 max-w-screen-2xl mx-auto">
            <FlashMessages />

            <div v-if="layout.serverNeedsAttention && isServerRoute"
                 class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm flex items-start gap-2">
              <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
              </svg>
              <span>{{ layout.serverBadCount }} server(s) not responding. Check your synchronizer server connection.</span>
            </div>

            <div v-if="layout.mappingNeedsAttention && isMappingRoute"
                 class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm flex items-start gap-2">
              <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
              </svg>
              <span>Mapping is not configured in at least 50% for: {{ layout.mappingUnhealthySystems.join(', ') }}</span>
            </div>

            <slot />
          </div>
        </template>
      </main>
    </div>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import TopBar from '../components/TopBar.vue'
import ConfigSidebar from '../components/ConfigSidebar.vue'
import BrowseDataSidebar from '../components/BrowseDataSidebar.vue'
import FlashMessages from '../components/FlashMessages.vue'
import ChannelBadge from '../components/ChannelBadge.vue'


const page = usePage()
const auth = computed(() => page.props.auth)
const layout = computed(() => page.props.layout ?? {})

const sidebarOpen = ref(false)
const isAnalyze = computed(() => layout.value.section === 'analyze')

const mainMargin = computed(() => {
  if (layout.value.section === 'configuration' && layout.value.onMapping && layout.value.mappingSystems?.length > 0) {
    return '24rem'
  }
  return '13rem'
})

const mappingBackHref = computed(() => '/data-relations')

// Route detection for contextual warnings
const currentPath = computed(() => page.url ?? window.location.pathname)
const isServerRoute = computed(() => currentPath.value.startsWith('/synchronizer/servers'))
const isMappingRoute = computed(() => currentPath.value.startsWith('/data-relations'))

// Expose sidebarOpen for TopBar hamburger
function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value
}

defineExpose({ toggleSidebar })
</script>
