<template>
  <AppLayout>
    <Head title="People" />

    <div class="page-header">
      <h1 class="page-title">People</h1>
      <div class="flex items-center gap-2">
        <template v-if="showFiltered">
          <Link :href="buildUrl({ show_filtered: undefined })" class="btn btn-danger btn-sm">&larr; All People</Link>
        </template>
        <template v-else>
          <Link :href="buildUrl({ show_filtered: 1 })" class="btn btn-secondary btn-sm">
            Filtered
            <span v-if="filteredCount > 0"
                  class="ml-1 inline-flex items-center justify-center bg-brand-600 text-white text-xs font-bold rounded-full w-4 h-4 leading-none">{{ filteredCount }}</span>
            <span v-else class="ml-1 text-xs text-gray-400">(0)</span>
          </Link>
        </template>
        <Link v-if="$page.props.auth.permissions.data_write" href="/people/create" class="btn btn-primary">+ New Person</Link>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-0 border-b border-gray-200 mb-5" role="tablist">
      <Link v-for="t in tabItems" :key="t.key"
            :href="buildUrl({ tab: t.key, page: undefined })"
            role="tab" :aria-selected="tab === t.key"
            :class="['flex items-center gap-1.5 px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition',
                     tab === t.key ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
        {{ t.label }}
        <span :class="['px-1.5 py-0.5 rounded-full text-xs',
                       tab === t.key ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-500']">
          {{ t.count.toLocaleString() }}
        </span>
      </Link>
    </div>

    <!-- Search + Filter bar -->
    <form @submit.prevent="applySearch">
      <div class="flex gap-2 mb-4 items-center">
        <button type="button" @click="showFilterPanel = !showFilterPanel"
                :class="['btn', activeFilterCount > 0 ? 'btn-primary' : 'btn-secondary']">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/>
          </svg>
          Filters
          <span v-if="activeFilterCount > 0"
                class="ml-0.5 bg-white/25 text-white text-xs font-bold rounded-full w-4 h-4 flex items-center justify-center leading-none">{{ activeFilterCount }}</span>
        </button>
        <input v-model="searchInput" type="text" placeholder="Search by name or identity..." class="input max-w-[280px]">
        <button type="submit" class="btn btn-secondary">Search</button>
        <Link v-if="hasFilters" :href="buildUrl({ q: undefined, f_lc_from: undefined, f_lc_to: undefined, f_has_company: undefined, f_channel: undefined })" class="btn btn-muted">Clear</Link>
      </div>

      <!-- Filter panel -->
      <div v-if="showFilterPanel" class="card p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <div class="min-w-0">
            <label class="label mb-1">Last contact</label>
            <div class="flex items-center gap-1.5">
              <input id="ppl-date-range" type="text" placeholder="Date range..." readonly class="input cursor-pointer flex-1 min-w-0">
              <button v-if="filterDateFrom" type="button" @click="clearDateFilter"
                      class="text-base leading-none text-gray-400 hover:text-gray-600 px-1">&times;</button>
            </div>
          </div>
          <div class="min-w-0">
            <label class="label mb-1">Has company</label>
            <select v-model="filterHasCompany" class="input w-full">
              <option value="">Any</option>
              <option value="has">Has company</option>
              <option value="none">No company</option>
            </select>
          </div>
          <div v-if="channelTypes.length" class="min-w-0">
            <label class="label mb-1">Channel</label>
            <select v-model="filterChannel" class="input w-full">
              <option value="">Any</option>
              <option v-for="ct in channelTypes" :key="ct" :value="ct">{{ ct.charAt(0).toUpperCase() + ct.slice(1) }}</option>
            </select>
          </div>
        </div>
        <div class="mt-3 flex justify-end gap-2">
          <Link v-if="activeFilterCount > 0"
                :href="buildUrl({ f_lc_from: undefined, f_lc_to: undefined, f_has_company: undefined, f_channel: undefined })"
                class="btn btn-muted">Clear filters</Link>
          <button type="button" @click="applyFilters" class="btn btn-primary">Apply</button>
        </div>
      </div>
    </form>

    <!-- Table -->
    <div class="card overflow-hidden">
      <!-- Bulk bar -->
      <div v-if="selectedIds.length" class="flex items-center gap-3 px-4 py-2 border-b bulk-bar">
        <span class="text-sm font-medium bulk-bar-text">{{ selectedIds.length }} selected</span>
        <template v-if="$page.props.auth.permissions.data_write">
          <button type="button" @click="openMergeModal" class="btn btn-secondary btn-sm">Merge...</button>
          <template v-if="tab === 'clients'">
            <button type="button" @click="openFilterModal()" class="btn btn-danger btn-sm">Filter...</button>
            <button type="button" @click="openAssignCompanyModal()" class="btn btn-secondary btn-sm">Assign Company...</button>
            <button type="button" @click="bulkMarkOurOrg" class="btn btn-secondary btn-sm">Mark as Our Org</button>
          </template>
          <template v-else-if="tab === 'our_org'">
            <button type="button" @click="bulkUnmarkOurOrg" class="btn btn-secondary btn-sm">Unmark Our Org</button>
          </template>
        </template>
        <button type="button" @click="selectedIds = []" class="text-xs text-gray-500 hover:text-gray-700">Clear</button>
      </div>

      <table class="w-full text-sm">
        <thead class="tbl-header">
          <tr>
            <th class="px-3 py-2.5 w-8">
              <input type="checkbox" class="rounded border-gray-300 cursor-pointer"
                     :checked="allSelected" :indeterminate.prop="someSelected" @change="toggleAll">
            </th>
            <th class="px-4 py-2.5 text-left w-56">
              <Link :href="sortUrl('first_name')" class="flex items-center justify-between gap-2 hover:text-gray-700">
                Name <span class="shrink-0 opacity-50">{{ sortIcon('first_name') }}</span>
              </Link>
            </th>
            <th class="col-mobile-hidden px-4 py-2.5 text-left w-36">Communication</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-left">Companies</th>
            <th class="px-4 py-2.5 text-left w-[28rem]">
              <Link :href="sortUrl('updated_at')" class="flex items-center justify-between gap-2 hover:text-gray-700">
                Last Contact <span class="shrink-0 opacity-50">{{ sortIcon('updated_at') }}</span>
              </Link>
            </th>
            <th class="px-4 py-2.5 w-12"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="person in people.data" :key="person.id"
              :class="['tbl-row', person.is_our_org ? 'bg-brand-50/60' : '']">
            <td class="px-3 py-3">
              <input type="checkbox" :value="person.id" v-model="selectedIds"
                     class="rounded border-gray-300 cursor-pointer">
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2.5">
                <Link :href="`/people/${person.id}`" class="shrink-0">
                  <img :src="person.avatar_url" class="w-8 h-8 rounded-full object-cover border border-gray-100 bg-gray-100">
                </Link>
                <Link :href="`/people/${person.id}`" class="font-medium link truncate">
                  {{ person.full_name }}
                </Link>
                <span v-if="showFiltered && person.filtered_reason"
                      :title="person.filtered_reason"
                      class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-red-100 text-red-600 text-[10px] font-bold shrink-0 cursor-default leading-none">i</span>
                <div class="flex-1"></div>
                <span v-if="person.notes_count" class="text-xs text-gray-400" :title="person.notes_count + ' notes'">
                  <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                  {{ person.notes_count }}
                </span>
              </div>
            </td>
            <td class="col-mobile-hidden px-4 py-3">
              <div class="flex items-center gap-1">
                <span v-for="id in person.identity_types" :key="id.type + id.value"
                      class="text-[10px] text-gray-400 bg-gray-100 px-1 py-0.5 rounded" :title="id.value">
                  {{ id.type }}
                </span>
                <span v-if="person.identities_count > 6" class="text-xs text-gray-400 ml-0.5">+{{ person.identities_count - 6 }}</span>
              </div>
            </td>
            <td class="col-mobile-hidden px-4 py-3 text-xs">
              <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                <Link v-for="company in person.companies" :key="company.id"
                      :href="`/companies/${company.id}`" class="link whitespace-nowrap">{{ company.name }}</Link>
                <span v-if="!person.companies.length" class="text-gray-300">&mdash;</span>
                <button v-if="$page.props.auth.permissions.data_write" type="button"
                        @click="openAssignCompanyModal([person.id])"
                        class="text-[10px] text-gray-400 hover:text-brand-600 transition border border-gray-200 hover:border-brand-300 rounded px-1 py-0 leading-4 cursor-pointer">
                  Assign
                </button>
              </div>
            </td>
            <td class="px-4 py-3">
              <template v-if="person.last_conv">
                <div class="hidden md:flex items-center gap-1.5">
                  <component :is="person.last_conv.modal_url ? 'button' : 'div'"
                             v-bind="person.last_conv.modal_url ? { type: 'button' } : {}"
                             @click="person.last_conv.modal_url && openConvQuickView(person.last_conv.modal_url)"
                             :class="['flex items-center gap-1.5 w-full', person.last_conv.modal_url ? 'text-left hover:opacity-75 transition cursor-pointer' : '']">
                    <span :class="['inline-flex px-1.5 py-0.5 rounded text-xs font-medium shrink-0', channelBadge[person.last_conv.channel_type] || 'bg-slate-100 text-slate-700']">
                      {{ person.last_conv.channel_type ? person.last_conv.channel_type.charAt(0).toUpperCase() + person.last_conv.channel_type.slice(1) : '' }}
                    </span>
                    <span class="text-xs text-gray-500 truncate" :title="person.last_conv.conv_subject">{{ person.last_conv.conv_subject || '—' }}</span>
                    <span class="text-xs text-gray-300 shrink-0" :title="person.last_conv.occurred_at_full">{{ person.last_conv.occurred_at_human }}</span>
                  </component>
                </div>
                <div class="md:hidden flex items-center gap-1.5">
                  <span :class="['inline-flex px-1.5 py-0.5 rounded text-xs font-medium shrink-0', channelBadge[person.last_conv.channel_type] || 'bg-slate-100 text-slate-700']">
                    {{ person.last_conv.channel_type ? person.last_conv.channel_type.charAt(0).toUpperCase() + person.last_conv.channel_type.slice(1) : '' }}
                  </span>
                  <span class="text-xs text-gray-400" :title="person.last_conv.occurred_at_full">{{ person.last_conv.occurred_at_short }}</span>
                </div>
              </template>
              <span v-else class="text-xs text-gray-300">&mdash;</span>
            </td>
            <td class="px-4 py-3 text-right">
              <div v-if="$page.props.auth.permissions.data_write && tab === 'clients'" class="row-actions-desktop items-center justify-end gap-1.5">
                <button type="button" @click="openFilterModal([person.id])" class="btn btn-sm btn-danger" title="Filter">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
                  Filter
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!people.data.length">
            <td colspan="6" class="px-4 py-10 text-center empty-state">No people found.</td>
          </tr>
        </tbody>
      </table>

      <div v-if="people.links && people.last_page > 1" class="px-4 py-3 border-t border-gray-100">
        <nav class="flex items-center gap-1">
          <template v-for="link in people.links" :key="link.label">
            <Link v-if="link.url" :href="link.url"
                  class="px-3 py-1 text-sm rounded border"
                  :class="link.active ? 'bg-brand-600 text-white border-brand-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                  v-html="link.label" />
            <span v-else class="px-3 py-1 text-sm text-gray-400" v-html="link.label" />
          </template>
        </nav>
      </div>
    </div>

    <!-- Vue Modals -->
    <ConversationQuickView :show="showConvModal" :src="convModalSrc" @close="showConvModal = false" />

    <MergeModal :show="showMerge" :fetchUrl="mergeFetchUrl" :submitUrl="'/people/merge'"
                title="Merge People" entityLabel="person" :items="mergeItems"
                @close="showMerge = false" @loaded="onMergeLoaded">
      <template #item-details="{ item }">
        <span v-if="item.badge" class="mr-2">{{ item.identities_summary }}</span>
        <span>{{ item.companies_summary }}</span>
      </template>
    </MergeModal>

    <FilterRuleModal :show="showFilter" :fetchUrl="filterFetchUrl" :submitUrl="'/data-relations/filtering/apply-rule'"
                     title="Filter People" @close="showFilter = false" />

    <AssignCompanyModal :show="showAssign" :personIds="assignPersonIds" @close="showAssign = false" />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import ConversationQuickView from '../../components/ConversationQuickView.vue'
import MergeModal from '../../components/MergeModal.vue'
import FilterRuleModal from '../../components/FilterRuleModal.vue'
import AssignCompanyModal from '../../components/AssignCompanyModal.vue'

const props = defineProps({
  people: Object,
  search: String,
  sort: String,
  dir: String,
  filteredCount: Number,
  showFiltered: Boolean,
  channelBadge: Object,
  tab: String,
  tabCounts: Object,
  f_lc_from: String,
  f_lc_to: String,
  f_has_company: String,
  f_channel: String,
  channelTypes: Array,
  activeFilterCount: Number,
})

const searchInput = ref(props.search || '')
const showFilterPanel = ref(props.activeFilterCount > 0)
const filterDateFrom = ref(props.f_lc_from || '')
const filterDateTo = ref(props.f_lc_to || '')
const filterHasCompany = ref(props.f_has_company || '')
const filterChannel = ref(props.f_channel || '')
const selectedIds = ref([])

// Modal state
const showConvModal = ref(false)
const convModalSrc = ref('')
const showMerge = ref(false)
const mergeFetchUrl = ref('')
const mergeItems = ref([])
const showFilter = ref(false)
const filterFetchUrl = ref('')
const showAssign = ref(false)
const assignPersonIds = ref([])

let fpInstance = null

const tabItems = computed(() => [
  { key: 'clients', label: 'Clients', count: props.tabCounts.clients },
  { key: 'our_org', label: 'Our Organization', count: props.tabCounts.our_org },
])

const hasFilters = computed(() => {
  return props.search || props.activeFilterCount > 0
})

const allSelected = computed(() => {
  return props.people.data.length > 0 && selectedIds.value.length === props.people.data.length
})

const someSelected = computed(() => {
  return selectedIds.value.length > 0 && selectedIds.value.length < props.people.data.length
})

function buildUrl(overrides) {
  const params = new URLSearchParams(window.location.search)
  for (const [k, v] of Object.entries(overrides)) {
    if (v === undefined) params.delete(k)
    else params.set(k, v)
  }
  return '/people?' + params.toString()
}

function sortUrl(col) {
  return buildUrl({
    sort: col,
    dir: (props.sort === col && props.dir === 'asc') ? 'desc' : 'asc',
  })
}

function sortIcon(col) {
  if (props.sort !== col) return '\u2195'
  return props.dir === 'asc' ? '\u2191' : '\u2193'
}

function applySearch() {
  router.get('/people', {
    tab: props.tab,
    sort: props.sort,
    dir: props.dir,
    q: searchInput.value || undefined,
    f_lc_from: filterDateFrom.value || undefined,
    f_lc_to: filterDateTo.value || undefined,
    f_has_company: filterHasCompany.value || undefined,
    f_channel: filterChannel.value || undefined,
    show_filtered: props.showFiltered ? 1 : undefined,
  }, { preserveState: true })
}

function applyFilters() {
  showFilterPanel.value = false
  applySearch()
}

function clearDateFilter() {
  fpInstance?.clear()
  filterDateFrom.value = ''
  filterDateTo.value = ''
}

function toggleAll(e) {
  if (e.target.checked) {
    selectedIds.value = props.people.data.map(p => p.id)
  } else {
    selectedIds.value = []
  }
}

function openFilterModal(ids) {
  if (!ids) ids = selectedIds.value
  if (!ids.length) return
  const qs = ids.map(id => 'ids[]=' + id).join('&')
  filterFetchUrl.value = '/people/filter-modal?' + qs
  showFilter.value = true
}

function openMergeModal() {
  if (selectedIds.value.length < 2) { alert('Select at least 2 people to merge.'); return }
  const qs = selectedIds.value.map(id => 'ids[]=' + id).join('&')
  mergeFetchUrl.value = '/people/merge-modal?' + qs
  mergeItems.value = []
  showMerge.value = true
}

function onMergeLoaded(data) {
  if (data.people) {
    mergeItems.value = data.people.map(p => ({
      id: p.id,
      name: p.full_name,
      badge: p.is_our_org ? 'Our Org' : null,
      identities_summary: p.identities.map(i => i.value).join(', '),
      companies_summary: p.companies.map(c => c.name).join(', '),
    }))
  }
}

function openAssignCompanyModal(ids) {
  if (!ids) ids = selectedIds.value
  if (!ids.length) return
  assignPersonIds.value = [...ids]
  showAssign.value = true
}

function openConvQuickView(url) {
  convModalSrc.value = url
  showConvModal.value = true
}

function bulkMarkOurOrg() {
  if (!selectedIds.value.length) return
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || ''
  fetch('/people/bulk-mark-our-org', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ ids: selectedIds.value }),
  }).then(r => r.json()).then(d => { if (d.ok) router.reload() })
}

function bulkUnmarkOurOrg() {
  if (!selectedIds.value.length) return
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || ''
  fetch('/people/bulk-unmark-our-org', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ ids: selectedIds.value }),
  }).then(r => r.json()).then(d => { if (d.ok) router.reload() })
}

onMounted(() => {
  if (window.drp && window._EP) {
    fpInstance = window.drp.init('ppl-date-range', function(from, to) {
      filterDateFrom.value = from
      filterDateTo.value = to
    }, {
      defaultFrom: props.f_lc_from || '',
      defaultTo: props.f_lc_to || '',
    })
  }
})
</script>
