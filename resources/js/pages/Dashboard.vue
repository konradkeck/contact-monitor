<template>
  <AppLayout>
    <Head title="Dashboard" />

    <div class="flex flex-col md:flex-row gap-6 md:items-start">

      <!-- Main content -->
      <div class="flex-1 min-w-0">

        <!-- Page header + date range -->
        <div class="page-header">
          <h1 class="page-title">Dashboard</h1>
          <div class="drp-wrap" id="dash-date-range-wrap">
            <input id="dash-date-range" type="text" placeholder="Date range..."
                   class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-600 bg-white
                          focus:outline-none cursor-pointer w-44" readonly>
          </div>
        </div>

        <!-- Stats cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
          <Link href="/conversations" class="card p-5 hover:shadow-sm transition">
            <p class="text-3xl font-bold text-gray-900">{{ formatNumber(conversationsCount) }}</p>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mt-1">Conversations</p>
          </Link>
          <Link href="/companies" class="card p-5 hover:shadow-sm transition">
            <p class="text-3xl font-bold text-gray-900">{{ formatNumber(newCompaniesCount) }}</p>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mt-1">New Companies</p>
          </Link>
          <Link href="/people" class="card p-5 hover:shadow-sm transition">
            <p class="text-3xl font-bold text-gray-900">{{ formatNumber(newPeopleCount) }}</p>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mt-1">New People</p>
          </Link>
        </div>

        <!-- Two summary tables -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

          <!-- Most Active People -->
          <div class="card overflow-hidden">
            <div class="section-header">
              <span class="section-header-title">Most Active People</span>
              <span class="text-xs text-gray-400">by conversations</span>
            </div>
            <table class="w-full text-sm">
              <thead class="tbl-header">
                <tr>
                  <th scope="col" class="px-4 py-2 text-left">Person</th>
                  <th scope="col" class="px-4 py-2 text-right w-24">Conversations</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="person in activePeople" :key="person.id" class="tbl-row">
                  <td class="px-4 py-2.5">
                    <div class="flex items-center gap-2">
                      <PersonAvatar :person="person" size="6" />
                      <Link :href="`/people/${person.id}`" class="link truncate">{{ person.full_name }}</Link>
                    </div>
                  </td>
                  <td class="px-4 py-2.5 text-right font-semibold text-gray-700">{{ person.conv_count }}</td>
                </tr>
                <tr v-if="activePeople.length === 0">
                  <td colspan="2" class="px-4 py-6 text-center text-sm text-gray-400">No activity in this period.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Most Active Team Members -->
          <div class="card overflow-hidden">
            <div class="section-header">
              <span class="section-header-title">Most Active Team Members</span>
              <span class="text-xs text-gray-400">by conversations</span>
            </div>
            <table class="w-full text-sm">
              <thead class="tbl-header">
                <tr>
                  <th scope="col" class="px-4 py-2 text-left">Member</th>
                  <th scope="col" class="px-4 py-2 text-right w-24">Conversations</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="person in activeTeam" :key="person.id" class="tbl-row">
                  <td class="px-4 py-2.5">
                    <div class="flex items-center gap-2">
                      <PersonAvatar :person="person" size="6" />
                      <Link :href="`/people/${person.id}`" class="link truncate">{{ person.full_name }}</Link>
                    </div>
                  </td>
                  <td class="px-4 py-2.5 text-right font-semibold text-gray-700">{{ person.conv_count }}</td>
                </tr>
                <tr v-if="activeTeam.length === 0">
                  <td colspan="2" class="px-4 py-6 text-center text-sm text-gray-400">No activity in this period.</td>
                </tr>
              </tbody>
            </table>
          </div>

        </div>
      </div>

      <!-- Right sidebar: Recent Notes -->
      <div class="w-full md:w-72 shrink-0">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Recent Notes</h3>

        <p v-if="recentNotes.length === 0" class="text-sm text-gray-400 italic">No notes yet.</p>

        <div v-else class="flex flex-col gap-2.5">
          <div v-for="note in recentNotes" :key="note.id"
               class="bg-amber-50 border border-amber-200 rounded-lg p-3 shadow-sm">
            <div class="flex items-center justify-between gap-1 mb-1.5">
              <Link v-if="note.entity_url && note.entity_name"
                 :href="note.entity_url"
                 class="text-xs font-semibold link truncate"
                 :title="note.entity_name">
                {{ note.entity_name }}
              </Link>
              <span v-else class="text-xs text-gray-400 italic">&mdash;</span>
              <span class="text-[10px] text-gray-400 shrink-0">{{ note.created_ago }}</span>
            </div>
            <p class="text-xs text-gray-700 leading-relaxed line-clamp-4">{{ note.content }}</p>
            <p v-if="note.user_name" class="text-[10px] text-gray-400 mt-1.5">by {{ note.user_name }}</p>
          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../layouts/AppLayout.vue'
import PersonAvatar from '../components/PersonAvatar.vue'

const props = defineProps({
  from: String,
  to: String,
  conversationsCount: Number,
  newCompaniesCount: Number,
  newPeopleCount: Number,
  activePeople: { type: Array, default: () => [] },
  activeTeam: { type: Array, default: () => [] },
  recentNotes: { type: Array, default: () => [] },
})

function formatNumber(n) {
  return n >= 1000 ? n.toLocaleString() : String(n)
}

onMounted(() => {
  // Initialize date range picker (easepick, still global during migration)
  if (window.drp && window._EP) {
    window.drp.init('dash-date-range', (from, to) => {
      if (!from) return
      router.get('/', { from, to }, { preserveState: true })
    }, { defaultFrom: props.from, defaultTo: props.to })
  }
})
</script>
