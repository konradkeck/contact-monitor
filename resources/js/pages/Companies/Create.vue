<template>
  <AppLayout>
    <Head title="New Company" />

    <div class="max-w-xl">
      <div class="page-header">
        <div>
          <nav aria-label="Breadcrumb" class="page-breadcrumb">
            <Link href="/companies">Companies</Link>
            <span class="sep">/</span>
            <span class="cur" aria-current="page">New Company</span>
          </nav>
          <h1 class="page-title mt-1">New Company</h1>
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
              <label class="label">Primary Domain</label>
              <input v-model="form.primary_domain" type="text" placeholder="example.com" class="input w-full">
            </div>
            <div>
              <label class="label">Timezone</label>
              <input v-model="form.timezone" type="text" placeholder="Europe/Warsaw" class="input w-full">
            </div>
          </div>
          <div class="flex gap-2 mt-6">
            <button type="submit" class="btn btn-primary" :disabled="form.processing">Create Company</button>
            <Link href="/companies" class="btn btn-secondary">Cancel</Link>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const form = useForm({
  name: '',
  primary_domain: '',
  timezone: 'Europe/Warsaw',
})

function submit() {
  form.post('/companies')
}
</script>
