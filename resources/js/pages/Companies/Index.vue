<template>
  <AppLayout>
    <Head title="Companies" />

    <div class="page-header">
      <h1 class="page-title">Companies</h1>
      <div class="flex items-center gap-2">
        <template v-if="showFiltered">
          <Link :href="buildUrl({ show_filtered: undefined })" class="btn btn-danger btn-sm">&larr; All Companies</Link>
        </template>
        <template v-else>
          <Link :href="buildUrl({ show_filtered: 1 })" class="btn btn-secondary btn-sm">
            Filtered
            <span v-if="filteredCount > 0"
                  class="ml-1 inline-flex items-center justify-center bg-brand-600 text-white text-xs font-bold rounded-full w-4 h-4 leading-none">{{ filteredCount }}</span>
            <span v-else class="ml-1 text-xs text-gray-400">(0)</span>
          </Link>
        </template>
        <Link v-if="$page.props.auth.permissions.data_write" href="/companies/create" class="btn btn-primary">+ New Company</Link>
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
        <input v-model="searchInput" type="text" placeholder="Search by name, domain, alias..." class="input max-w-[280px]">
        <button type="submit" class="btn btn-secondary">Search</button>
        <Link v-if="hasFilters" href="/companies" class="btn btn-muted">Clear</Link>
      </div>

      <!-- Filter panel -->
      <div v-if="showFilterPanel" class="card p-4 mb-4">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
          <div>
            <label class="label mb-1">Domain</label>
            <input v-model="fDomain" type="text" placeholder="filter..." class="input">
          </div>
          <div>
            <label class="label mb-1">Min contacts</label>
            <input v-model="fPeopleMin" type="number" placeholder="e.g. 2" min="0" class="input">
          </div>
          <template v-for="bp in brandProducts" :key="'bp-' + bp.id">
            <div>
              <label class="label mb-1">{{ bp.name }}{{ bp.variant ? ' \u00b7 ' + bp.variant : '' }} &mdash; stage</label>
              <select v-model="bpFilters[bp.id].stage" class="input">
                <option value="">any</option>
                <option v-for="s in stages" :key="s" :value="s">{{ s.charAt(0).toUpperCase() + s.slice(1) }}</option>
              </select>
            </div>
            <div>
              <label class="label mb-1">{{ bp.name }}{{ bp.variant ? ' \u00b7 ' + bp.variant : '' }} &mdash; score</label>
              <div class="flex items-center gap-1">
                <input v-model="bpFilters[bp.id].score_min" type="number" placeholder="min" min="1" max="10" class="input">
                <span class="text-gray-300 shrink-0">&ndash;</span>
                <input v-model="bpFilters[bp.id].score_max" type="number" placeholder="max" min="1" max="10" class="input">
              </div>
            </div>
          </template>
          <div>
            <label class="label mb-1">Updated</label>
            <div class="flex items-center gap-1.5">
              <input id="co-date-range" type="text" placeholder="Date range..." readonly class="input cursor-pointer flex-1">
              <button v-if="fUpdatedFrom" type="button" @click="clearDateFilter"
                      class="text-base leading-none text-gray-400 hover:text-gray-600 px-1">&times;</button>
            </div>
          </div>
          <div>
            <label class="label mb-1">Channel type</label>
            <select v-model="fConvType" class="input">
              <option value="">any</option>
              <option v-for="ct in channelTypes" :key="ct" :value="ct">{{ ct }}</option>
            </select>
          </div>
        </div>
        <div class="mt-3 flex justify-end gap-2">
          <Link v-if="activeFilterCount > 0"
                :href="buildUrl(clearFilterParams())" class="btn btn-muted">Clear filters</Link>
          <button type="button" @click="applyFilters" class="btn btn-primary">Apply</button>
        </div>
      </div>
    </form>

    <!-- Table -->
    <div class="card overflow-visible relative">
      <!-- Bulk bar -->
      <div v-if="selectedIds.length" class="flex items-center gap-3 px-4 py-2 border-b bulk-bar">
        <span class="text-sm font-medium bulk-bar-text">{{ selectedIds.length }} selected</span>
        <template v-if="$page.props.auth.permissions.data_write">
          <button type="button" @click="openMergeModal" class="btn btn-secondary btn-sm">Merge...</button>
        </template>
        <button type="button" @click="openFilterModal()" class="btn btn-danger btn-sm">Filter...</button>
        <button type="button" @click="selectedIds = []" class="text-xs text-gray-500 hover:text-gray-700">Clear</button>
      </div>

      <div class="overflow-x-auto">
        <table class="text-sm table-fixed" :style="{ width: tableWidth + 'px', minWidth: '100%' }">
          <colgroup>
            <col style="width:36px">
            <col style="width:220px">
            <col class="col-mobile-hidden" style="width:160px">
            <col style="width:150px">
            <template v-if="!brandProducts.length">
              <col class="col-mobile-hidden" style="width:320px">
            </template>
            <template v-else>
              <col v-for="bp in brandProducts" :key="'col-' + bp.id" class="col-mobile-hidden" style="width:160px">
            </template>
            <col class="col-mobile-hidden" style="width:110px">
            <col class="col-mobile-hidden" style="width:120px">
            <col class="col-mobile-only" style="width:44px">
          </colgroup>
          <thead class="tbl-header">
            <tr>
              <th class="px-3 py-2.5 w-8">
                <input type="checkbox" class="rounded border-gray-300 cursor-pointer"
                       :checked="allSelected" :indeterminate.prop="someSelected" @change="toggleAll">
              </th>
              <th class="px-4 py-2.5 text-left">
                <Link :href="sortUrl('name')" class="flex items-center justify-between gap-2 hover:text-gray-900">
                  Company <span class="shrink-0 opacity-60">{{ sortIcon('name') }}</span>
                </Link>
              </th>
              <th class="col-mobile-hidden px-4 py-2.5 text-left">
                <Link :href="sortUrl('domain')" class="flex items-center justify-between gap-2 hover:text-gray-900">
                  Domain <span class="shrink-0 opacity-60">{{ sortIcon('domain') }}</span>
                </Link>
              </th>
              <th class="px-4 py-2.5 text-left">
                <Link :href="sortUrl('contacts')" class="flex items-center justify-between gap-2 hover:text-gray-900">
                  Contacts <span class="shrink-0 opacity-60">{{ sortIcon('contacts') }}</span>
                </Link>
              </th>
              <template v-if="!brandProducts.length">
                <th class="col-mobile-hidden px-4 py-2.5 text-left">
                  <span class="text-xs text-gray-500 font-normal italic">
                    Configure <a href="/segmentation" class="underline hover:text-gray-700 transition">Segmentation</a> to evaluate
                  </span>
                </th>
              </template>
              <template v-else>
                <th v-for="bp in brandProducts" :key="'th-' + bp.id" class="col-mobile-hidden px-2 py-2.5 text-left">
                  <Link :href="sortUrl('bp_score_' + bp.id)"
                        class="flex items-center justify-between gap-1 hover:text-gray-900 text-xs">
                    <span class="leading-tight truncate">{{ bp.name }}{{ bp.variant ? ' \u00b7 ' + bp.variant : '' }}</span>
                    <span class="shrink-0 opacity-60">{{ sortIcon('bp_score_' + bp.id) }}</span>
                  </Link>
                </th>
              </template>
              <th class="col-mobile-hidden px-4 py-2.5 text-left">
                <Link :href="sortUrl('updated_at')" class="flex items-center justify-between gap-2 hover:text-gray-900">
                  Updated <span class="shrink-0 opacity-60">{{ sortIcon('updated_at') }}</span>
                </Link>
              </th>
              <th class="col-mobile-hidden px-4 py-2.5 text-left">
                <Link :href="sortUrl('last_conv')" class="flex items-center justify-between gap-2 hover:text-gray-900">
                  Channels <span class="shrink-0 opacity-60">{{ sortIcon('last_conv') }}</span>
                </Link>
              </th>
              <th class="col-mobile-only px-2 py-2.5"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="company in companies.data" :key="company.id" class="tbl-row group/row">
              <td class="px-3 py-3">
                <input type="checkbox" :value="company.id" v-model="selectedIds"
                       class="rounded border-gray-300 cursor-pointer">
              </td>

              <!-- Company name + alias count + notes -->
              <td class="px-4 py-3 overflow-hidden">
                <div class="flex items-center gap-1.5 min-w-0">
                  <Link :href="`/companies/${company.id}`" :title="company.name"
                        class="font-semibold text-gray-900 hover:text-brand-700 transition truncate">
                    {{ company.name }}
                  </Link>
                  <div v-if="company.non_primary_aliases?.length" class="relative group inline-block shrink-0">
                    <span class="text-xs text-gray-400 cursor-default leading-none">+{{ company.non_primary_aliases.length }}</span>
                    <div class="absolute left-0 top-full mt-1 bg-gray-900 text-white text-xs rounded-lg px-3 py-2
                                invisible opacity-0 group-hover:visible group-hover:opacity-100 transition z-30
                                min-w-max shadow-lg space-y-0.5 pointer-events-none">
                      <div v-for="(alias, i) in company.non_primary_aliases" :key="i">{{ alias }}</div>
                    </div>
                  </div>
                  <span v-if="showFiltered && company.filtered_reason"
                        :title="company.filtered_reason"
                        class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-red-100 text-red-600 text-[10px] font-bold shrink-0 cursor-default leading-none">i</span>
                  <div class="flex-1"></div>
                  <button v-if="$page.props.auth.permissions.data_write" type="button"
                          @click="openFilterModal([company.id])"
                          title="Filter"
                          class="shrink-0 text-gray-300 hover:text-red-500 transition leading-none md:opacity-0 md:group-hover/row:opacity-100 focus:opacity-100">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
                  </button>
                  <span v-if="company.notes_count" class="text-xs text-gray-400" :title="company.notes_count + ' notes'">
                    <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                    {{ company.notes_count }}
                  </span>
                </div>
              </td>

              <!-- Domain -->
              <td class="col-mobile-hidden px-4 py-3 overflow-hidden">
                <div v-if="company.primary_domain_name" class="flex items-center gap-1.5 min-w-0">
                  <span class="font-mono text-xs text-gray-600 truncate" :title="company.primary_domain_name">{{ company.primary_domain_name }}</span>
                  <div v-if="company.extra_domains_count > 0" class="relative group inline-block shrink-0">
                    <span class="text-xs text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded font-medium leading-none cursor-default">+{{ company.extra_domains_count }}</span>
                    <div class="absolute left-0 top-full mt-1 bg-gray-900 text-white text-xs rounded-lg px-3 py-2
                                invisible opacity-0 group-hover:visible group-hover:opacity-100 transition z-30
                                min-w-max shadow-lg space-y-0.5 pointer-events-none">
                      <div v-for="(d, i) in company.extra_domains" :key="i" class="font-mono">{{ d }}</div>
                    </div>
                  </div>
                </div>
                <span v-else class="text-gray-300">&mdash;</span>
              </td>

              <!-- Contacts -->
              <td class="px-4 py-3">
                <template v-if="company.contacts_count > 0">
                  <button type="button" @click="contactsPopupId = contactsPopupId === company.id ? null : company.id"
                          class="flex items-center justify-start cursor-pointer group">
                    <div v-for="(person, i) in company.visible_people" :key="person.id"
                         :class="['w-7 h-7 rounded-full bg-brand-100 text-brand-700 border-2 border-white flex items-center justify-center text-xs font-bold shrink-0 group-hover:border-brand-200 transition', i > 0 ? '-ml-1.5' : '']">
                      {{ person.initials }}
                    </div>
                    <div v-if="company.extra_people_count > 0"
                         class="w-7 h-7 rounded-full bg-gray-100 text-gray-500 border-2 border-white flex items-center justify-center text-xs font-semibold shrink-0 -ml-1.5 group-hover:bg-gray-200 transition">
                      +{{ company.extra_people_count }}
                    </div>
                  </button>
                </template>
                <span v-else class="text-gray-300 text-xs">&mdash;</span>
              </td>

              <!-- Brand statuses -->
              <template v-if="!brandProducts.length">
                <td class="col-mobile-hidden"></td>
              </template>
              <template v-else>
                <td v-for="bp in brandProducts" :key="'bs-' + company.id + '-' + bp.id"
                    class="col-mobile-hidden px-2 py-2 text-center">
                  <template v-if="getBrandStatus(company, bp.id)">
                    <button type="button" @click="brandPopup = { company, bp, status: getBrandStatus(company, bp.id) }"
                            :class="['w-full flex items-center gap-1.5 px-1.5 py-1 rounded-lg transition cursor-pointer hover:ring-1 hover:ring-gray-200',
                                     stageCell(getBrandStatus(company, bp.id).stage)]">
                      <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold text-white shrink-0"
                            :style="{ background: scoreColorMap[getBrandStatus(company, bp.id).score] || '#d1d5db' }">
                        {{ getBrandStatus(company, bp.id).score || '?' }}
                      </span>
                      <span :class="['text-[11px] font-medium leading-tight truncate', stageText(getBrandStatus(company, bp.id).stage)]">
                        {{ getBrandStatus(company, bp.id).stage ? getBrandStatus(company, bp.id).stage.charAt(0).toUpperCase() + getBrandStatus(company, bp.id).stage.slice(1) : '' }}
                      </span>
                    </button>
                  </template>
                  <span v-else class="text-gray-300 text-xs">&mdash;</span>
                </td>
              </template>

              <!-- Updated -->
              <td class="col-mobile-hidden px-4 py-3 text-xs text-gray-400 whitespace-nowrap">
                <span :title="company.updated_at_full">{{ company.updated_at_human }}</span>
              </td>

              <!-- Channels -->
              <td class="col-mobile-hidden px-4 py-3">
                <div v-if="company.conv_channels.length" class="flex items-center gap-1 flex-wrap">
                  <Link v-for="ch in company.conv_channels" :key="ch"
                        :href="`/conversations?f_company=${company.id}&f_channel=${ch}`"
                        :class="['inline-flex items-center justify-center w-6 h-6 rounded text-[10px] font-bold shrink-0',
                                 convTypeMap[ch]?.cls || 'bg-slate-100 text-slate-700']"
                        :style="convTypeMap[ch]?.style || ''"
                        :title="ch">
                    {{ ch.charAt(0).toUpperCase() }}
                  </Link>
                </div>
                <span v-else class="text-gray-300 text-xs">&mdash;</span>
              </td>

              <!-- Mobile actions -->
              <td class="col-mobile-only px-2 py-3 text-right">
                <div class="relative inline-block">
                  <button type="button" @click.stop="mobileMenuId = mobileMenuId === company.id ? null : company.id"
                          class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <circle cx="10" cy="4" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="10" cy="16" r="1.5"/>
                    </svg>
                  </button>
                  <div v-if="mobileMenuId === company.id"
                       class="absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-30 py-1.5 w-36">
                    <Link :href="`/companies/${company.id}`"
                          class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">View</Link>
                    <button v-if="$page.props.auth.permissions.data_write" type="button"
                            @click="openFilterModal([company.id])"
                            class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Filter</button>
                  </div>
                </div>
              </td>
            </tr>
            <tr v-if="!companies.data.length">
              <td :colspan="6 + Math.max(1, brandProducts.length)" class="px-4 py-10 text-center empty-state italic">No companies found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="companies.links && companies.last_page > 1" class="px-4 py-3 border-t border-gray-100">
        <nav class="flex items-center gap-1">
          <template v-for="link in companies.links" :key="link.label">
            <Link v-if="link.url" :href="link.url"
                  class="px-3 py-1 text-sm rounded border"
                  :class="link.active ? 'bg-brand-600 text-white border-brand-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                  v-html="link.label" />
            <span v-else class="px-3 py-1 text-sm text-gray-400" v-html="link.label" />
          </template>
        </nav>
      </div>
    </div>

    <!-- Contacts popup -->
    <Teleport to="body">
      <template v-if="contactsPopupId">
        <div class="fixed inset-0 bg-black/40 z-40" @click="contactsPopupId = null"></div>
        <div class="fixed z-50 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
                    w-[360px] bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Contacts &mdash; {{ contactsCompany?.name }}</h3>
            <button type="button" @click="contactsPopupId = null"
                    class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
          </div>
          <ul class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
            <li v-for="person in contactsCompany?.all_contacts" :key="person.id">
              <Link :href="`/people/${person.id}`"
                    class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition">
                <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-xs font-bold shrink-0">
                  {{ person.initials }}
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-800 truncate">{{ person.full_name }}</p>
                  <p v-if="person.role" class="text-xs text-gray-400">{{ person.role }}</p>
                </div>
                <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </Link>
            </li>
          </ul>
        </div>
      </template>
    </Teleport>

    <!-- Brand status popup -->
    <Teleport to="body">
      <template v-if="brandPopup">
        <div class="fixed inset-0 bg-black/40 z-40" @click="brandPopup = null"></div>
        <div class="fixed z-50 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
                    w-[340px] bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div>
              <h3 class="font-semibold text-gray-800">{{ brandPopup.company.name }}</h3>
              <p class="text-xs text-gray-500">{{ brandPopup.bp.name }}{{ brandPopup.bp.variant ? ' \u00b7 ' + brandPopup.bp.variant : '' }}</p>
            </div>
            <button type="button" @click="brandPopup = null"
                    class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
          </div>
          <div class="px-5 py-4 flex flex-col items-center gap-3">
            <div class="relative w-20 h-20">
              <svg class="w-20 h-20 -rotate-90" viewBox="0 0 80 80">
                <circle cx="40" cy="40" r="34" fill="none" stroke="#e5e7eb" stroke-width="6" />
                <circle cx="40" cy="40" r="34" fill="none"
                        :stroke="scoreColorMap[brandPopup.status.score] || '#d1d5db'"
                        stroke-width="6" stroke-linecap="round"
                        :stroke-dasharray="2 * Math.PI * 34"
                        :stroke-dashoffset="2 * Math.PI * 34 * (1 - (brandPopup.status.score || 0) / 10)" />
              </svg>
              <div class="absolute inset-0 flex items-center justify-center text-lg font-bold text-gray-800">
                {{ brandPopup.status.score ?? '?' }}<span class="text-xs text-gray-400 font-normal">/10</span>
              </div>
            </div>
            <span :class="['badge', stageBadge(brandPopup.status.stage)]">
              {{ brandPopup.status.stage ? brandPopup.status.stage.charAt(0).toUpperCase() + brandPopup.status.stage.slice(1) : 'N/A' }}
            </span>
            <p v-if="brandPopup.status.last_evaluated_at" class="text-xs text-gray-400">
              Last evaluated {{ brandPopup.status.last_evaluated_at }}
            </p>
            <p v-if="brandPopup.status.notes" class="text-sm text-gray-600 text-center">{{ brandPopup.status.notes }}</p>
            <Link :href="`/companies/${brandPopup.company.id}`" class="btn btn-sm btn-secondary mt-1">View Company</Link>
          </div>
        </div>
      </template>
    </Teleport>

    <!-- Vue Modals -->
    <MergeModal :show="showMerge" :fetchUrl="mergeFetchUrl" :submitUrl="'/companies/merge'"
                title="Merge Companies" entityLabel="company" :items="mergeItems"
                @close="showMerge = false" @loaded="onMergeLoaded">
      <template #item-details="{ item }">
        <span>{{ item.details }}</span>
      </template>
    </MergeModal>

    <FilterRuleModal :show="showFilter" :fetchUrl="filterFetchUrl" :submitUrl="'/data-relations/filtering/apply-rule'"
                     title="Filter Companies" @close="showFilter = false" />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import MergeModal from '../../components/MergeModal.vue'
import FilterRuleModal from '../../components/FilterRuleModal.vue'

const props = defineProps({
  companies: Object,
  search: String,
  sort: String,
  dir: String,
  brandProducts: Array,
  channelTypes: Array,
  filteredCount: Number,
  showFiltered: Boolean,
  scoreColorMap: Object,
  convTypeMap: Object,
  activeFilterCount: Number,
  hasFilters: Boolean,
  tab: String,
  tabCounts: Object,
  f_domain: String,
  f_people_min: [String, Number],
  f_conv_type: String,
  f_updated_from: String,
  f_updated_to: String,
  brandFilters: Object,
})

const stages = ['lead', 'prospect', 'trial', 'active', 'churned']
const searchInput = ref(props.search || '')
const showFilterPanel = ref(props.activeFilterCount > 0)
const fDomain = ref(props.f_domain || '')
const fPeopleMin = ref(props.f_people_min || '')
const fConvType = ref(props.f_conv_type || '')
const fUpdatedFrom = ref(props.f_updated_from || '')
const fUpdatedTo = ref(props.f_updated_to || '')
const selectedIds = ref([])
const contactsPopupId = ref(null)
const brandPopup = ref(null)
const mobileMenuId = ref(null)

// Modal state
const showMerge = ref(false)
const mergeFetchUrl = ref('')
const mergeItems = ref([])
const showFilter = ref(false)
const filterFetchUrl = ref('')

let fpInstance = null

// Init brand product filter state
const bpFilters = ref({})
for (const bp of props.brandProducts) {
  bpFilters.value[bp.id] = {
    stage: props.brandFilters?.[bp.id]?.stage || '',
    score_min: props.brandFilters?.[bp.id]?.score_min || '',
    score_max: props.brandFilters?.[bp.id]?.score_max || '',
  }
}

const tableWidth = computed(() => {
  const bpCols = props.brandProducts.length || 1
  return 36 + 220 + 160 + 150 + (bpCols * 160) + 110 + 120
})

const tabItems = computed(() => [
  { key: 'clients', label: 'Clients', count: props.tabCounts.clients },
  { key: 'our_org', label: 'Our Organization', count: props.tabCounts.our_org },
])

const allSelected = computed(() => props.companies.data.length > 0 && selectedIds.value.length === props.companies.data.length)
const someSelected = computed(() => selectedIds.value.length > 0 && selectedIds.value.length < props.companies.data.length)

const contactsCompany = computed(() => {
  if (!contactsPopupId.value) return null
  return props.companies.data.find(c => c.id === contactsPopupId.value)
})

function buildUrl(overrides) {
  const params = new URLSearchParams(window.location.search)
  for (const [k, v] of Object.entries(overrides)) {
    if (v === undefined) params.delete(k)
    else params.set(k, v)
  }
  return '/companies?' + params.toString()
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
  const params = {
    tab: props.tab,
    sort: props.sort,
    dir: props.dir,
    q: searchInput.value || undefined,
    f_domain: fDomain.value || undefined,
    f_people_min: fPeopleMin.value || undefined,
    f_conv_type: fConvType.value || undefined,
    f_updated_from: fUpdatedFrom.value || undefined,
    f_updated_to: fUpdatedTo.value || undefined,
    show_filtered: props.showFiltered ? 1 : undefined,
  }
  for (const bp of props.brandProducts) {
    const f = bpFilters.value[bp.id]
    if (f.stage) params[`f_bp_${bp.id}_stage`] = f.stage
    if (f.score_min) params[`f_bp_${bp.id}_score_min`] = f.score_min
    if (f.score_max) params[`f_bp_${bp.id}_score_max`] = f.score_max
  }
  router.get('/companies', params, { preserveState: true })
}

function applyFilters() {
  showFilterPanel.value = false
  applySearch()
}

function clearFilterParams() {
  const overrides = {
    f_domain: undefined,
    f_people_min: undefined,
    f_conv_type: undefined,
    f_updated_from: undefined,
    f_updated_to: undefined,
  }
  for (const bp of props.brandProducts) {
    overrides[`f_bp_${bp.id}_stage`] = undefined
    overrides[`f_bp_${bp.id}_score_min`] = undefined
    overrides[`f_bp_${bp.id}_score_max`] = undefined
  }
  return overrides
}

function clearDateFilter() {
  fpInstance?.clear()
  fUpdatedFrom.value = ''
  fUpdatedTo.value = ''
}

function toggleAll(e) {
  if (e.target.checked) {
    selectedIds.value = props.companies.data.map(c => c.id)
  } else {
    selectedIds.value = []
  }
}

function openFilterModal(ids) {
  if (!ids) ids = selectedIds.value
  if (!ids.length) return
  const qs = ids.map(id => 'ids[]=' + id).join('&')
  filterFetchUrl.value = '/companies/filter-modal?' + qs
  showFilter.value = true
}

function openMergeModal() {
  if (selectedIds.value.length < 2) { alert('Select at least 2 companies to merge.'); return }
  const qs = selectedIds.value.map(id => 'ids[]=' + id).join('&')
  mergeFetchUrl.value = '/companies/merge-modal?' + qs
  mergeItems.value = []
  showMerge.value = true
}

function onMergeLoaded(data) {
  if (data.companies) {
    mergeItems.value = data.companies.map(c => ({
      id: c.id,
      name: c.name,
      badge: null,
      details: [
        c.domains_count + ' domains',
        c.accounts_count + ' accounts',
        c.contacts_count + ' contacts',
        c.conversations_count + ' conversations',
      ].join(', '),
    }))
  }
}

function getBrandStatus(company, bpId) {
  return company.brand_display?.find(b => b.brand_product_id === bpId) || null
}

function stageCell(stage) {
  const map = {
    lead: 'bg-blue-50',
    prospect: 'bg-indigo-50',
    trial: 'bg-amber-50',
    active: 'bg-green-50',
    churned: 'bg-red-50',
  }
  return map[stage] || ''
}

function stageText(stage) {
  const map = {
    lead: 'text-blue-700',
    prospect: 'text-indigo-700',
    trial: 'text-amber-700',
    active: 'text-green-700',
    churned: 'text-red-700',
  }
  return map[stage] || 'text-gray-500'
}

function stageBadge(stage) {
  const map = {
    lead: 'badge-blue',
    prospect: 'badge-blue',
    trial: 'badge-yellow',
    active: 'badge-green',
    churned: 'badge-red',
  }
  return map[stage] || 'badge-gray'
}

function handleKeydown(e) {
  if (e.key === 'Escape') {
    contactsPopupId.value = null
    brandPopup.value = null
    mobileMenuId.value = null
  }
}

function handleClickOutside() {
  mobileMenuId.value = null
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
  document.addEventListener('click', handleClickOutside)

  if (window.drp && window._EP) {
    fpInstance = window.drp.init('co-date-range', function(from, to) {
      fUpdatedFrom.value = from
      fUpdatedTo.value = to
    }, {
      defaultFrom: props.f_updated_from || '',
      defaultTo: props.f_updated_to || '',
    })
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown)
  document.removeEventListener('click', handleClickOutside)
})
</script>
