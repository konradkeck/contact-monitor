<template>
  <AppLayout>
    <Head :title="`Edit ${company.name}`" />

    <div class="max-w-xl">
      <div class="page-header">
        <div>
          <nav aria-label="Breadcrumb" class="page-breadcrumb">
            <Link href="/companies">Companies</Link>
            <span class="sep">/</span>
            <Link :href="`/companies/${company.id}`">{{ company.name }}</Link>
            <span class="sep">/</span>
            <span class="cur" aria-current="page">Edit</span>
          </nav>
          <h1 class="page-title mt-1">Edit Company</h1>
        </div>
      </div>

      <div class="card p-6">
        <form @submit.prevent="submit">
          <div class="space-y-4">
            <div>
              <label class="label">Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" required class="input w-full">
              <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
            </div>
            <div>
              <label class="label">Primary Domain <span class="text-xs text-gray-400">(display only)</span></label>
              <input v-model="form.primary_domain" type="text" class="input w-full">
            </div>
            <div>
              <label class="label">Timezone</label>
              <input v-model="form.timezone" type="text" class="input w-full">
            </div>
          </div>
          <div class="flex gap-2 mt-6">
            <button type="submit" class="btn btn-primary" :disabled="form.processing">Save</button>
            <Link :href="`/companies/${company.id}`" class="btn btn-secondary">Cancel</Link>
          </div>
        </form>
      </div>

      <div class="mt-4 pt-4 border-t border-gray-100">
        <button @click="deleteCompany" class="text-sm text-red-500 hover:text-red-700">Delete this company</button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({
  company: Object,
})

const form = useForm({
  name: props.company.name,
  primary_domain: props.company.primary_domain || '',
  timezone: props.company.timezone || '',
})

function submit() {
  form.put(`/companies/${props.company.id}`)
}

function deleteCompany() {
  if (confirm('Delete this company? This cannot be undone.')) {
    router.delete(`/companies/${props.company.id}`)
  }
}
</script>
