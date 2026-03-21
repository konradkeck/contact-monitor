<template>
  <AppLayout>
    <Head title="Our Organization — Data Relations" />

    <div class="page-header">
      <div>
        <h1 class="page-title">Our Organization</h1>
        <p class="text-xs text-gray-400 mt-0.5">Define which people and domains belong to your own team. This separates internal activity from customer interactions so timelines and statistics only reflect external communications.</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-bar flex gap-0 border-b border-gray-200 mb-6">
      <Link v-for="(cfg, tab) in tabConfig" :key="tab"
            :href="`/configuration/our-organization?tab=${tab}`"
            :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition whitespace-nowrap shrink-0',
                     activeTab === tab ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
        {{ cfg.label }}
        <span v-if="cfg.count > 0"
              :class="['ml-1.5 px-1.5 py-0.5 rounded-full text-xs',
                       activeTab === tab ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-500']">
          {{ cfg.count }}
        </span>
      </Link>
    </div>

    <!-- MEMBERS -->
    <template v-if="activeTab === 'members'">
      <template v-if="teamPeople.length">
        <div class="w-full md:w-4/5 bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
          <div v-for="person in teamPeople" :key="person.id" class="flex items-center gap-3 px-4 py-3">
            <img :src="`https://www.gravatar.com/avatar/${person.gravatar_hash}?d=identicon&s=80`"
                 class="w-9 h-9 rounded-full object-cover border border-gray-100 shrink-0">
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-sm text-gray-800">{{ person.full_name }}</p>
              <div class="flex flex-wrap gap-1.5 mt-0.5">
                <span v-for="id in person.team_identities" :key="id.type + id.value"
                      class="text-xs text-gray-500 font-mono bg-gray-100 px-1.5 py-0.5 rounded">
                  {{ id.type }}: {{ id.value }}
                </span>
              </div>
            </div>
            <div class="relative shrink-0">
              <button type="button" @click="toggleMenu(person.id)"
                      class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 text-lg leading-none transition">...</button>
              <div v-if="openMenu === person.id"
                   class="absolute right-0 top-full mt-1 w-36 bg-white border border-gray-200 rounded-lg shadow-lg z-10 py-1 text-sm">
                <button @click="removeMember(person)" class="w-full text-left px-3 py-1.5 text-red-500 hover:bg-red-50 hover:text-red-700">x Remove</button>
              </div>
            </div>
          </div>
        </div>
      </template>
      <template v-else>
        <div class="bg-white rounded-xl border border-gray-200 px-6 py-10 text-center">
          <p class="text-gray-400 text-sm italic">No members in Our Organization yet.</p>
          <p class="text-gray-300 text-xs mt-1">Mark people from the People list or Person Details page.</p>
        </div>
      </template>
    </template>

    <!-- IDENTITIES -->
    <template v-if="activeTab === 'identities'">
      <template v-if="unlinkedTeamIdentities.length">
        <div class="w-full md:w-4/5 bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
          <div v-for="identity in unlinkedTeamIdentities" :key="identity.id" class="flex items-center gap-3 px-4 py-2.5 text-sm">
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded shrink-0">{{ identity.type }}</span>
            <span class="font-mono text-gray-700 flex-1 truncate">{{ identity.value }}</span>
            <span class="text-xs text-gray-400 shrink-0 font-mono col-mobile-hidden">{{ identity.system_slug }}</span>
            <div class="relative shrink-0">
              <button type="button" @click="toggleMenu('id-' + identity.id)"
                      class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 text-lg leading-none transition">...</button>
              <div v-if="openMenu === 'id-' + identity.id"
                   class="absolute right-0 top-full mt-1 w-36 bg-white border border-gray-200 rounded-lg shadow-lg z-10 py-1 text-sm">
                <button @click="unmarkIdentity(identity)" class="w-full text-left px-3 py-1.5 text-red-500 hover:bg-red-50 hover:text-red-700">x Unmark</button>
              </div>
            </div>
          </div>
        </div>
      </template>
      <template v-else>
        <div class="bg-white rounded-xl border border-gray-200 px-6 py-10 text-center">
          <p class="text-gray-400 text-sm italic">No unlinked team identities.</p>
        </div>
      </template>
    </template>

    <!-- DOMAINS -->
    <template v-if="activeTab === 'domains'">
      <div class="max-w-xl">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Team Email Domains</h2>
            <p class="text-xs text-gray-400 mt-0.5">Identities with these email domains are auto-marked as team members on Auto-Resolve.</p>
          </div>

          <template v-if="teamDomains.length">
            <ul class="divide-y divide-gray-50">
              <li v-for="domain in teamDomains" :key="domain" class="flex items-center justify-between px-5 py-2.5">
                <span class="font-mono text-sm text-gray-700">{{ domain }}</span>
                <button @click="removeDomain(domain)" class="text-xs text-red-400 hover:text-red-600 font-bold">x remove</button>
              </li>
            </ul>
          </template>
          <template v-else>
            <p class="px-5 py-4 text-sm text-gray-400 italic">No team domains configured.</p>
          </template>

          <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
            <form @submit.prevent="saveDomains">
              <textarea v-model="domainsText" rows="3" placeholder="modulesgarden.com&#10;mg.com"
                        class="w-full text-sm font-mono border border-gray-200 rounded-lg px-3 py-2 placeholder-gray-300 text-gray-700 resize-none focus:outline-none focus:ring-2 focus:ring-brand-300"></textarea>
              <button type="submit" class="w-full py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg transition mt-2">
                Save & Auto-mark
              </button>
            </form>
          </div>
        </div>
      </div>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({
  teamDomains: { type: Array, default: () => [] },
  teamPeople: { type: Array, default: () => [] },
  unlinkedTeamIdentities: { type: Array, default: () => [] },
  activeTab: { type: String, default: 'members' },
})

const openMenu = ref(null)
const domainsText = ref(props.teamDomains.join('\n'))

const tabConfig = computed(() => ({
  members: { label: 'Members', count: props.teamPeople.length },
  identities: { label: 'Team Identities', count: props.unlinkedTeamIdentities.length },
  domains: { label: 'Email Domains', count: props.teamDomains.length },
}))

function toggleMenu(id) {
  openMenu.value = openMenu.value === id ? null : id
}

function removeMember(person) {
  if (confirm(`Remove ${person.full_name} from Our Organization?`)) {
    router.delete(`/configuration/our-organization/members/${person.id}`)
  }
}

function unmarkIdentity(identity) {
  router.post(`/configuration/identities/${identity.id}/toggle-bot`, {}, {
    preserveScroll: true,
  })
}

function removeDomain(domain) {
  router.post('/configuration/our-organization/remove-domain', { domain })
}

function saveDomains() {
  router.post('/configuration/our-organization/domains', { domains: domainsText.value })
}
</script>
