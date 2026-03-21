<template>
  <AppLayout>
    <Head title="Segmentation" />

    <div class="page-header">
      <div>
        <h1 class="page-title">Segmentation</h1>
        <p class="text-xs text-gray-400 mt-0.5">Define the products and service lines your company offers, then track which pipeline stage each client is in.</p>
      </div>
      <Link href="/configuration/segmentation/create" class="btn btn-primary">+ New Segmentation</Link>
    </div>

    <div class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="tbl-header">
          <tr>
            <th class="px-4 py-2.5 text-left">Name</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-left">Variant</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-left">Slug</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-center">Companies</th>
            <th class="px-4 py-2.5"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="product in products" :key="product.id" class="tbl-row">
            <td class="px-4 py-3 font-medium">
              <Link :href="`/configuration/segmentation/${product.id}/edit`" class="link">
                {{ product.name }}
              </Link>
              <span v-if="product.variant" class="md:hidden text-xs text-gray-400 ml-1">{{ product.variant }}</span>
            </td>
            <td class="col-mobile-hidden px-4 py-3 text-gray-500">{{ product.variant || '\u2014' }}</td>
            <td class="col-mobile-hidden px-4 py-3 font-mono text-gray-500 text-xs">{{ product.slug }}</td>
            <td class="col-mobile-hidden px-4 py-3 text-center text-gray-500">{{ product.company_statuses_count }}</td>
            <td class="px-4 py-3 text-right">
              <div class="row-actions-desktop">
                <Link :href="`/configuration/segmentation/${product.id}/edit`" class="btn btn-muted btn-sm">Edit</Link>
              </div>
            </td>
          </tr>
          <tr v-if="products.length === 0">
            <td colspan="5" class="px-4 py-8 text-center text-gray-400 italic">No segmentation configured.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

defineProps({
  products: { type: Array, default: () => [] },
})
</script>
