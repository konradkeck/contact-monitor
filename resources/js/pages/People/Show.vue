<template>
  <AppLayout>
    <!-- Popups (Link company, Add identity) defined as Modal components below -->

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div>
        <nav aria-label="Breadcrumb" class="page-breadcrumb">
          <template v-if="backLink">
            <a :href="backLink.url">{{ backLink.label }}</a>
            <span class="sep">/</span>
          </template>
          <a href="/people">People</a>
          <span class="sep">/</span>
          <span class="cur" aria-current="page">{{ person.full_name }}</span>
        </nav>
        <h1 class="page-title mt-1">{{ person.full_name }}</h1>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <button type="button" @click="showPersonFilterModal" class="btn btn-secondary btn-sm">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
          <span class="hidden sm:inline">Filter</span>
        </button>
        <template v-if="canWrite">
          <button v-if="person.is_our_org" type="button" @click="unmarkOurOrg" class="btn btn-muted btn-sm">
            <span class="hidden sm:inline">Unmark Our Org</span>
            <span class="sm:hidden">Unmark</span>
          </button>
          <button v-else type="button" @click="markOurOrg" class="btn btn-org btn-sm">
            <span class="hidden sm:inline">Our Org</span>
            <span class="sm:hidden">Org</span>
          </button>
          <button type="button" @click="showAssignCompany" class="btn btn-secondary btn-sm">
            <span class="hidden sm:inline">Assign Company</span>
            <span class="sm:hidden">Assign</span>
          </button>
          <a :href="`/people/${person.id}/edit`" class="btn btn-secondary btn-sm">Edit</a>
        </template>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">

      <!-- LEFT COLUMN -->
      <div class="space-y-4 order-1">

        <!-- Avatar + name + companies -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <!-- Dark header -->
          <div :class="person.is_our_org ? 'bg-gradient-to-b from-brand-600 to-brand-800' : 'bg-gradient-to-b from-[#1c2028] to-[#252d3b]'"
               class="px-5 pt-5 pb-10 flex flex-col items-center text-center">
            <span v-if="person.is_our_org"
                  class="mb-2 inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/15 text-white/90 border border-white/20">
              Our Org
            </span>
            <div :class="person.is_our_org ? 'border-white/30' : 'border-white/20'"
                 class="w-14 h-14 rounded-full flex items-center justify-center text-lg font-bold bg-gray-600 text-white border-2 mb-3">
              {{ person.initials }}
            </div>
            <p class="font-bold text-white text-base leading-snug">{{ person.full_name }}</p>
            <p class="text-xs text-white/60 mt-1">Since {{ person.created_at }}</p>
          </div>

          <!-- Companies lifted card -->
          <div class="-mt-4 mx-4 mb-4 bg-white rounded-lg border border-gray-200 shadow-sm">
            <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
              <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Assigned Companies</span>
              <button v-if="canWrite" type="button" @click="showAssignCompany"
                      class="text-xs font-medium text-gray-500 hover:text-brand-700 border border-gray-200 hover:border-brand-400 px-2 py-0.5 rounded transition">
                + Assign
              </button>
            </div>
            <p v-if="!companies.length" class="px-4 py-3 text-xs text-gray-400 italic">Not linked to any company.</p>
            <ul v-else class="divide-y divide-gray-100">
              <li v-for="c in companies" :key="c.id" class="px-4 py-2.5 flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <a :href="`/companies/${c.id}`" class="text-sm font-medium link block truncate">{{ c.name }}</a>
                  <span v-if="c.role" class="text-xs text-gray-400">{{ c.role }}</span>
                </div>
                <form v-if="canWrite" @submit.prevent="unlinkCompany(c)" class="shrink-0 mt-0.5">
                  <button type="submit" class="text-xs text-red-400 hover:text-red-600">✕</button>
                </form>
              </li>
            </ul>
          </div>
        </div>

        <!-- Identities -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-sm">Identities</h3>
            <button v-if="canWrite" @click="popup = 'add-identity'"
                    class="text-xs font-medium text-brand-600 hover:text-brand-700 border border-brand-200
                           hover:border-brand-400 px-3 py-1 rounded-full transition">
              + Add
            </button>
          </div>
          <p v-if="!identities.length" class="px-4 py-4 text-sm text-gray-400 italic">No identities yet.</p>
          <ul v-else class="divide-y divide-gray-50">
            <li v-for="identity in identities" :key="identity.id" class="px-4 py-2 flex items-center justify-between gap-2">
              <div class="flex items-center gap-2 min-w-0">
                <IdentityIcon :type="identity.type" :sysType="identity.system_type" />
                <img v-if="identity.avatar && (identity.type === 'discord_user' || identity.type === 'discord_id')"
                     :src="`https://cdn.discordapp.com/avatars/${identity.value_normalized}/${identity.avatar}.webp?size=32`"
                     class="w-5 h-5 rounded-full shrink-0 ring-1 ring-gray-200" alt="avatar">
                <img v-else-if="identity.avatar && identity.type === 'slack_user'"
                     :src="identity.avatar"
                     class="w-5 h-5 rounded-full shrink-0 ring-1 ring-gray-200" alt="avatar">
                <span v-if="identity.display_name" class="text-xs text-gray-700 truncate font-medium">{{ identity.display_name }}</span>
                <template v-if="!['discord_user','discord_id','slack_user'].includes(identity.type) || !identity.display_name">
                  <a v-if="identity.href" :href="identity.href" target="_blank" rel="noopener"
                     class="font-mono text-xs link truncate">{{ identity.value }}</a>
                  <span v-else class="font-mono text-xs text-gray-600 truncate">{{ identity.value }}</span>
                </template>
              </div>
              <form v-if="canWrite" @submit.prevent="deleteIdentity(identity)" class="shrink-0">
                <button type="submit" class="text-xs text-red-400 hover:text-red-600">✕</button>
              </form>
            </li>
          </ul>
        </div>

        <!-- Notes -->
        <NotesSection
          :notes="notes"
          linkableType="App\Models\Person"
          :linkableId="person.id"
        />

        <!-- Merged people -->
        <div v-if="mergedPeople.length">
          <div class="flex items-center justify-between mb-2 px-1">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">
              Merged
              <span class="ml-1 px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs font-bold">{{ mergedPeople.length }}</span>
            </p>
          </div>
          <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            <div v-for="mp in mergedPeople" :key="mp.id" class="flex items-center gap-3 px-4 py-3">
              <div class="flex-1 min-w-0">
                <a :href="`/people/${mp.id}`" class="font-medium text-sm text-gray-800 hover:text-brand-700 truncate block">
                  {{ mp.full_name }}
                </a>
                <p v-if="mp.first_identity" class="text-xs text-gray-400 font-mono truncate">{{ mp.first_identity }}</p>
              </div>
              <form v-if="canWrite" @submit.prevent="unmerge(mp)">
                <button type="submit" class="text-xs text-gray-400 hover:text-red-600 transition shrink-0">Unmerge</button>
              </form>
            </div>
          </div>
        </div>

        <!-- Conversations -->
        <div v-if="convGroups.length">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">Conversations</p>
          <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-50 overflow-hidden">
            <a v-for="g in convGroups" :key="g.channel_type + g.system_slug"
               :href="`/conversations?person_id=${person.id}&channel_type=${g.channel_type}&system_slug=${g.system_slug}`"
               class="px-4 py-2.5 flex items-center gap-2.5 hover:bg-gray-50 transition">
              <ChannelBadge :type="g.channel_type" />
              <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-700 truncate leading-snug" :title="g.last_subject">
                  {{ truncate(g.last_subject || '(no subject)', 38) }}
                </p>
                <p class="text-xs text-gray-400 mt-0.5">
                  <span class="font-mono">{{ g.system_slug }}</span>
                  · {{ g.conv_count }}
                </p>
              </div>
              <p class="text-xs text-gray-400 shrink-0 whitespace-nowrap">{{ timeAgo(g.last_message_at) }}</p>
            </a>
          </div>
        </div>

      </div><!-- /LEFT -->

      <!-- CENTER: TIMELINE (col-span-2) -->
      <div class="col-span-1 md:col-span-2 order-3 md:order-2">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <!-- Tab bar -->
          <div class="flex items-center border-b border-gray-100 px-4 pt-1">
            <button v-for="tab in timelineTabs" :key="tab.key"
                    @click="setTimelineTab(tab.key)"
                    class="px-4 py-2.5 text-sm font-medium border-b-2 transition whitespace-nowrap mr-1"
                    :class="activeTimelineTab === tab.key
                      ? (tab.key === 'filtered' ? 'border-red-400 text-red-600' : 'border-brand-500 text-brand-700')
                      : (tab.key === 'filtered' ? 'border-transparent text-gray-300 hover:text-red-500' : 'border-transparent text-gray-400 hover:text-gray-700')">
              {{ tab.label }}
            </button>
          </div>

          <!-- Filter bar -->
          <div class="px-5 pt-3 pb-3 border-b border-gray-100">
            <div class="flex items-center gap-3">
              <!-- Conv filter dropdown -->
              <div v-show="activeTimelineTab === 'conversations'" class="relative" ref="convDropdownRef">
                <button @click="convDropdownOpen = !convDropdownOpen"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs text-gray-600 hover:border-gray-300 transition min-w-[130px]">
                  <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/></svg>
                  <span class="flex-1 text-left">{{ convDropdownLabel }}</span>
                  <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div v-show="convDropdownOpen" class="absolute left-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1.5 w-64">
                  <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                    <input type="checkbox" class="rounded border-gray-300" :checked="!tlActiveSystems.length && !tlShowFiltered" @change="tlConvAll">
                    <span class="text-sm text-gray-700 font-medium">All</span>
                  </label>
                  <label v-if="filteredConvCount > 0" class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                    <input type="checkbox" class="rounded border-gray-300" :checked="tlShowFiltered" @change="tlToggleFiltered">
                    <span class="w-2 h-2 rounded-full bg-red-400 shrink-0"></span>
                    <span class="text-sm text-gray-700">Filtered ({{ filteredConvCount }})</span>
                  </label>
                  <template v-if="convSystems.length">
                    <div class="border-t border-gray-100 my-1"></div>
                    <label v-for="sys in convSystems" :key="sys.channel_type + '|' + sys.system_slug"
                           class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                      <input type="checkbox" class="rounded border-gray-300"
                             :checked="tlActiveSystems.includes(sys.channel_type + '|' + sys.system_slug)"
                             @change="tlToggleSystem(sys.channel_type + '|' + sys.system_slug)">
                      <ChannelBadge :type="sys.channel_type" :label="false" />
                      <span class="text-xs text-gray-700 truncate">{{ sys.system_slug }}</span>
                    </label>
                  </template>
                </div>
              </div>

              <!-- Activity filter dropdown -->
              <div v-show="activeTimelineTab === 'activity'" class="relative" ref="actDropdownRef">
                <button @click="actDropdownOpen = !actDropdownOpen"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs text-gray-600 hover:border-gray-300 transition min-w-[130px]">
                  <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/></svg>
                  <span class="flex-1 text-left">{{ actDropdownLabel }}</span>
                  <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div v-show="actDropdownOpen" class="absolute left-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1.5 w-52">
                  <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                    <input type="checkbox" class="rounded border-gray-300" :checked="!tlActiveActTypes.length" @change="tlActAll">
                    <span class="text-sm text-gray-700 font-medium">All</span>
                  </label>
                  <template v-if="activityTypes.length">
                    <div class="border-t border-gray-100 my-1"></div>
                    <label v-for="t in activityTypes" :key="t"
                           class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                      <input type="checkbox" class="rounded border-gray-300"
                             :checked="tlActiveActTypes.includes(t)" @change="tlToggleActType(t)">
                      <span class="w-2 h-2 rounded-full shrink-0" :class="typeColors[t] || 'bg-slate-300'"></span>
                      <span class="text-sm text-gray-700">{{ formatType(t) }}</span>
                    </label>
                  </template>
                </div>
              </div>

              <div class="flex-1"></div>
              <button v-show="hasTimelineFilters" @click="resetTimelineFilters"
                      class="text-xs text-gray-400 hover:text-gray-600 transition whitespace-nowrap">✕ Clear</button>
              <input ref="dateRangeInput" type="text" placeholder="Date range…"
                     class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-600 bg-white focus:outline-none cursor-pointer w-44" readonly>
            </div>
          </div>

          <!-- Timeline body -->
          <div class="relative px-4 py-2 min-h-[120px]">
            <div class="absolute inset-y-0 left-1/2 -translate-x-1/2 w-px bg-gray-200 pointer-events-none z-0"></div>
            <div class="relative z-10">
              <Timeline
                :activities="tlItems"
                :initialCursor="tlNextCursor"
                :timelineUrl="timelineUrl"
                :showCompanyLink="true"
                gridClass="grid grid-cols-[1fr_2rem_1fr]"
                @openModal="openModal"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN: Quick Reports -->
      <div class="space-y-3 order-2 md:order-3">
        <div class="flex items-center justify-between gap-3 px-1">
          <p class="text-sm font-semibold text-gray-700">Quick Reports</p>
          <input ref="graphDateInput" type="text" placeholder="Last 365 days"
                 class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-600 bg-white focus:outline-none cursor-pointer w-32" readonly>
        </div>

        <!-- Hourly Activity chart -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div class="px-3 py-2 border-b border-gray-100">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Hourly Activity</h3>
            <p class="text-[10px] text-gray-400 mt-0.5">Messages sent per hour of day</p>
          </div>
          <div class="px-3 pt-2.5 pb-2">
            <div class="relative" ref="agWrap">
              <div ref="agTip"
                   class="absolute hidden z-10 px-2 py-1 rounded text-[10px] font-medium text-white pointer-events-none"
                   style="background:rgba(30,30,30,0.85);bottom:calc(100% + 4px);transform:translateX(-50%)"></div>
              <div ref="agChart" class="flex items-end gap-px" style="height:64px">
                <div v-for="h in 24" :key="h - 1" class="ag-bar flex-1 rounded-sm cursor-default"
                     :data-hour="h - 1" style="height:2px;background:rgba(164,0,87,0.25)"></div>
              </div>
            </div>
            <div class="flex justify-between text-[9px] text-gray-300 mt-1">
              <span>0h</span><span>6h</span><span>12h</span><span>18h</span><span>23h</span>
            </div>
            <p ref="agTotal" class="text-[9px] text-gray-400 mt-1"></p>
          </div>
        </div>

        <!-- Weekly Availability heatmap -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div class="px-3 py-2 border-b border-gray-100">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Weekly Availability</h3>
            <p class="text-[10px] text-gray-400 mt-0.5">Active hours per day of week</p>
          </div>
          <div class="px-3 pt-3 pb-2">
            <div class="flex gap-1.5">
              <div class="flex flex-col justify-between text-[9px] text-gray-300 pr-0.5 shrink-0" style="height:120px">
                <span>0h</span><span>6h</span><span>12h</span><span>18h</span><span>23h</span>
              </div>
              <div v-for="(label, dow) in dayLabels" :key="dow" class="flex-1 flex flex-col items-center">
                <div class="w-full flex flex-col" style="height:120px">
                  <div v-for="h in 24" :key="h - 1" class="av-cell flex-1"
                       :data-dow="dow" :data-hour="h - 1"
                       style="background:rgba(243,244,246,0.9)"></div>
                </div>
                <span class="text-[9px] text-gray-400 mt-1.5">{{ label }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Add Identity Modal -->
    <Modal v-if="canWrite" :show="popup === 'add-identity'" @close="popup = null" size="sm">
      <template #header>Add identity</template>
      <form @submit.prevent="addIdentity" class="px-5 py-4 flex flex-col gap-3">
        <div>
          <label class="text-xs text-gray-500 mb-1 block">Type</label>
          <select v-model="newIdentity.type"
                  class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
            <option v-for="t in identityTypes" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
        <div>
          <label class="text-xs text-gray-500 mb-1 block">Value</label>
          <input v-model="newIdentity.value" type="text" placeholder="e.g. user@example.com"
                 class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
        </div>
        <button type="submit" class="w-full py-2 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition">
          Add identity
        </button>
      </form>
    </Modal>

    <!-- Link Company Modal -->
    <Modal v-if="canWrite && allCompanies.length" :show="popup === 'link-company'" @close="popup = null" size="sm">
      <template #header>Link company</template>
      <form @submit.prevent="linkCompany" class="px-5 py-4 flex flex-col gap-3">
        <div>
          <label class="text-xs text-gray-500 mb-1 block">Company</label>
          <select v-model="linkForm.company_id"
                  class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
            <option v-for="c in allCompanies" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="text-xs text-gray-500 mb-1 block">Role <span class="text-gray-300">(optional)</span></label>
          <input v-model="linkForm.role" type="text" placeholder="e.g. CTO, Owner…"
                 class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
        </div>
        <button type="submit" class="w-full py-2 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition">
          Link company
        </button>
      </form>
    </Modal>

    <ConversationQuickView :show="showConvModal" :src="convModalSrc" @close="showConvModal = false" />

    <FilterRuleModal :show="showFilterModal" :fetchUrl="filterFetchUrl" :submitUrl="'/data-relations/filtering/apply-rule'"
                     title="Filter Person" @close="showFilterModal = false" />

  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import Modal from '../../components/Modal.vue'
import NotesSection from '../../components/NotesSection.vue'
import ChannelBadge from '../../components/ChannelBadge.vue'
import IdentityIcon from '../../components/IdentityIcon.vue'
import Timeline from '../../components/Timeline.vue'
import ConversationQuickView from '../../components/ConversationQuickView.vue'
import FilterRuleModal from '../../components/FilterRuleModal.vue'

const props = defineProps({
  person: Object,
  identities: Array,
  companies: Array,
  allCompanies: Array,
  notes: Array,
  convGroups: Array,
  mergedPeople: Array,
  timeline: Object,
  convSystems: Array,
  filteredConvCount: Number,
  activityTypes: Array,
  typeColors: Object,
  backLink: Object,
  filterModalUrl: String,
  assignCompanyModalUrl: String,
  hourlyActivityUrl: String,
  activityAvailabilityUrl: String,
})

const page = usePage()
const canWrite = computed(() => page.props.auth?.permissions?.data_write)

const popup = ref(null)
const showConvModal = ref(false)
const convModalSrc = ref('')
const showFilterModal = ref(false)
const filterFetchUrl = ref('')
const newIdentity = ref({ type: 'email', value: '' })
const identityTypes = ['email', 'slack_id', 'discord_id', 'phone', 'linkedin', 'twitter']
const linkForm = ref({ company_id: props.allCompanies[0]?.id, role: '' })

const dayLabels = { 1: 'M', 2: 'T', 3: 'W', 4: 'T', 5: 'F', 6: 'S', 7: 'S' }

// Timeline state (same pattern as Companies/Show)
const activeTimelineTab = ref('conversations')
const tlActiveSystems = ref([])
const tlShowFiltered = ref(false)
const tlActiveActTypes = ref([])
const tlItems = ref([...props.timeline.items])
const tlNextCursor = ref(props.timeline.nextCursor)
const convDropdownOpen = ref(false)
const actDropdownOpen = ref(false)
const convDropdownRef = ref(null)
const actDropdownRef = ref(null)
const dateRangeInput = ref(null)
const graphDateInput = ref(null)
let dateFrom = ''
let dateTo = ''
let agFrom = ''
let agTo = ''

const timelineTabs = [
  { key: 'conversations', label: 'Conversations' },
  { key: 'activity', label: 'Activity' },
  { key: 'all', label: 'All' },
  { key: 'filtered', label: 'Filtered' },
]

const convDropdownLabel = computed(() => {
  const total = tlActiveSystems.value.length + (tlShowFiltered.value ? 1 : 0)
  if (total === 0) return 'All'
  if (total === 1) {
    if (tlShowFiltered.value && !tlActiveSystems.value.length) return 'Filtered'
    return tlActiveSystems.value[0]?.split('|')[1] || 'All'
  }
  return total + ' filters'
})

const actDropdownLabel = computed(() => {
  if (!tlActiveActTypes.value.length) return 'All'
  if (tlActiveActTypes.value.length === 1) return tlActiveActTypes.value[0].replace(/_/g, ' ')
  return tlActiveActTypes.value.length + ' types'
})

const hasTimelineFilters = computed(() =>
  tlActiveSystems.value.length > 0 || tlShowFiltered.value || tlActiveActTypes.value.length > 0 || dateFrom || dateTo
)

const timelineUrl = computed(() => {
  const p = new URLSearchParams()
  if (dateFrom) p.set('from', dateFrom)
  if (dateTo) p.set('to', dateTo)
  if (activeTimelineTab.value === 'conversations') {
    p.append('types[]', 'conversation')
    tlActiveSystems.value.forEach(s => p.append('systems[]', s))
    if (tlShowFiltered.value) p.set('is_filtered', '1')
  } else if (activeTimelineTab.value === 'activity') {
    tlActiveActTypes.value.forEach(t => p.append('types[]', t))
  } else if (activeTimelineTab.value === 'filtered') {
    p.set('is_filtered', '1')
  }
  return `/people/${props.person.id}/timeline?${p}`
})

function setTimelineTab(tab) {
  activeTimelineTab.value = tab
  tlActiveSystems.value = []
  tlShowFiltered.value = false
  tlActiveActTypes.value = []
  convDropdownOpen.value = false
  actDropdownOpen.value = false
  resetTimeline()
}

function tlConvAll() { tlActiveSystems.value = []; tlShowFiltered.value = false; resetTimeline() }
function tlToggleFiltered() { tlShowFiltered.value = !tlShowFiltered.value; resetTimeline() }
function tlToggleSystem(val) {
  const idx = tlActiveSystems.value.indexOf(val)
  if (idx === -1) tlActiveSystems.value.push(val); else tlActiveSystems.value.splice(idx, 1)
  resetTimeline()
}
function tlActAll() { tlActiveActTypes.value = []; resetTimeline() }
function tlToggleActType(t) {
  const idx = tlActiveActTypes.value.indexOf(t)
  if (idx === -1) tlActiveActTypes.value.push(t); else tlActiveActTypes.value.splice(idx, 1)
  resetTimeline()
}
function resetTimelineFilters() { setTimelineTab('all') }

async function resetTimeline() {
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
    console.error('Timeline reset error:', e)
  }
}

function formatType(t) { return t.charAt(0).toUpperCase() + t.slice(1).replace(/_/g, ' ') }

function handleClickOutside(e) {
  if (convDropdownRef.value && !convDropdownRef.value.contains(e.target)) convDropdownOpen.value = false
  if (actDropdownRef.value && !actDropdownRef.value.contains(e.target)) actDropdownOpen.value = false
}

// Refs for graphs
const agWrap = ref(null)
const agTip = ref(null)
const agChart = ref(null)
const agTotal = ref(null)

function loadHourly() {
  const qs = (agFrom && agTo) ? `?from=${agFrom}&to=${agTo}` : ''
  fetch(props.hourlyActivityUrl + qs)
    .then(r => r.json())
    .then(data => {
      const hours = data.hours
      const max = Math.max(...Object.values(hours).concat([1]))
      const chartH = agChart.value?.offsetHeight || 56
      agChart.value?.querySelectorAll('.ag-bar').forEach(bar => {
        const h = parseInt(bar.dataset.hour)
        const val = hours[h] || 0
        const px = val > 0 ? Math.max(Math.round((val / max) * chartH), 4) : 2
        bar.style.height = px + 'px'
        bar.style.opacity = val > 0 ? '1' : '0.25'
        bar._val = val
      })
      if (agTotal.value) {
        agTotal.value.textContent = data.total > 0
          ? data.total + ' messages in period'
          : 'No messages in this period'
      }
    })
}

function loadAvailability() {
  const qs = (agFrom && agTo) ? `?from=${agFrom}&to=${agTo}` : ''
  fetch(props.activityAvailabilityUrl + qs)
    .then(r => r.json())
    .then(data => {
      const days = data.days
      let maxCount = 1
      for (let d = 1; d <= 7; d++) {
        if (!days[d]) continue
        for (let h = 0; h < 24; h++) {
          if (days[d][h] > maxCount) maxCount = days[d][h]
        }
      }
      document.querySelectorAll('.av-cell').forEach(cell => {
        const dow = parseInt(cell.dataset.dow)
        const hour = parseInt(cell.dataset.hour)
        const cnt = (days[dow] && days[dow][hour]) ? days[dow][hour] : 0
        if (cnt > 0) {
          const opacity = (0.3 + 0.7 * (cnt / maxCount)).toFixed(2)
          cell.style.background = `rgba(164,0,87,${opacity})`
          cell.style.borderRadius = '1px'
          cell.title = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][dow-1] + ` ${hour}:00 — ${cnt} ${cnt === 1 ? 'week' : 'weeks'}`
        } else {
          cell.style.background = 'rgba(243,244,246,0.8)'
          cell.title = ''
        }
      })
    })
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)

  // Timeline date range
  if (window.drp && dateRangeInput.value) {
    window.drp.init(dateRangeInput.value, (from, to) => {
      dateFrom = from; dateTo = to; resetTimeline()
    })
  }

  // Graph date range
  if (window.drp && graphDateInput.value) {
    window.drp.init(graphDateInput.value, (from, to) => {
      agFrom = from; agTo = to
      if (agFrom && agTo) { loadHourly(); loadAvailability() }
    }, { defaultDays: 365 })
  }

  // Hourly chart tooltip
  if (agChart.value && agTip.value) {
    agChart.value.querySelectorAll('.ag-bar').forEach(bar => {
      bar.addEventListener('mouseenter', () => {
        const h = parseInt(bar.dataset.hour)
        const val = bar._val !== undefined ? bar._val : 0
        agTip.value.textContent = `${h}:00 — ${val} ${val === 1 ? 'msg' : 'msgs'}`
        const barRect = bar.getBoundingClientRect()
        const wrapRect = agWrap.value.getBoundingClientRect()
        agTip.value.style.left = (barRect.left - wrapRect.left + barRect.width / 2) + 'px'
        agTip.value.classList.remove('hidden')
      })
      bar.addEventListener('mouseleave', () => {
        agTip.value?.classList.add('hidden')
      })
    })
  }
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

// Modal helpers
function openModal(url) {
  convModalSrc.value = url
  showConvModal.value = true
}
function showPersonFilterModal() {
  filterFetchUrl.value = `${props.filterModalUrl}?ids[]=${props.person.id}`
  showFilterModal.value = true
}
function showAssignCompany() {
  popup.value = 'link-company'
}

// CRUD
function markOurOrg() {
  router.post(`/people/${props.person.id}/mark-our-org`, {}, { preserveScroll: true })
}
function unmarkOurOrg() {
  router.post(`/people/${props.person.id}/unmark-our-org`, {}, { preserveScroll: true })
}
function addIdentity() {
  if (!newIdentity.value.value.trim()) return
  router.post(`/people/${props.person.id}/identities`, newIdentity.value, {
    preserveScroll: true,
    onSuccess: () => { newIdentity.value = { type: 'email', value: '' }; popup.value = null },
  })
}
function deleteIdentity(identity) {
  if (!confirm(`Remove identity ${identity.value}?`)) return
  router.delete(`/people/${props.person.id}/identities/${identity.id}`, { preserveScroll: true })
}
function linkCompany() {
  router.post(`/people/${props.person.id}/companies`, linkForm.value, {
    preserveScroll: true,
    onSuccess: () => { linkForm.value = { company_id: props.allCompanies[0]?.id, role: '' }; popup.value = null },
  })
}
function unlinkCompany(c) {
  router.delete(`/people/${props.person.id}/companies/${c.id}`, { preserveScroll: true })
}
function unmerge(mp) {
  if (!confirm(`Unmerge ${mp.full_name}? They will reappear in the people list.`)) return
  router.post(`/people/${mp.id}/unmerge`, {}, { preserveScroll: true })
}

// Helpers
function truncate(str, len) {
  return str.length > len ? str.substring(0, len) + '…' : str
}
function timeAgo(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  const now = new Date()
  const seconds = Math.floor((now - d) / 1000)
  if (seconds < 60) return 'just now'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  if (days < 30) return `${days}d ago`
  const months = Math.floor(days / 30)
  return months < 12 ? `${months}mo ago` : `${Math.floor(months / 12)}y ago`
}
</script>
