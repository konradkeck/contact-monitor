<template>
  <AppLayout>
    <div class="page-header">
      <h1 class="page-title">Conversations</h1>
    </div>

    <!-- Company filter indicator -->
    <div v-if="companyId" class="mb-5">
      <a :href="route('conversations.index')" class="text-sm text-gray-400 hover:text-gray-600">&larr; All conversations</a>
      <span class="text-sm text-gray-500 ml-2">Filtered by company</span>
    </div>

    <!-- Tabs -->
    <div v-else class="flex gap-0 border-b border-gray-200 mb-5" role="tablist">
      <a v-for="(label, key) in { assigned: 'Assigned', unassigned: 'Unassigned', filtered: 'Filtered' }" :key="key"
         :href="tabUrl(key)" role="tab" :aria-selected="tab === key"
         class="px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition"
         :class="tab === key ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
        {{ label }}
        <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-xs"
              :class="tab === key ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-500'">
          {{ (tabCounts[key] || 0).toLocaleString() }}
        </span>
      </a>
    </div>

    <!-- Search + Filters -->
    <form @submit.prevent="applyFilters">
      <div class="flex gap-2 mb-4 items-center">
        <button type="button" @click="showFilters = !showFilters"
                class="btn" :class="activeConvFilterCount > 0 ? 'btn-primary' : 'btn-secondary'">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/>
          </svg>
          Filters
          <span v-if="activeConvFilterCount > 0"
                class="ml-0.5 bg-white/25 text-white text-xs font-bold rounded-full w-4 h-4 flex items-center justify-center leading-none">
            {{ activeConvFilterCount }}
          </span>
        </button>
        <input type="text" v-model="search" placeholder="Search by company or channel..."
               class="input max-w-[280px]" @keyup.enter="applyFilters">
        <button type="submit" class="btn btn-secondary">Search</button>
        <a v-if="search || activeConvFilterCount > 0"
           :href="route('conversations.index', { tab })" class="btn btn-muted">Clear</a>
      </div>

      <!-- Filter panel -->
      <div v-show="showFilters" class="card p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <!-- Date range -->
          <div class="min-w-0">
            <label class="label mb-1">Last message</label>
            <div class="flex items-center gap-1.5">
              <input ref="dateRangeInput" type="text" placeholder="Date range..." readonly
                     class="input cursor-pointer flex-1 min-w-0">
              <button v-if="dateFrom" type="button" @click="clearDateRange"
                      class="text-base leading-none text-gray-400 hover:text-gray-600 px-1">&times;</button>
            </div>
          </div>

          <!-- Channels multi-select -->
          <div v-if="systemOptions.length && !companyId" class="min-w-0 relative" ref="channelDropdown">
            <label class="label mb-1">Channels</label>
            <div class="relative">
              <button type="button" @click="channelOpen = !channelOpen"
                      class="input w-full flex items-center justify-between gap-2 cursor-pointer text-left"
                      :class="selectedSystems.length ? 'border-brand-400 bg-brand-50 text-brand-800' : ''">
                <span class="text-sm">{{ channelLabel }}</span>
                <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform" :class="channelOpen ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>
              <div v-show="channelOpen"
                   class="absolute z-30 mt-1 w-full min-w-[220px] bg-white border border-gray-200 rounded-xl shadow-lg py-1 max-h-60 overflow-y-auto">
                <label v-for="sys in systemOptions" :key="sys.value"
                       class="flex items-center gap-2.5 px-3 py-2 cursor-pointer hover:bg-gray-50 transition select-none"
                       :class="selectedSystems.includes(sys.value) ? 'bg-brand-50' : ''">
                  <input type="checkbox" :value="sys.value"
                         :checked="selectedSystems.includes(sys.value)"
                         @change="toggleSystem(sys.value)"
                         class="rounded border-gray-300 shrink-0">
                  <span class="inline-flex items-center gap-1.5 flex-1 min-w-0">
                    <ChannelBadge :type="sys.channel_type" :label="false" />
                    <span v-if="sys.system_icon" v-html="sys.system_icon" />
                    <span class="text-sm text-gray-700 truncate">{{ sys.system_slug }}</span>
                  </span>
                </label>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-3 flex justify-end gap-2">
          <a v-if="activeConvFilterCount > 0"
             :href="route('conversations.index', { tab })" class="btn btn-muted">Clear filters</a>
          <button type="submit" class="btn btn-primary">Apply</button>
        </div>
      </div>
    </form>

    <!-- Table -->
    <div class="card overflow-hidden">
      <!-- Bulk bar -->
      <div v-if="selectedIds.length" class="flex items-center gap-3 px-4 py-2 border-b bulk-bar">
        <span class="text-sm font-medium bulk-bar-text">{{ selectedIds.length }} selected</span>
        <button v-if="canWrite" type="button" @click="openBulkFilterModal" class="btn btn-danger btn-sm">Filter...</button>
        <button type="button" @click="selectedIds = []; allSelected = false" class="text-xs text-gray-500 hover:text-gray-700">Clear</button>
      </div>

      <table class="w-full text-sm">
        <thead class="tbl-header">
          <tr>
            <th class="px-3 py-2.5 w-8">
              <input type="checkbox" v-model="allSelected" @change="toggleAll" class="rounded border-gray-300 cursor-pointer">
            </th>
            <th class="px-4 py-2.5 text-left">Channel</th>
            <th class="px-4 py-2.5 text-left">Subject</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-left">Company</th>
            <th class="px-4 py-2.5 text-left">People</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-left">Team</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-center">Msgs</th>
            <th class="px-4 py-2.5" />
          </tr>
        </thead>
        <tbody>
          <tr v-for="conv in conversations.data" :key="conv.id" class="tbl-row">
            <td class="px-3 py-3">
              <input type="checkbox" :value="conv.id" v-model="selectedIds" class="rounded border-gray-300 cursor-pointer">
            </td>
            <!-- Channel -->
            <td class="px-4 py-3">
              <a :href="conv.show_url" class="flex items-center gap-1.5 hover:underline">
                <ChannelBadge :type="conv.channel_type" :label="false" />
                <span v-if="conv.system_icon" v-html="conv.system_icon" />
                <span class="hidden md:inline text-xs text-gray-700">{{ conv.system_slug }}</span>
              </a>
            </td>
            <!-- Subject -->
            <td class="px-4 py-3 max-w-[200px]">
              <button type="button" @click="openConvQuickView(conv.modal_url)"
                      class="link text-xs truncate block text-left w-full"
                      :title="conv.subject || 'No subject'">
                {{ conv.subject || '\u2014' }}
              </button>
              <span v-if="conv.last_message_ago" class="block text-[10px] text-gray-400 mt-0.5">{{ conv.last_message_ago }}</span>
            </td>
            <!-- Company -->
            <td class="col-mobile-hidden px-4 py-3 max-w-[160px]">
              <a v-if="conv.company_name" :href="`/companies/${conv.company_id}`" class="link text-xs truncate block">{{ conv.company_name }}</a>
              <span v-else class="text-gray-300 text-xs">&mdash;</span>
            </td>
            <!-- People (customer) -->
            <td class="px-4 py-3">
              <ParticipantAvatars v-if="conv.participants.customer.length" :entries="conv.participants.customer" :max="2"
                                  @show-all="peoplePopup = conv.id" />
              <span v-else class="text-gray-300 text-xs">&mdash;</span>
            </td>
            <!-- Team -->
            <td class="col-mobile-hidden px-4 py-3">
              <ParticipantAvatars v-if="conv.participants.team.length" :entries="conv.participants.team" :max="4" team />
              <span v-else class="text-gray-300">---</span>
            </td>
            <!-- Messages -->
            <td class="col-mobile-hidden px-4 py-3 text-center text-gray-500 tabular-nums">{{ conv.message_count }}</td>
            <!-- Actions -->
            <td class="px-4 py-3 text-right">
              <div class="row-actions-desktop items-center justify-end gap-1.5">
                <button v-if="canWrite" type="button" @click="openFilterModalFor([conv.id])" class="btn btn-sm btn-danger" title="Filter">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
                  Filter
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!conversations.data.length">
            <td colspan="8" class="px-4 py-8 text-center empty-state italic">No conversations.</td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="conversations.meta.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex items-center justify-center gap-1">
        <template v-for="link in conversations.links" :key="link.label">
          <a v-if="link.url" :href="link.url" class="px-3 py-1 text-sm rounded"
             :class="link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
             v-html="link.label" />
          <span v-else class="px-3 py-1 text-sm text-gray-400" v-html="link.label" />
        </template>
      </div>
    </div>

    <!-- People popup modal -->
    <Modal :show="peoplePopup !== null" @close="peoplePopup = null" size="sm">
      <template #header>
        <h3 class="font-semibold text-gray-800 text-sm">Participants</h3>
      </template>
      <ul v-if="peoplePopup !== null" class="divide-y divide-gray-50 max-h-72 overflow-y-auto">
        <li v-for="entry in popupParticipants" :key="entry._label" class="flex items-center gap-3 px-4 py-2.5">
          <img v-if="entry._imgSrc" :src="entry._imgSrc" :alt="entry._title" class="w-8 h-8 rounded-full object-cover shrink-0">
          <span v-else class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold shrink-0"
                :class="entry.person_id ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-600'">{{ entry._label }}</span>
          <a v-if="entry.person_id" :href="`/people/${entry.person_id}`" class="text-sm text-gray-700 hover:text-brand-700 truncate">{{ entry._title }}</a>
          <span v-else class="text-sm text-gray-700 truncate">{{ entry._title }}</span>
        </li>
      </ul>
    </Modal>

    <!-- Vue Modals -->
    <ConversationQuickView :show="showConvModal" :src="convModalSrc" @close="showConvModal = false" />

    <FilterRuleModal :show="showFilterModal" :fetchUrl="filterFetchUrl" :submitUrl="'/conversations/archive-with-rule'"
                     title="Filter Conversations" :archiveOnly="true" @close="showFilterModal = false" />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import ChannelBadge from '../../components/ChannelBadge.vue'
import Modal from '../../components/Modal.vue'
import ConversationQuickView from '../../components/ConversationQuickView.vue'
import FilterRuleModal from '../../components/FilterRuleModal.vue'

const props = defineProps({
  conversations: Object,
  tab: String,
  tabCounts: Object,
  companyId: [Number, String, null],
  channelType: [String, null],
  systemSlug: [String, null],
  personId: [Number, String, null],
  q: [String, null],
  systemOptions: Array,
  activeSystems: Array,
  f_date_from: String,
  f_date_to: String,
  activeConvFilterCount: Number,
  filterModalUrl: String,
})

const page = usePage()
const canWrite = computed(() => page.props.auth?.permissions?.data_write)

const search = ref(props.q || '')
const showFilters = ref(props.activeConvFilterCount > 0)
const selectedIds = ref([])
const allSelected = ref(false)
const channelOpen = ref(false)
const channelDropdown = ref(null)
const selectedSystems = ref([...props.activeSystems])
const dateFrom = ref(props.f_date_from || '')
const dateTo = ref(props.f_date_to || '')
const dateRangeInput = ref(null)
const peoplePopup = ref(null)

// Modal state
const showConvModal = ref(false)
const convModalSrc = ref('')
const showFilterModal = ref(false)
const filterFetchUrl = ref('')

const channelLabel = computed(() => {
  if (!selectedSystems.value.length) return 'All channels'
  if (selectedSystems.value.length === 1) return '1 channel'
  return selectedSystems.value.length + ' channels'
})

const popupParticipants = computed(() => {
  if (peoplePopup.value === null) return []
  const conv = props.conversations.data.find(c => c.id === peoplePopup.value)
  return conv?.participants?.customer || []
})

function tabUrl(key) {
  const params = new URLSearchParams(window.location.search)
  params.set('tab', key)
  params.delete('page')
  return `${window.location.pathname}?${params.toString()}`
}

function toggleAll() {
  if (allSelected.value) {
    selectedIds.value = props.conversations.data.map(c => c.id)
  } else {
    selectedIds.value = []
  }
}

function toggleSystem(val) {
  const idx = selectedSystems.value.indexOf(val)
  if (idx === -1) selectedSystems.value.push(val)
  else selectedSystems.value.splice(idx, 1)
}

function applyFilters() {
  const params = { tab: props.tab }
  if (search.value) params.q = search.value
  if (dateFrom.value) params.f_date_from = dateFrom.value
  if (dateTo.value) params.f_date_to = dateTo.value
  if (selectedSystems.value.length) params['systems[]'] = selectedSystems.value
  if (props.companyId) params.company_id = props.companyId
  if (props.personId) params.person_id = props.personId

  router.get(route('conversations.index'), params, { preserveState: false })
}

function clearDateRange() {
  dateFrom.value = ''
  dateTo.value = ''
  if (dateRangeInput.value) dateRangeInput.value.value = ''
}

function openConvQuickView(src) {
  convModalSrc.value = src
  showConvModal.value = true
}

function openFilterModalFor(ids) {
  const qs = ids.map(id => 'ids[]=' + encodeURIComponent(id)).join('&')
  filterFetchUrl.value = props.filterModalUrl + '?' + qs
  showFilterModal.value = true
}

function openBulkFilterModal() {
  openFilterModalFor(selectedIds.value)
}

// Close channel dropdown on outside click
function handleOutsideClick(e) {
  if (channelDropdown.value && !channelDropdown.value.contains(e.target)) {
    channelOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)

  // Init date range picker
  if (dateRangeInput.value && window._EP) {
    window.drp.init('conv-date-input', function(from, to) {
      dateFrom.value = from
      dateTo.value = to
    }, {
      defaultFrom: props.f_date_from || '',
      defaultTo: props.f_date_to || '',
    })
  }
})

// Helper function for Ziggy routes
function route(name, params) {
  if (window.route) return window.route(name, params)
  // Fallback
  const routes = {
    'conversations.index': '/conversations',
  }
  let url = routes[name] || '/'
  if (params && typeof params === 'object') {
    const qs = new URLSearchParams(params).toString()
    if (qs) url += '?' + qs
  }
  return url
}

// Participant avatars sub-component
const ParticipantAvatars = {
  props: {
    entries: Array,
    max: { type: Number, default: 2 },
    team: Boolean,
  },
  emits: ['showAll'],
  template: `
    <div class="flex items-center -space-x-1.5">
      <span v-for="(entry, idx) in entries.slice(0, max)" :key="idx" :title="entry._title" class="relative inline-block">
        <img v-if="entry._imgSrc" :src="entry._imgSrc" :alt="entry._title" class="w-7 h-7 rounded-full ring-2 ring-white object-cover">
        <span v-else class="flex items-center justify-center w-7 h-7 rounded-full ring-2 ring-white text-xs font-semibold"
              :class="team ? 'bg-violet-100 text-violet-700' : (entry.person_id ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-600')">
          {{ entry._label }}
        </span>
      </span>
      <button v-if="entries.length > max" type="button" @click="$emit('showAll')"
              class="flex items-center justify-center w-7 h-7 rounded-full ring-2 ring-white text-xs font-semibold bg-gray-100 text-gray-500 hover:bg-gray-200 transition"
              :title="entries.length > max ? entries.slice(max).map(e => e.display_name || e._title).join(', ') : ''">
        +{{ entries.length - max }}
      </button>
    </div>
  `,
}
</script>
