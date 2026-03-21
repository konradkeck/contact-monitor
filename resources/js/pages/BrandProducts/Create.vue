<template>
  <AppLayout>
    <Head title="New Segmentation" />

    <div class="max-w-xl">
      <div class="page-header">
        <div>
          <nav aria-label="Breadcrumb" class="page-breadcrumb">
            <Link href="/configuration/segmentation">Segmentation</Link>
            <span class="sep">/</span>
            <span class="cur" aria-current="page">New Segmentation</span>
          </nav>
          <h1 class="page-title mt-1">New Segmentation</h1>
        </div>
      </div>

      <form @submit.prevent="submit" class="bg-white rounded-lg border border-gray-200 p-5 space-y-4">
        <div>
          <label class="label">Name <span class="text-red-500">*</span></label>
          <input type="text" v-model="form.name" required class="input w-full">
          <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
        </div>
        <div>
          <label class="label">Variant <span class="text-xs text-gray-400 font-normal">(optional)</span></label>
          <input type="text" v-model="form.variant" placeholder="e.g. Cloud, On-Premise" class="input w-full">
        </div>
        <div>
          <label class="label">Slug <span class="text-xs text-gray-400 font-normal">(auto-generated if empty)</span></label>
          <input type="text" v-model="form.slug" placeholder="my-segmentation" class="input w-full font-mono">
          <p v-if="form.errors.slug" class="text-red-500 text-xs mt-1">{{ form.errors.slug }}</p>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Link href="/configuration/segmentation" class="btn btn-secondary">Cancel</Link>
          <button type="submit" class="btn btn-primary" :disabled="form.processing">Create</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const form = useForm({
  name: '',
  variant: '',
  slug: '',
})

function submit() {
  form.post('/configuration/segmentation')
}
</script>
