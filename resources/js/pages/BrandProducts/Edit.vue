<template>
  <AppLayout>
    <Head :title="'Edit ' + brandProduct.name" />

    <div class="max-w-xl">
      <div class="page-header">
        <div>
          <nav aria-label="Breadcrumb" class="page-breadcrumb">
            <Link href="/configuration/segmentation">Segmentation</Link>
            <span class="sep">/</span>
            <span class="cur" aria-current="page">{{ brandProduct.name }}</span>
          </nav>
          <h1 class="page-title mt-1">Edit Segmentation</h1>
        </div>
      </div>

      <form @submit.prevent="submit" class="bg-white rounded-lg border border-gray-200 p-5 space-y-4">
        <div>
          <label class="label">Name <span class="text-red-500">*</span></label>
          <input type="text" v-model="form.name" required class="input w-full">
          <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
        </div>
        <div>
          <label class="label">Variant</label>
          <input type="text" v-model="form.variant" class="input w-full">
        </div>
        <div>
          <label class="label">Slug</label>
          <input type="text" v-model="form.slug" required class="input w-full font-mono">
          <p v-if="form.errors.slug" class="text-red-500 text-xs mt-1">{{ form.errors.slug }}</p>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Link href="/configuration/segmentation" class="btn btn-secondary">Cancel</Link>
          <button type="submit" class="btn btn-primary" :disabled="form.processing">Save</button>
        </div>
      </form>

      <div class="mt-4 pt-4 border-t border-gray-100">
        <button @click="confirmDelete" class="text-sm text-red-500 hover:text-red-700">Delete this segmentation</button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({
  brandProduct: { type: Object, required: true },
})

const form = useForm({
  name: props.brandProduct.name,
  variant: props.brandProduct.variant || '',
  slug: props.brandProduct.slug,
})

function submit() {
  form.put(`/configuration/segmentation/${props.brandProduct.id}`)
}

function confirmDelete() {
  if (confirm('Delete?')) {
    router.delete(`/configuration/segmentation/${props.brandProduct.id}`)
  }
}
</script>
