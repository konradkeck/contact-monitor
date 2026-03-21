<template>
  <AppLayout>
    <Head :title="`${systemSlug} — Mapping`" />

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <nav aria-label="Breadcrumb" class="page-breadcrumb">
          <Link href="/configuration/data-relations">Data Relations</Link>
          <span class="sep">/</span>
          <span class="cur" aria-current="page">{{ systemSlug }}</span>
        </nav>
        <div class="flex items-center gap-2.5 mt-1">
          <span class="badge badge-gray text-xs">{{ systemType }}</span>
          <h1 class="page-title">{{ systemSlug }}</h1>
        </div>
      </div>
      <button @click="autoResolve" class="btn btn-secondary btn-sm">Auto-Resolve</button>
    </div>

    <!-- Top-level Tabs -->
    <div v-if="hasTabs || hasWhmcsTabs" class="flex gap-0 border-b border-gray-200 mb-6">
      <template v-if="hasWhmcsTabs">
        <Link v-for="(tab, key) in whmcsTabs" :key="key"
              :href="tabUrl(key)"
              :class="['px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition',
                       activeTab === key ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
          {{ tab.label }}
          <span v-if="tab.count > 0"
                :class="['ml-1.5 px-1.5 py-0.5 rounded-full text-xs', activeTab === key ? 'bg-brand-100 text-brand-700' : 'bg-amber-100 text-amber-700']">
            {{ tab.count }}
          </span>
        </Link>
      </template>
      <template v-if="hasTabs">
        <Link v-for="(tab, key) in channelTabs" :key="key"
              :href="tabUrl(key)"
              :class="['px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition',
                       activeTab === key ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
          {{ tab.label }}
          <span v-if="tab.count > 0"
                :class="['ml-1.5 px-1.5 py-0.5 rounded-full text-xs', activeTab === key ? 'bg-brand-100 text-brand-700' : 'bg-amber-100 text-amber-700']">
            {{ tab.count }}
          </span>
        </Link>
      </template>
    </div>

    <!-- ACCOUNT-BASED (WHMCS, MetricsCube) — clients tab -->
    <template v-if="isAccountSystem && activeTab !== 'unregistered'">
      <PeopleToolbar :linked-count="stats.linked" :unlinked-count="stats.unlinked" :active-view="activeView" :search-query="searchQuery" />

      <template v-if="activeView === 'unlinked'">
        <template v-if="unlinked.data.length">
          <div class="bg-white rounded-lg border border-gray-200 mobile-overflow-visible overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="tbl-header">
                <tr>
                  <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Client ID</th>
                  <th class="px-4 py-2 text-left font-medium">Company name</th>
                  <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Email</th>
                  <th v-if="systemType === 'whmcs'" class="px-4 py-2 text-left font-medium col-mobile-hidden">Phone</th>
                  <th v-if="systemType === 'whmcs'" class="px-4 py-2 text-left font-medium col-mobile-hidden">Country</th>
                  <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <template v-for="account in unlinked.data" :key="account.id">
                  <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 font-mono text-xs text-gray-500 truncate max-w-[80px] col-mobile-hidden">{{ account.external_id }}</td>
                    <td class="px-4 py-2.5 text-gray-800 truncate max-w-[180px]">{{ account.meta_json?.company_name || '—' }}</td>
                    <td class="px-4 py-2.5 text-gray-500 text-xs truncate max-w-[160px] col-mobile-hidden">{{ account.meta_json?.email || '—' }}</td>
                    <td v-if="systemType === 'whmcs'" class="px-4 py-2.5 text-gray-500 text-xs truncate col-mobile-hidden">{{ account.meta_json?.phone || '—' }}</td>
                    <td v-if="systemType === 'whmcs'" class="px-4 py-2.5 text-gray-500 text-xs truncate col-mobile-hidden">{{ account.meta_json?.country || '—' }}</td>
                    <td class="px-4 py-2.5 text-right">
                      <div class="row-actions-desktop items-center justify-end gap-1.5">
                        <button @click="openLinkCompany(account)" class="btn btn-sm btn-primary">Link</button>
                        <button v-if="account.filter_url" @click="openFilterModal(account.filter_url)" class="btn btn-sm btn-danger">Filter</button>
                      </div>
                    </td>
                  </tr>
                  <!-- Inline contacts -->
                  <tr v-for="contact in getContactsForAccount(account.external_id)" :key="'c-' + contact.id" class="border-t-0 contact-subrow">
                    <td class="pl-5 pr-2 py-1.5 col-mobile-hidden"><span class="text-gray-300 text-xs select-none">&darr;</span></td>
                    <td class="px-4 py-1.5">
                      <div class="flex flex-col gap-0.5">
                        <span class="font-mono text-gray-600 text-xs truncate">{{ contact.value }}</span>
                        <span v-if="contact.meta_json?.display_name" class="text-gray-400 text-xs truncate">{{ contact.meta_json.display_name }}</span>
                      </div>
                    </td>
                    <td class="col-mobile-hidden"></td>
                    <td v-if="systemType === 'whmcs'" class="col-mobile-hidden"></td>
                    <td v-if="systemType === 'whmcs'" class="col-mobile-hidden"></td>
                    <td class="px-4 py-1.5 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <template v-if="contact.person">
                          <Link :href="`/people/${contact.person.id}`" class="link font-medium text-xs">{{ contact.person.full_name }}</Link>
                          <button @click="unlinkIdentity(contact)" class="row-action-danger">Unlink</button>
                        </template>
                        <template v-else>
                          <span class="text-gray-400 text-xs">&mdash;</span>
                          <button @click="openLinkPerson(contact)" class="btn btn-sm btn-primary shrink-0">Link</button>
                        </template>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
            <Pagination v-if="unlinked.last_page > 1" :paginator="unlinked" />
          </div>
        </template>
        <template v-else>
          <div class="bg-green-50 border border-green-200 rounded-lg px-5 py-4 text-sm text-green-700">All accounts are linked to companies.</div>
        </template>
      </template>

      <template v-else>
        <!-- Linked view -->
        <template v-if="linked.data.length">
          <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
            <table class="w-full text-sm table-fixed min-w-[400px]">
              <thead class="tbl-header">
                <tr>
                  <th class="px-4 py-2 text-left font-medium whitespace-nowrap col-mobile-hidden w-[110px]">External ID</th>
                  <th v-if="systemType === 'whmcs'" class="px-4 py-2 text-left font-medium col-mobile-hidden w-[220px]">Company name (WHMCS)</th>
                  <th class="px-4 py-2 text-left font-medium">Company in Contact Monitor</th>
                  <th class="px-4 py-2 w-20"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <template v-for="account in linked.data" :key="account.id">
                  <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 font-mono text-xs text-gray-500 truncate col-mobile-hidden">{{ account.external_id }}</td>
                    <td v-if="systemType === 'whmcs'" class="px-4 py-2.5 text-gray-600 text-xs truncate col-mobile-hidden">{{ account.meta_json?.company_name || '—' }}</td>
                    <td class="px-4 py-2.5">
                      <Link :href="`/companies/${account.company?.id}`" class="link font-medium">{{ account.company?.name }}</Link>
                    </td>
                    <td class="px-4 py-2.5 text-right">
                      <button @click="unlinkAccount(account)" class="row-action-danger">Unlink</button>
                    </td>
                  </tr>
                  <!-- Inline contacts for linked -->
                  <tr v-for="contact in getContactsForAccount(account.external_id)" :key="'cl-' + contact.id" class="border-t-0 contact-subrow">
                    <td class="pl-5 pr-2 py-1.5 col-mobile-hidden"><span class="text-gray-300 text-xs select-none">&darr;</span></td>
                    <td v-if="systemType === 'whmcs'" class="px-4 py-1.5 col-mobile-hidden">
                      <div class="flex flex-col gap-0.5">
                        <span class="font-mono text-gray-600 text-xs truncate">{{ contact.value }}</span>
                        <span v-if="contact.meta_json?.display_name" class="text-gray-400 text-xs truncate">{{ contact.meta_json.display_name }}</span>
                      </div>
                    </td>
                    <td class="px-4 py-1.5">
                      <template v-if="contact.person">
                        <Link :href="`/people/${contact.person.id}`" class="link font-medium text-xs truncate">{{ contact.person.full_name }}</Link>
                      </template>
                      <template v-else><span class="text-gray-400 text-xs">&mdash;</span></template>
                    </td>
                    <td class="px-4 py-1.5 text-right">
                      <template v-if="contact.person">
                        <button @click="unlinkIdentity(contact)" class="row-action-danger">Unlink</button>
                      </template>
                      <template v-else>
                        <button @click="openLinkPerson(contact)" class="btn btn-sm btn-primary">Link</button>
                      </template>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
            <Pagination v-if="linked.last_page > 1" :paginator="linked" />
          </div>
        </template>
        <template v-else>
          <div class="bg-gray-50 border border-gray-200 rounded-lg px-5 py-4 text-sm text-gray-500">No linked accounts.</div>
        </template>
      </template>
    </template>

    <!-- UNREGISTERED USERS (WHMCS) -->
    <template v-if="isAccountSystem && activeTab === 'unregistered'">
      <div class="mb-4 text-sm text-gray-500">
        Email addresses that appeared in tickets or other activity but are <strong>not registered WHMCS clients</strong>.
        Auto-Resolve will try to match them to existing people by email.
      </div>
      <template v-if="unregisteredUsers.length">
        <div class="bg-white rounded-lg border border-gray-200 mobile-overflow-visible">
          <table class="w-full text-sm">
            <thead class="tbl-header">
              <tr>
                <th class="px-4 py-2 text-left font-medium">Email address</th>
                <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Display name</th>
                <th class="px-4 py-2 text-left font-medium">Person</th>
                <th class="px-4 py-2 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="identity in unregisteredUsers" :key="identity.id" class="hover:bg-gray-50">
                <td class="px-4 py-2.5 font-mono text-xs text-gray-700 truncate max-w-[180px]">{{ identity.value }}</td>
                <td class="px-4 py-2.5 col-mobile-hidden">
                  <div class="flex items-center gap-2">
                    <img :src="`https://www.gravatar.com/avatar/${identity.gravatar_hash}?d=identicon&s=40`" class="w-6 h-6 rounded-full object-cover shrink-0">
                    <span class="text-gray-600 text-xs">{{ identity.meta_json?.display_name || '—' }}</span>
                  </div>
                </td>
                <td class="px-4 py-2.5">
                  <template v-if="identity.person">
                    <Link :href="`/people/${identity.person.id}`" class="link font-medium text-xs">{{ identity.person.full_name }}</Link>
                    <button @click="unlinkIdentity(identity)" class="row-action-danger ml-2">Unlink</button>
                  </template>
                  <template v-else><span class="text-gray-400 text-xs">&mdash;</span></template>
                </td>
                <td class="px-4 py-2.5 text-right">
                  <div class="row-actions-desktop items-center justify-end gap-1.5">
                    <button v-if="!identity.person" @click="openLinkPerson(identity)" class="btn btn-sm btn-primary">Link</button>
                    <button v-if="!identity.person && identity.filter_url" @click="openFilterModal(identity.filter_url)" class="btn btn-sm btn-danger">Filter</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
      <template v-else>
        <div class="bg-green-50 border border-green-200 rounded-lg px-5 py-4 text-sm text-green-700">No unregistered users found.</div>
      </template>
    </template>

    <!-- IDENTITY-BASED (IMAP, Slack, Discord) — People tab -->
    <template v-if="!isAccountSystem && (activeTab === 'people' || !hasTabs)">
      <PeopleToolbar :linked-count="stats.linked" :unlinked-count="stats.unlinked" :active-view="activeView" :search-query="searchQuery" />

      <template v-if="activeView === 'unlinked'">
        <template v-if="unlinked.data.length">
          <div class="bg-white rounded-lg border border-gray-200 mobile-overflow-visible">
            <table class="w-full text-sm">
              <thead class="tbl-header">
                <tr>
                  <th class="px-4 py-2 text-left font-medium">
                    <template v-if="systemType === 'slack'"><span class="hidden md:inline">Slack user ID</span><span class="md:hidden">User</span></template>
                    <template v-else-if="systemType === 'discord'"><span class="hidden md:inline">Discord user ID</span><span class="md:hidden">User</span></template>
                    <template v-else>Identity value</template>
                  </th>
                  <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Display name</th>
                  <th v-if="systemType === 'slack'" class="px-4 py-2 text-left font-medium col-mobile-hidden">Email</th>
                  <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="identity in unlinked.data" :key="identity.id" class="hover:bg-gray-50">
                  <td class="px-4 py-2.5">
                    <IdentityCell :identity="identity" :system-type="systemType" />
                  </td>
                  <td class="px-4 py-2.5 col-mobile-hidden">
                    <AvatarName :identity="identity" />
                  </td>
                  <td v-if="systemType === 'slack'" class="px-4 py-2.5 text-gray-500 text-xs col-mobile-hidden">{{ identity.meta_json?.email_hint || '—' }}</td>
                  <td class="px-4 py-2.5 text-right">
                    <div class="row-actions-desktop items-center justify-end gap-1.5">
                      <button @click="openLinkPerson(identity)" class="btn btn-sm btn-primary">Link</button>
                      <button v-if="identity.has_filter_data" @click="openFilterModal(identity.filter_url)" class="btn btn-sm btn-danger">Filter</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
            <Pagination v-if="unlinked.last_page > 1" :paginator="unlinked" />
          </div>
        </template>
        <template v-else>
          <div class="bg-green-50 border border-green-200 rounded-lg px-5 py-4 text-sm text-green-700">All identities are linked to people.</div>
        </template>
      </template>

      <template v-else>
        <!-- Linked identities -->
        <template v-if="linked.data.length">
          <div class="bg-white rounded-lg border border-gray-200 mobile-overflow-visible">
            <table class="w-full text-sm">
              <thead class="tbl-header">
                <tr>
                  <th class="px-4 py-2 text-left font-medium">
                    <template v-if="systemType === 'slack'"><span class="hidden md:inline">Slack user ID</span><span class="md:hidden">User</span></template>
                    <template v-else-if="systemType === 'discord'"><span class="hidden md:inline">Discord user ID</span><span class="md:hidden">User</span></template>
                    <template v-else>Identity value</template>
                  </th>
                  <th class="px-4 py-2 text-left font-medium col-mobile-hidden">Display name</th>
                  <th v-if="systemType === 'slack'" class="px-4 py-2 text-left font-medium col-mobile-hidden">Email</th>
                  <th class="px-4 py-2 text-left font-medium">Person in Contact Monitor</th>
                  <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="identity in linked.data" :key="identity.id" class="hover:bg-gray-50">
                  <td class="px-4 py-2.5">
                    <IdentityCell :identity="identity" :system-type="systemType" />
                  </td>
                  <td class="px-4 py-2.5 col-mobile-hidden">
                    <AvatarName :identity="identity" />
                  </td>
                  <td v-if="systemType === 'slack'" class="px-4 py-2.5 text-gray-500 text-xs col-mobile-hidden">{{ identity.meta_json?.email_hint || '—' }}</td>
                  <td class="px-4 py-2.5">
                    <Link :href="`/people/${identity.person?.id}`" class="link font-medium">{{ identity.person?.first_name }} {{ identity.person?.last_name }}</Link>
                  </td>
                  <td class="px-4 py-2.5 text-right">
                    <button @click="unlinkIdentity(identity)" class="btn btn-sm btn-danger">Unlink</button>
                  </td>
                </tr>
              </tbody>
            </table>
            <Pagination v-if="linked.last_page > 1" :paginator="linked" />
          </div>
        </template>
        <template v-else>
          <div class="bg-gray-50 border border-gray-200 rounded-lg px-5 py-4 text-sm text-gray-500">No linked identities.</div>
        </template>
      </template>
    </template>

    <!-- CHANNELS TAB (Discord/Slack) -->
    <template v-if="activeTab === 'channels' && conversationStats">
      <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex-1 min-w-0 flex gap-2 items-center">
          <input type="text" v-model="channelSearch" placeholder="Search..."
                 class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-full max-w-[280px] focus:outline-none focus:border-brand-400">
        </div>
        <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm shrink-0">
          <button @click="channelView = 'unlinked'"
                  :class="['px-4 py-1.5 font-medium transition border-r border-gray-200',
                           channelView === 'unlinked' ? 'bg-amber-50 text-amber-700' : 'bg-white text-gray-500 hover:bg-gray-50']">
            Unlinked <span class="font-bold">{{ conversationStats.unlinked }}</span>
          </button>
          <button @click="channelView = 'linked'"
                  :class="['px-4 py-1.5 font-medium transition',
                           channelView === 'linked' ? 'bg-green-50 text-green-700' : 'bg-white text-gray-500 hover:bg-gray-50']">
            Linked <span class="font-bold">{{ conversationStats.linked }}</span>
          </button>
        </div>
      </div>
      <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm min-w-[480px]">
          <thead class="tbl-header">
            <tr>
              <th class="px-4 py-2 text-left font-medium">Channel</th>
              <th class="px-4 py-2 text-left font-medium">Company in Contact Monitor</th>
              <th class="px-4 py-2 text-left font-medium w-72">Link / Change</th>
              <th class="px-4 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="conv in filteredConversations" :key="conv.id" class="hover:bg-gray-50">
              <td class="px-4 py-2.5 font-medium text-gray-800">{{ conv.subject || conv.external_thread_id }}</td>
              <td class="px-4 py-2.5">
                <Link v-if="conv.company" :href="`/companies/${conv.company.id}`" class="link font-medium">{{ conv.company.name }}</Link>
                <span v-else class="text-gray-400 text-xs">&mdash;</span>
              </td>
              <td class="px-4 py-2.5">
                <CompanyAutocomplete :action="`/configuration/conversations/${conv.id}/link`"
                                     :placeholder="conv.company ? 'Change...' : 'Search company...'"
                                     :search-url="companySearchUrl" />
              </td>
              <td class="px-4 py-2.5 text-right">
                <button v-if="conv.company_id" @click="unlinkConversation(conv)" class="row-action-danger">Unlink</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Link Company Modal -->
    <div v-if="linkCompanyTarget" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/30 backdrop-blur-sm" @click.self="linkCompanyTarget = null">
      <div class="bg-white rounded-xl shadow-2xl p-4 w-80 max-w-[90vw]" @click.stop>
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm font-semibold text-gray-700">Link to company</span>
          <button @click="linkCompanyTarget = null" class="text-gray-400 hover:text-gray-600 -mr-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
          </button>
        </div>
        <CompanyAutocomplete :action="`/configuration/accounts/${linkCompanyTarget.id}/link`"
                             :search-url="companySearchUrl" />
      </div>
    </div>

    <!-- Identity Filter Modal -->
    <IdentityFilterModal :show="showIdentityFilter" :fetchUrl="identityFilterUrl" @close="showIdentityFilter = false" />

    <!-- Link Person Modal -->
    <div v-if="linkPersonTarget" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/30 backdrop-blur-sm" @click.self="linkPersonTarget = null" @keydown.escape="linkPersonTarget = null">
      <div class="bg-white rounded-xl shadow-2xl p-4 w-80 max-w-[90vw]" @click.stop>
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm font-semibold text-gray-700">Link to person</span>
          <button @click="linkPersonTarget = null" class="text-gray-400 hover:text-gray-600 -mr-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
          </button>
        </div>
        <LinkPersonPanel :link-url="`/configuration/identities/${linkPersonTarget.id}/link-create`"
                         :person-search-url="personSearchUrl"
                         @done="linkPersonTarget = null; router.reload()" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import IdentityFilterModal from '../../components/IdentityFilterModal.vue'

const csrf = document.querySelector('meta[name="csrf-token"]')?.content || ''

const props = defineProps({
  systemType: String,
  systemSlug: String,
  isAccountSystem: Boolean,
  stats: Object,
  unlinked: Object,
  linked: Object,
  conversations: { type: Array, default: () => [] },
  conversationStats: { type: Object, default: null },
  identitiesByExtId: { type: Object, default: () => ({}) },
  unregisteredUsers: { type: Array, default: () => [] },
  unregisteredStats: { type: Object, default: null },
  hasTabs: Boolean,
  hasWhmcsTabs: Boolean,
  activeTab: String,
  activeView: String,
  searchQuery: { type: String, default: '' },
  companySearchUrl: String,
  personSearchUrl: String,
})

const linkCompanyTarget = ref(null)
const linkPersonTarget = ref(null)
const showIdentityFilter = ref(false)
const identityFilterUrl = ref('')
const channelSearch = ref('')
const channelView = ref('unlinked')

const whmcsTabs = computed(() => ({
  clients: { label: 'Clients & Contacts', count: props.stats.unlinked },
  unregistered: { label: 'Unregistered Users', count: props.unregisteredStats?.unlinked || 0 },
}))

const channelTabs = computed(() => ({
  people: { label: 'People', count: props.stats.unlinked },
  channels: { label: 'Channels', count: props.conversationStats?.unlinked || 0 },
}))

const filteredConversations = computed(() => {
  const q = channelSearch.value.toLowerCase()
  return props.conversations.filter(c => {
    const isLinked = !!c.company_id
    const matchView = channelView.value === 'unlinked' ? !isLinked : isLinked
    const matchSearch = !q || (c.subject || c.external_thread_id || '').toLowerCase().includes(q)
    return matchView && matchSearch
  })
})

function tabUrl(key) {
  const base = `/configuration/mapping/${props.systemType}/${props.systemSlug}`
  return `${base}?tab=${key}&view=unlinked&q=`
}

function getContactsForAccount(externalId) {
  return props.identitiesByExtId[String(externalId)] || []
}

function autoResolve() {
  router.post('/configuration/resolve-auto')
}

function openLinkCompany(account) {
  linkCompanyTarget.value = account
}

function openLinkPerson(identity) {
  linkPersonTarget.value = identity
}

function openFilterModal(url) {
  identityFilterUrl.value = url
  showIdentityFilter.value = true
}

function unlinkAccount(account) {
  router.delete(`/configuration/accounts/${account.id}/unlink`)
}

function unlinkIdentity(identity) {
  router.delete(`/configuration/identities/${identity.id}/unlink`)
}

function unlinkConversation(conv) {
  router.delete(`/configuration/conversations/${conv.id}/unlink`)
}

// Sub-components
const PeopleToolbar = {
  props: ['linkedCount', 'unlinkedCount', 'activeView', 'searchQuery'],
  setup(props) {
    const q = ref(props.searchQuery || '')
    function search() {
      const params = new URLSearchParams(window.location.search)
      params.set('q', q.value)
      params.delete('page')
      router.get(window.location.pathname + '?' + params.toString())
    }
    function clearSearch() {
      const params = new URLSearchParams(window.location.search)
      params.set('q', '')
      params.delete('page')
      router.get(window.location.pathname + '?' + params.toString())
    }
    function setView(view) {
      const params = new URLSearchParams(window.location.search)
      params.set('view', view)
      params.delete('page')
      router.get(window.location.pathname + '?' + params.toString())
    }
    return { q, search, clearSearch, setView }
  },
  template: `<div class="flex flex-col gap-2 md:flex-row md:items-center md:gap-3 mb-4">
    <form @submit.prevent="search" class="flex gap-2 items-center min-w-0">
      <input type="text" v-model="q" placeholder="Search..." class="input flex-1 min-w-0 md:w-52 md:flex-none py-1.5 text-sm">
      <button type="submit" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded-lg transition shrink-0">Search</button>
      <button v-if="searchQuery" type="button" @click="clearSearch" class="text-xs text-gray-400 hover:text-gray-600 shrink-0">clear</button>
    </form>
    <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm shrink-0 self-start md:self-auto">
      <button @click="setView('unlinked')"
              :class="['px-3 py-1.5 font-medium transition border-r border-gray-200',
                       activeView === 'unlinked' ? 'bg-amber-50 text-amber-700' : 'bg-white text-gray-500 hover:bg-gray-50']">
        Unlinked <span class="ml-1 font-bold">{{ unlinkedCount }}</span>
      </button>
      <button @click="setView('linked')"
              :class="['px-3 py-1.5 font-medium transition',
                       activeView === 'linked' ? 'bg-green-50 text-green-700' : 'bg-white text-gray-500 hover:bg-gray-50']">
        Linked <span class="ml-1 font-bold">{{ linkedCount }}</span>
      </button>
    </div>
  </div>`
}

const Pagination = {
  props: ['paginator'],
  template: `<div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-sm">
    <span class="text-gray-500">Page {{ paginator.current_page }} of {{ paginator.last_page }}</span>
    <div class="flex gap-1">
      <a v-for="link in paginator.links" :key="link.label"
         :href="link.url"
         :class="['px-2.5 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : link.url ? 'hover:bg-gray-100 text-gray-600' : 'text-gray-300']"
         v-html="link.label"></a>
    </div>
  </div>`
}

const IdentityCell = {
  props: ['identity', 'systemType'],
  template: `<span>
    <template v-if="systemType === 'slack' || systemType === 'discord'">
      <span class="hidden md:inline font-mono text-xs text-gray-700">{{ identity.value }}</span>
      <div class="flex items-center gap-2 md:hidden">
        <span class="text-gray-700 text-xs truncate max-w-[140px]">{{ identity.meta_json?.display_name || identity.value }}</span>
      </div>
    </template>
    <template v-else>
      <span class="font-mono text-xs text-gray-700 truncate max-w-[180px]">{{ identity.value }}</span>
    </template>
  </span>`
}

const AvatarName = {
  props: ['identity'],
  template: `<div class="flex items-center gap-2">
    <span class="relative w-6 h-6 shrink-0 inline-flex rounded-full">
      <span class="w-6 h-6 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-[10px] font-bold absolute inset-0">
        {{ (identity.meta_json?.display_name || identity.value || '?').charAt(0).toUpperCase() }}
      </span>
      <img v-if="identity.sys_avatar" :src="identity.sys_avatar" class="w-6 h-6 rounded-full object-cover absolute inset-0" @error="$event.target.style.display='none'">
      <img v-else-if="identity.gravatar_hash" :src="'https://www.gravatar.com/avatar/' + identity.gravatar_hash + '?d=identicon&s=40'" class="w-6 h-6 rounded-full object-cover absolute inset-0" @error="$event.target.style.display='none'">
    </span>
    <span class="text-gray-600 text-xs">{{ identity.meta_json?.display_name || '—' }}</span>
  </div>`
}

const CompanyAutocomplete = {
  props: ['action', 'placeholder', 'searchUrl'],
  setup(props) {
    const q = ref('')
    const results = ref([])
    const selectedId = ref('')
    let timer = null

    function onInput() {
      selectedId.value = ''
      clearTimeout(timer)
      if (q.value.trim().length < 2) { results.value = []; return }
      timer = setTimeout(async () => {
        try {
          const res = await fetch(`${props.searchUrl}?q=${encodeURIComponent(q.value)}`)
          results.value = await res.json()
        } catch { results.value = [] }
      }, 250)
    }

    function pick(item) {
      q.value = item.name
      selectedId.value = item.id
      results.value = []
    }

    function submit() {
      if (!selectedId.value) return
      router.post(props.action, { company_id: selectedId.value })
    }

    return { q, results, selectedId, onInput, pick, submit }
  },
  template: `<form @submit.prevent="submit" class="flex gap-2 items-center">
    <div class="relative flex-1">
      <input type="text" v-model="q" @input="onInput" :placeholder="placeholder || 'Search company...'" autocomplete="off"
             class="ac-input text-xs border border-gray-300 rounded px-2 py-1 w-full focus:outline-none focus:border-brand-400">
      <ul v-if="results.length" class="absolute z-10 w-full bg-white border border-gray-200 rounded shadow-lg mt-0.5 max-h-48 overflow-y-auto text-xs">
        <li v-for="item in results" :key="item.id" @mousedown.prevent="pick(item)"
            class="px-3 py-1.5 cursor-pointer hover:bg-brand-50 hover:text-brand-700">{{ item.name }}</li>
      </ul>
    </div>
    <button type="submit" :disabled="!selectedId"
            class="px-2 py-1 bg-brand-600 text-white text-xs rounded hover:bg-brand-700 transition whitespace-nowrap disabled:opacity-40 disabled:cursor-not-allowed">Link</button>
  </form>`
}

const LinkPersonPanel = {
  props: ['linkUrl', 'personSearchUrl'],
  emits: ['done'],
  setup(props, { emit }) {
    const mode = ref('existing')
    const q = ref('')
    const results = ref([])
    const picked = ref(null)
    const fn = ref('')
    const ln = ref('')
    const isOurOrg = ref(false)
    const err = ref('')
    const busy = ref(false)
    let timer = null

    async function doSearch(v) {
      picked.value = null
      clearTimeout(timer)
      if (v.length < 2) { results.value = []; return }
      timer = setTimeout(async () => {
        try {
          const res = await fetch(`${props.personSearchUrl}?q=${encodeURIComponent(v)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          })
          results.value = await res.json()
        } catch { results.value = [] }
      }, 220)
    }

    function pick(p) {
      picked.value = p
      q.value = p.name
      results.value = []
    }

    async function submit() {
      err.value = ''
      let body = {}
      if (mode.value === 'new') {
        if (!fn.value.trim()) { err.value = 'Enter first name.'; return }
        body = { mode: 'new', first_name: fn.value.trim(), last_name: ln.value.trim(), is_our_org: isOurOrg.value }
      } else {
        if (!picked.value) { err.value = 'Select a person.'; return }
        body = { mode: 'existing', person_id: picked.value.id }
      }
      busy.value = true
      try {
        const res = await fetch(props.linkUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify(body),
        })
        const data = await res.json()
        if (data.ok) { emit('done') }
        else { err.value = data.error || 'Error.'; busy.value = false }
      } catch { err.value = 'Network error.'; busy.value = false }
    }

    return { mode, q, results, picked, fn, ln, isOurOrg, err, busy, doSearch, pick, submit }
  },
  template: `<div>
    <div class="grid grid-cols-2 gap-2 mb-4">
      <button @click="mode = 'existing'; err = ''"
              :class="['flex flex-col items-center gap-1.5 p-3 rounded-lg border-2 transition cursor-pointer',
                       mode === 'existing' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-200 text-gray-500 hover:border-gray-300']">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
        </svg>
        <span class="text-xs font-medium">Assign Existing</span>
      </button>
      <button @click="mode = 'new'; err = ''"
              :class="['flex flex-col items-center gap-1.5 p-3 rounded-lg border-2 transition cursor-pointer',
                       mode === 'new' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-200 text-gray-500 hover:border-gray-300']">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/>
          <line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
        </svg>
        <span class="text-xs font-medium">Create New</span>
      </button>
    </div>

    <div v-if="mode === 'existing'">
      <label class="label mb-1.5">Search person</label>
      <div class="relative">
        <input type="text" v-model="q" @input="doSearch(q)" placeholder="Type name..." autocomplete="off" class="input text-sm">
        <ul v-if="results.length" class="absolute z-10 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-0.5 max-h-48 overflow-y-auto text-sm">
          <li v-for="p in results" :key="p.id">
            <button type="button" @click="pick(p)" class="w-full text-left px-3 py-2 hover:bg-gray-50 transition">{{ p.name }}</button>
          </li>
        </ul>
      </div>
      <p v-if="picked" class="text-xs text-brand-700 mt-1.5">Selected: {{ picked.name }}</p>
    </div>

    <div v-if="mode === 'new'">
      <div class="flex gap-2 mb-3">
        <div class="flex-1"><label class="label mb-1">First name</label><input type="text" v-model="fn" placeholder="Jane" class="input text-sm"></div>
        <div class="flex-1"><label class="label mb-1">Last name</label><input type="text" v-model="ln" placeholder="Doe" class="input text-sm"></div>
      </div>
      <label class="flex items-center gap-2 cursor-pointer select-none">
        <input type="checkbox" v-model="isOurOrg" class="rounded border-gray-300 text-brand-600 focus:ring-brand-400">
        <span class="text-xs text-gray-600">Our Organization member</span>
      </label>
    </div>

    <p v-if="err" class="text-xs text-red-500 mt-2">{{ err }}</p>

    <div class="flex items-center gap-2 mt-4">
      <button @click="submit()" :disabled="busy" class="btn btn-primary btn-sm">{{ mode === 'new' ? 'Create & Link' : 'Link' }}</button>
      <span v-if="busy" class="text-xs text-gray-400">Saving...</span>
    </div>
  </div>`
}
</script>
