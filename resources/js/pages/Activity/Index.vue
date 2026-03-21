<template>
  <AppLayout>
    <Head title="Activities" />

    <div class="page-header">
      <h1 class="page-title">Activities</h1>
    </div>

    <!-- Tabs -->
    <div class="flex gap-0 border-b border-gray-200 mb-5" role="tablist" aria-label="Activity view">
      <button v-for="t in tabs" :key="t.key"
              @click="setTab(t.key)"
              role="tab"
              :aria-selected="activeTab === t.key"
              :class="['px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition whitespace-nowrap',
                       activeTab === t.key ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
        {{ t.label }}
      </button>
    </div>

    <!-- Search + Filter bar -->
    <div class="flex gap-2 mb-4 items-center flex-wrap">
      <button type="button" @click="showFilterPanel = !showFilterPanel"
              :class="['btn', filterCount > 0 ? 'btn-primary' : 'btn-secondary']">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/>
        </svg>
        Filters
        <span v-if="filterCount > 0"
              class="ml-0.5 bg-white/25 text-white text-xs font-bold rounded-full w-4 h-4 flex items-center justify-center leading-none">{{ filterCount }}</span>
      </button>
      <input v-model="searchInput" type="text" placeholder="Search activities..." class="input max-w-[280px]"
             @keydown.enter="applySearch">
      <button type="button" @click="applySearch" class="btn btn-secondary">Search</button>
      <button v-if="hasFilters" @click="resetFilters" class="btn btn-muted">Clear</button>
    </div>

    <!-- Collapsible filter panel -->
    <div v-if="showFilterPanel" class="card p-4 mb-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- Date range -->
        <div class="min-w-0">
          <label class="label mb-1">Date range</label>
          <div class="drp-wrap flex items-center gap-1.5">
            <input id="tl-date-range" type="text" placeholder="Date range..." class="input cursor-pointer flex-1 min-w-0">
            <button v-if="dateFrom" type="button" @click="clearDate"
                    class="drp-clear text-base leading-none text-gray-400 hover:text-gray-600 px-1">&times;</button>
          </div>
        </div>

        <!-- Channels -->
        <div v-if="convSystems.length" class="min-w-0 sm:col-span-1 lg:col-span-2">
          <label class="label mb-1">Channels</label>
          <div class="flex flex-wrap gap-2">
            <label v-for="sys in convSystems" :key="sys.channel_type + '|' + sys.system_slug"
                   :class="['flex items-center gap-1.5 cursor-pointer select-none border rounded-lg px-2 py-1 hover:border-gray-300 hover:bg-gray-50 transition text-xs',
                            activeSystems.includes(sys.channel_type + '|' + sys.system_slug) ? 'border-brand-400 bg-brand-50' : 'border-gray-200']">
              <input type="checkbox" class="rounded border-gray-300"
                     :value="sys.channel_type + '|' + sys.system_slug"
                     :checked="activeSystems.includes(sys.channel_type + '|' + sys.system_slug)"
                     @change="toggleSystem(sys.channel_type + '|' + sys.system_slug)">
              <span class="text-gray-600">{{ sys.system_slug }}</span>
            </label>
          </div>
        </div>

        <!-- Activity types -->
        <div v-if="activityTypes.length" class="min-w-0">
          <label class="label mb-1">Activity type</label>
          <div class="flex flex-wrap gap-2">
            <label v-for="t in activityTypes" :key="t"
                   :class="['flex items-center gap-1.5 cursor-pointer select-none border rounded-lg px-2 py-1 hover:border-gray-300 hover:bg-gray-50 transition text-xs',
                            activeActTypes.includes(t) ? 'border-brand-400 bg-brand-50' : 'border-gray-200']">
              <input type="checkbox" class="rounded border-gray-300"
                     :value="t" :checked="activeActTypes.includes(t)"
                     @change="toggleActType(t)">
              <span :class="['w-2 h-2 rounded-full shrink-0', typeColors[t] || 'bg-slate-300']"></span>
              <span class="text-gray-600">{{ t === 'note' ? 'Other' : t.charAt(0).toUpperCase() + t.slice(1).replace(/_/g, ' ') }}</span>
            </label>
          </div>
        </div>
      </div>
      <div class="mt-3 flex justify-end gap-2">
        <button type="button" @click="resetFilters" class="btn btn-muted">Clear filters</button>
        <button type="button" @click="showFilterPanel = false; reload()" class="btn btn-primary">Apply</button>
      </div>
    </div>

    <!-- Timeline -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="relative px-4 py-2 min-h-[120px]">
        <div class="absolute inset-y-0 left-1/2 -translate-x-1/2 w-px bg-gray-200 pointer-events-none z-0"></div>
        <Timeline
          :activities="tlItems"
          :initialCursor="tlNextCursor"
          :timelineUrl="timelineUrl"
          gridClass="relative z-10"
          @openModal="openConvQuickView"
        />
      </div>
    </div>

    <ConversationQuickView :show="showConvModal" :src="convModalSrc" @close="showConvModal = false" />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import Timeline from '../../components/Timeline.vue'
import ConversationQuickView from '../../components/ConversationQuickView.vue'

const props = defineProps({
  convSystems: Array,
  activityTypes: Array,
  typeColors: Object,
})

const tabs = [
  { key: 'all', label: 'All' },
  { key: 'conversations', label: 'Conversations' },
  { key: 'activity', label: 'Activity' },
]

const activeTab = ref('all')
const searchInput = ref('')
const searchQuery = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const activeSystems = ref([])
const activeActTypes = ref([])
const showFilterPanel = ref(false)
const tlItems = ref([])
const tlNextCursor = ref(null)
const showConvModal = ref(false)
const convModalSrc = ref('')
let fpInstance = null

const filterCount = computed(() => {
  return activeSystems.value.length + activeActTypes.value.length + (dateFrom.value ? 1 : 0) + (searchQuery.value ? 1 : 0)
})

const hasFilters = computed(() => {
  return activeSystems.value.length > 0 || activeActTypes.value.length > 0 || dateFrom.value || dateTo.value || searchQuery.value
})

const timelineUrl = computed(() => {
  const p = new URLSearchParams()
  if (dateFrom.value) p.set('from', dateFrom.value)
  if (dateTo.value) p.set('to', dateTo.value)
  if (searchQuery.value) p.set('q', searchQuery.value)

  if (activeSystems.value.length) {
    p.append('types[]', 'conversation')
    activeSystems.value.forEach(s => p.append('systems[]', s))
  } else if (activeActTypes.value.length) {
    activeActTypes.value.forEach(t => p.append('types[]', t))
  } else if (activeTab.value === 'conversations') {
    p.append('types[]', 'conversation')
  } else if (activeTab.value === 'activity') {
    p.set('exclude_type', 'conversation')
  }

  return `/activities/timeline?${p}`
})

function setTab(tab) {
  activeSystems.value = []
  activeActTypes.value = []
  activeTab.value = tab
  reload()
}

function toggleSystem(val) {
  const idx = activeSystems.value.indexOf(val)
  if (idx === -1) activeSystems.value.push(val)
  else activeSystems.value.splice(idx, 1)
}

function toggleActType(val) {
  const idx = activeActTypes.value.indexOf(val)
  if (idx === -1) activeActTypes.value.push(val)
  else activeActTypes.value.splice(idx, 1)
}

function applySearch() {
  searchQuery.value = searchInput.value.trim()
  reload()
}

function clearDate() {
  fpInstance?.clear()
  dateFrom.value = ''
  dateTo.value = ''
  reload()
}

function openConvQuickView(url) {
  convModalSrc.value = url
  showConvModal.value = true
}

function resetFilters() {
  activeSystems.value = []
  activeActTypes.value = []
  searchQuery.value = ''
  searchInput.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  fpInstance?.clear()
  reload()
}

async function reload() {
  try {
    const url = new URL(timelineUrl.value, window.location.origin)
    url.searchParams.set('json', '1')
    const resp = await fetch(url.toString(), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const data = await resp.json()
    tlItems.value = data.items
    tlNextCursor.value = data.nextCursor || null
  } catch (e) {
    console.error('Timeline load error:', e)
  }
}

onMounted(() => {
  if (window.drp && window._EP) {
    fpInstance = window.drp.init('tl-date-range', function(from, to) {
      dateFrom.value = from
      dateTo.value = to
      reload()
    })
  }
  reload()
})
</script>
