<template>
  <AppLayout>
    <Head :title="`Edit ${person.first_name} ${person.last_name || ''}`" />

    <div class="max-w-xl">
      <div class="page-header">
        <div>
          <nav aria-label="Breadcrumb" class="page-breadcrumb">
            <Link href="/people">People</Link>
            <span class="sep">/</span>
            <Link :href="`/people/${person.id}`">{{ person.first_name }} {{ person.last_name }}</Link>
            <span class="sep">/</span>
            <span class="cur" aria-current="page">Edit</span>
          </nav>
          <h1 class="page-title mt-1">Edit Person</h1>
        </div>
      </div>

      <div class="card p-6">
        <form @submit.prevent="submit">
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">First Name <span class="text-red-500">*</span></label>
                <input v-model="form.first_name" type="text" required class="input w-full">
                <p v-if="form.errors.first_name" class="text-red-500 text-xs mt-1">{{ form.errors.first_name }}</p>
              </div>
              <div>
                <label class="label">Last Name</label>
                <input v-model="form.last_name" type="text" class="input w-full">
                <p v-if="form.errors.last_name" class="text-red-500 text-xs mt-1">{{ form.errors.last_name }}</p>
              </div>
            </div>
            <div class="pt-1">
              <label class="flex items-center gap-2.5 cursor-pointer select-none group">
                <input v-model="form.is_our_org" type="checkbox" :true-value="1" :false-value="0"
                       class="w-4 h-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                <span class="text-sm font-medium text-gray-700">Our Organization</span>
                <span class="text-xs text-gray-400">(member of our team)</span>
              </label>
            </div>
          </div>
          <div class="flex gap-2 mt-6">
            <button type="submit" class="btn btn-primary" :disabled="form.processing">Save</button>
            <Link :href="`/people/${person.id}`" class="btn btn-secondary">Cancel</Link>
          </div>
        </form>
      </div>

      <div class="mt-4 pt-4 border-t border-gray-100">
        <button @click="deletePerson" class="text-sm text-red-500 hover:text-red-700">Delete this person</button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({
  person: Object,
})

const form = useForm({
  first_name: props.person.first_name,
  last_name: props.person.last_name || '',
  is_our_org: props.person.is_our_org ? 1 : 0,
})

function submit() {
  form.put(`/people/${props.person.id}`)
}

function deletePerson() {
  if (confirm('Delete this person?')) {
    router.delete(`/people/${props.person.id}`)
  }
}
</script>
