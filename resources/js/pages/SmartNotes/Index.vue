<template>
  <AppLayout>
    <Head title="Smart Notes" />

    <div class="page-header">
      <div>
        <h1 class="page-title flex items-center gap-2">
          <img :src="'/ai-icon.svg'" class="w-5 h-5" alt="">
          Smart Notes
        </h1>
      </div>
    </div>

    <div v-if="!smartNotesEnabled" class="alert-warning mb-5 flex items-center gap-3">
      <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12" stroke-width="1.75" stroke-linecap="round"/><circle cx="12" cy="16" r="0.75" fill="currentColor" stroke="none"/></svg>
      <span>Smart Notes is disabled. <Link href="/configuration/smart-notes" class="font-semibold underline hover:no-underline">Enable it in Configuration → Smart Notes</Link> to start capturing notes.</span>
    </div>

    <!-- Tabs -->
    <div class="flex gap-0 border-b border-gray-200 mb-5">
      <Link href="/smart-notes?tab=unrecognized"
            :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition -mb-px flex items-center gap-1.5',
                     tab === 'unrecognized' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700']">
        Unrecognized
        <span v-if="unrecognizedCount > 0"
              class="text-xs bg-red-100 text-red-700 border border-red-200 rounded-full px-1.5 py-0.5 font-medium">{{ unrecognizedCount }}</span>
      </Link>
      <Link href="/smart-notes?tab=recognized"
            :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition -mb-px flex items-center gap-1.5',
                     tab === 'recognized' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700']">
        Recognized
        <span v-if="recognizedCount > 0"
              class="text-xs bg-gray-100 text-gray-500 border border-gray-200 rounded-full px-1.5 py-0.5 font-medium">{{ recognizedCount }}</span>
      </Link>
    </div>

    <div class="card-xl-overflow">
      <table class="w-full text-sm">
        <thead class="tbl-header">
          <tr>
            <th class="px-4 py-2.5 text-left">Content</th>
            <th class="px-4 py-2.5 text-left">Source</th>
            <th class="px-4 py-2.5 text-left">Sender</th>
            <th class="px-4 py-2.5 text-left">Filter</th>
            <th class="px-4 py-2.5 text-left">Date</th>
            <th class="px-4 py-2.5 text-right w-32"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="note in notes.data" :key="note.id" class="tbl-row">
            <td class="px-4 py-3 max-w-sm">
              <p class="line-clamp-2 text-gray-800 text-xs leading-relaxed">{{ note.content }}</p>
              <span v-if="note.as_internal_note" class="badge badge-blue mt-1">Internal</span>
            </td>
            <td class="px-4 py-3">
              <span class="badge badge-gray">{{ note.source_label }}</span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-600">
              <template v-if="note.sender_name">
                <span class="font-medium">{{ note.sender_name }}</span>
                <br v-if="note.sender_value">
                <span v-if="note.sender_value" class="text-gray-400 font-mono">{{ note.sender_value }}</span>
              </template>
              <template v-else-if="note.sender_value">
                <span class="font-mono text-gray-500">{{ note.sender_value }}</span>
              </template>
              <span v-else class="text-gray-300">&mdash;</span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-500">
              {{ note.filter_type_label || '—' }}
            </td>
            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
              {{ note.occurred_at_formatted || '—' }}
            </td>
            <td class="px-4 py-3">
              <div class="row-actions-desktop items-center justify-end gap-1.5">
                <Link v-if="tab === 'unrecognized'" :href="`/smart-notes/${note.id}/recognize`"
                      class="row-action text-xs font-medium text-brand-600 hover:text-brand-700">Recognize</Link>
                <button v-else @click="unrecognize(note)" class="row-action text-xs">Unrecognize</button>
                <button @click="deleteNote(note)" class="row-action-danger text-xs">Delete</button>
              </div>
              <div class="row-actions-mobile relative">
                <button @click="openMenu = openMenu === note.id ? null : note.id"
                        class="text-gray-400 hover:text-gray-600 px-2 py-1 rounded text-base leading-none">···</button>
                <div v-if="openMenu === note.id"
                     class="absolute right-0 top-full mt-1 w-36 bg-white border border-gray-200 rounded-xl shadow-lg py-1 z-10 text-xs">
                  <Link v-if="tab === 'unrecognized'" :href="`/smart-notes/${note.id}/recognize`"
                        class="block px-3 py-2 text-brand-600 hover:bg-brand-50">Recognize</Link>
                  <button v-else @click="unrecognize(note)" class="block w-full text-left px-3 py-2 text-gray-700 hover:bg-gray-50">Unrecognize</button>
                  <button @click="deleteNote(note)" class="block w-full text-left px-3 py-2 text-red-600 hover:bg-red-50">Delete</button>
                </div>
              </div>
            </td>
          </tr>
          <tr v-if="!notes.data.length">
            <td colspan="6" class="px-4 py-10 text-center empty-state italic">
              <template v-if="tab === 'unrecognized'">
                No unrecognized Smart Notes. {{ smartNotesEnabled ? 'Filters will capture new messages automatically.' : '' }}
              </template>
              <template v-else>
                No recognized Smart Notes yet.
              </template>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="notes.links && notes.last_page > 1" class="px-4 py-3 border-t border-gray-100">
        <nav class="flex items-center gap-1">
          <template v-for="link in notes.links" :key="link.label">
            <Link v-if="link.url" :href="link.url"
                  class="px-3 py-1 text-sm rounded border"
                  :class="link.active ? 'bg-brand-600 text-white border-brand-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                  v-html="link.label" />
            <span v-else class="px-3 py-1 text-sm text-gray-400" v-html="link.label" />
          </template>
        </nav>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({
  tab: String,
  smartNotesEnabled: Boolean,
  unrecognizedCount: Number,
  recognizedCount: Number,
  notes: Object,
})

const openMenu = ref(null)

function unrecognize(note) {
  router.post(`/smart-notes/${note.id}/unrecognize`, {}, { preserveScroll: true })
}

function deleteNote(note) {
  if (confirm('Delete this Smart Note?')) {
    router.delete(`/smart-notes/${note.id}`, { preserveScroll: true })
  }
}
</script>
