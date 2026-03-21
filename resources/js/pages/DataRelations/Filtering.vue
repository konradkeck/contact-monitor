<template>
  <AppLayout>
    <Head title="Filtering — Data Relations" />

    <div class="page-header">
      <div>
        <h1 class="page-title">Filtering</h1>
        <p class="text-xs text-gray-400 mt-0.5">Block unwanted email addresses, domains, and companies from appearing in your data. Add filters here to suppress noise from automated senders, internal tools, and known spam sources.</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-bar flex gap-0 border-b border-gray-200 mb-5">
      <Link v-for="(cfg, tab) in tabConfig" :key="tab"
            :href="`/configuration/filtering?tab=${tab}`"
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

    <!-- DOMAINS -->
    <div v-if="activeTab === 'domains'" class="max-w-xl">
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
          <h2 class="font-semibold text-gray-800">Filter Domains</h2>
          <p class="text-xs text-gray-400 mt-0.5">Conversations or contacts from these domains will be excluded from views.</p>
        </div>
        <div class="px-5 py-4">
          <TagInput :tags="localDomains" placeholder="example.com — Enter or , to add" :splitComma="true"
                    @save="saveDomains" />
        </div>
      </div>
    </div>

    <!-- EMAILS -->
    <div v-if="activeTab === 'emails'" class="max-w-xl">
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
          <h2 class="font-semibold text-gray-800">Filter Emails</h2>
          <p class="text-xs text-gray-400 mt-0.5">Specific email addresses to exclude from views.</p>
        </div>
        <div class="px-5 py-4">
          <TagInput :tags="localEmails" placeholder="noreply@example.com — Enter or , to add" :splitComma="true"
                    @save="saveEmails" />
        </div>
      </div>
    </div>

    <!-- SUBJECTS -->
    <div v-if="activeTab === 'subjects'" class="max-w-xl">
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
          <h2 class="font-semibold text-gray-800">Filter Subjects</h2>
          <p class="text-xs text-gray-400 mt-0.5">Conversations whose subject/title matches these entries will be excluded from views. Exact match.</p>
        </div>
        <div class="px-5 py-4">
          <TagInput :tags="localSubjects" placeholder="Re: Your invoice — Enter to add" :splitComma="false"
                    @save="saveSubjects" />
          <p class="text-xs text-gray-400 mt-1.5">Press <kbd class="px-1 py-0.5 text-xs bg-gray-100 border border-gray-200 rounded">Enter</kbd> to add (no comma splitting — subjects may contain commas). Click x to remove.</p>
        </div>
      </div>
    </div>

    <!-- CONTACTS -->
    <div v-if="activeTab === 'contacts'" class="max-w-2xl">
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
          <h2 class="font-semibold text-gray-800">Filtered Contacts</h2>
          <p class="text-xs text-gray-400 mt-0.5">Contacts whose activity will be excluded from views. Add from the People list or contact details.</p>
        </div>

        <template v-if="filterContacts.length">
          <div class="divide-y divide-gray-100">
            <div v-for="person in filterContacts" :key="person.id" class="flex items-center gap-3 px-5 py-3">
              <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-xs font-bold shrink-0">
                {{ (person.first_name || '').charAt(0).toUpperCase() }}{{ (person.last_name || '').charAt(0).toUpperCase() }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800">{{ person.full_name }}</p>
              </div>
              <Link :href="`/people/${person.id}`" class="text-xs text-brand-600 hover:underline shrink-0">View &rarr;</Link>
              <button @click="removeContact(person)" class="text-xs text-red-400 hover:text-red-600 font-medium">x Remove</button>
            </div>
          </div>
        </template>
        <template v-else>
          <div class="px-6 py-10 text-center">
            <p class="text-gray-400 text-sm italic">No filtered contacts yet.</p>
            <p class="text-gray-300 text-xs mt-1">Add contacts from the People list or their detail page.</p>
          </div>
        </template>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const csrf = document.querySelector('meta[name="csrf-token"]')?.content || ''

const props = defineProps({
  activeTab: { type: String, default: 'domains' },
  filterDomains: { type: Array, default: () => [] },
  filterEmails: { type: Array, default: () => [] },
  filterSubjects: { type: Array, default: () => [] },
  filterContacts: { type: Array, default: () => [] },
})

const localDomains = ref([...props.filterDomains])
const localEmails = ref([...props.filterEmails])
const localSubjects = ref([...props.filterSubjects])

const tabConfig = computed(() => ({
  domains: { label: 'Domains', count: props.filterDomains.length },
  emails: { label: 'Emails', count: props.filterEmails.length },
  subjects: { label: 'Subjects', count: props.filterSubjects.length },
  contacts: { label: 'Contacts', count: props.filterContacts.length },
}))

function saveDomains(tags) {
  router.post('/configuration/filtering/domains', { domains: tags.join('\n') })
}
function saveEmails(tags) {
  router.post('/configuration/filtering/emails', { emails: tags.join('\n') })
}
function saveSubjects(tags) {
  router.post('/configuration/filtering/subjects', { subjects: tags.join('\n') })
}
function removeContact(person) {
  if (confirm(`Remove ${person.full_name} from filtered contacts?`)) {
    router.delete(`/configuration/filtering/contacts/${person.id}`)
  }
}

const TagInput = {
  props: {
    tags: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    splitComma: { type: Boolean, default: true },
  },
  emits: ['save'],
  setup(props, { emit }) {
    const items = ref([...props.tags])
    const input = ref('')
    const inputEl = ref(null)

    function add() {
      const sep = props.splitComma ? /[,\n]+/ : /\n+/
      const vals = input.value.split(sep).map(s => s.trim()).filter(Boolean)
      vals.forEach(v => { if (!items.value.includes(v)) items.value.push(v) })
      input.value = ''
      if (vals.length) emit('save', items.value)
    }

    function remove(tag) {
      items.value = items.value.filter(t => t !== tag)
      emit('save', items.value)
    }

    function onKey(e) {
      if (e.key === 'Enter' || (props.splitComma && e.key === ',')) {
        e.preventDefault()
        add()
      } else if (e.key === 'Backspace' && !input.value && items.value.length) {
        items.value.pop()
        emit('save', items.value)
      }
    }

    return { items, input, inputEl, remove, onKey }
  },
  template: `<div>
    <div class="w-full min-h-[44px] bg-white border border-gray-200 rounded-lg px-2 py-1.5 flex flex-wrap gap-1.5 focus-within:border-brand-400 focus-within:ring-2 focus-within:ring-brand-100 cursor-text transition"
         @click="$refs.tagInput?.focus()">
      <span v-for="tag in items" :key="tag"
            class="inline-flex items-center gap-1 bg-brand-100 text-brand-800 text-xs font-mono px-2 py-0.5 rounded-full max-w-[220px]">
        <span class="truncate">{{ tag }}</span>
        <button type="button" @click.stop="remove(tag)" class="text-brand-400 hover:text-brand-700 shrink-0 leading-none">
          <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
      </span>
      <input ref="tagInput" type="text" v-model="input" @keydown="onKey"
             :placeholder="placeholder"
             class="flex-1 min-w-[160px] text-xs text-gray-700 font-mono outline-none border-none bg-transparent py-0.5 placeholder-gray-300">
    </div>
    <p v-if="splitComma" class="text-xs text-gray-400 mt-1.5">Press <kbd class="px-1 py-0.5 text-xs bg-gray-100 border border-gray-200 rounded">Enter</kbd> or <kbd class="px-1 py-0.5 text-xs bg-gray-100 border border-gray-200 rounded">,</kbd> to add. Click x to remove.</p>
  </div>`
}
</script>
