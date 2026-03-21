<template>
  <AppLayout>
    <Head title="New Person" />

    <div class="max-w-xl">
      <div class="page-header">
        <div>
          <nav aria-label="Breadcrumb" class="page-breadcrumb">
            <Link href="/people">People</Link>
            <span class="sep">/</span>
            <span class="cur" aria-current="page">New Person</span>
          </nav>
          <h1 class="page-title mt-1">New Person</h1>
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
            <button type="submit" class="btn btn-primary" :disabled="form.processing">Create</button>
            <Link href="/people" class="btn btn-secondary">Cancel</Link>
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
  first_name: '',
  last_name: '',
  is_our_org: 0,
})

function submit() {
  form.post('/people')
}
</script>
