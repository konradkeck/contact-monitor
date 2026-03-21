<template>
  <AppLayout>
    <Head title="Recognize Smart Note" />

    <div class="page-header">
      <div>
        <Link href="/smart-notes" class="page-breadcrumb-back">&larr; Smart Notes</Link>
        <h1 class="page-title mt-1 flex items-center gap-2">
          <img :src="'/ai-icon.svg'" class="w-5 h-5" alt="">
          Recognize Smart Note
        </h1>
      </div>
    </div>

    <!-- Original message -->
    <div class="card p-5 mb-5 max-w-4xl">
      <div class="flex items-start justify-between gap-4 mb-3">
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Original Message</p>
          <p v-if="smartNote.sender_name || smartNote.sender_value" class="text-sm text-gray-600 mt-0.5">
            From:
            <strong v-if="smartNote.sender_name">{{ smartNote.sender_name }}</strong>
            <span v-if="smartNote.sender_value" class="font-mono text-gray-500 ml-1">{{ smartNote.sender_value }}</span>
          </p>
        </div>
        <div class="flex items-center gap-2 shrink-0 text-xs text-gray-400">
          <span class="badge badge-gray">{{ smartNote.source_label }}</span>
          <span v-if="smartNote.occurred_at_formatted">{{ smartNote.occurred_at_formatted }}</span>
        </div>
      </div>
      <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-sm text-gray-800 whitespace-pre-wrap leading-relaxed font-mono text-xs max-h-48 overflow-y-auto">{{ smartNote.content }}</div>
    </div>

    <!-- Segments editor -->
    <div class="max-w-4xl">
      <div class="flex items-center justify-between mb-3">
        <p class="section-header-title">Note Segments</p>
        <button type="button" @click="addSegment" class="btn btn-secondary btn-sm">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          Split / Add Segment
        </button>
      </div>

      <form @submit.prevent="submit">
        <div class="space-y-4">
          <div v-for="(seg, index) in segments" :key="index" class="card p-4">
            <div class="flex items-start gap-3">
              <div class="flex-1 min-w-0">
                <label class="label">Content</label>
                <textarea v-model="seg.content" rows="4"
                          class="input w-full text-xs font-mono resize-y"
                          placeholder="Note content..."></textarea>
              </div>
              <button v-if="segments.length > 1" type="button" @click="removeSegment(index)"
                      class="shrink-0 mt-6 text-gray-400 hover:text-red-500 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-3">
              <div>
                <label class="label">Assign to</label>
                <select v-model="seg.assign_to" class="input w-full">
                  <option value="">— Do not assign —</option>
                  <option value="company">Company</option>
                  <option value="person">Person</option>
                </select>
              </div>

              <!-- Company search -->
              <div v-if="seg.assign_to === 'company'" class="relative">
                <label class="label">Company</label>
                <input v-model="seg._companyQuery" type="text" class="input w-full" placeholder="Search company..."
                       @focus="seg._showDropdown = true" @input="seg._showDropdown = true">
                <div v-if="seg._showDropdown"
                     class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg py-1 max-h-48 overflow-y-auto">
                  <button v-for="c in filteredCompanies(seg._companyQuery)" :key="c.id" type="button"
                          @click="seg.entity_id = c.id; seg._companyQuery = c.name; seg._showDropdown = false"
                          class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50">{{ c.name }}</button>
                  <p v-if="filteredCompanies(seg._companyQuery).length === 0"
                     class="px-3 py-2 text-xs text-gray-400 italic">No results</p>
                </div>
              </div>

              <!-- Person search -->
              <div v-if="seg.assign_to === 'person'" class="relative">
                <label class="label">Person</label>
                <input v-model="seg._personQuery" type="text" class="input w-full" placeholder="Search person..."
                       @focus="seg._showDropdown = true" @input="seg._showDropdown = true">
                <div v-if="seg._showDropdown"
                     class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg py-1 max-h-48 overflow-y-auto">
                  <button v-for="p in filteredPeople(seg._personQuery)" :key="p.id" type="button"
                          @click="seg.entity_id = p.id; seg._personQuery = p.name; seg._showDropdown = false"
                          class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50">{{ p.name }}</button>
                  <p v-if="filteredPeople(seg._personQuery).length === 0"
                     class="px-3 py-2 text-xs text-gray-400 italic">No results</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex gap-2 mt-5">
          <button type="submit" class="btn btn-primary" :disabled="processing">Save & Recognize</button>
          <Link href="/smart-notes" class="btn btn-secondary">Cancel</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({
  smartNote: Object,
  companies: Array,
  people: Array,
})

const processing = ref(false)

const initialSegments = (props.smartNote.segments_json || [{ content: props.smartNote.content, assign_to: null, entity_id: null }])
  .map(s => ({
    content: s.content || '',
    assign_to: s.company_id ? 'company' : (s.person_id ? 'person' : (s.assign_to || '')),
    entity_id: s.company_id || s.person_id || s.entity_id || null,
    _companyQuery: '',
    _personQuery: '',
    _showDropdown: false,
  }))

const segments = reactive(initialSegments)

function addSegment() {
  segments.push({ content: '', assign_to: '', entity_id: null, _companyQuery: '', _personQuery: '', _showDropdown: false })
}

function removeSegment(index) {
  if (segments.length <= 1) return
  segments.splice(index, 1)
}

function filteredCompanies(q) {
  if (!q) return props.companies.slice(0, 10)
  const lq = q.toLowerCase()
  return props.companies.filter(c => c.name.toLowerCase().includes(lq)).slice(0, 10)
}

function filteredPeople(q) {
  if (!q) return props.people.slice(0, 10)
  const lq = q.toLowerCase()
  return props.people.filter(p => p.name.toLowerCase().includes(lq)).slice(0, 10)
}

function submit() {
  processing.value = true
  const payload = {
    segments: segments.map(s => ({
      content: s.content,
      assign_to: s.assign_to || null,
      entity_id: s.entity_id,
    })),
  }
  router.post(`/smart-notes/${props.smartNote.id}/recognize`, payload, {
    onFinish: () => { processing.value = false },
  })
}
</script>
