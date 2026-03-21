<template>
  <AppLayout>
    <Head title="Mapping" />

    <div class="page-header">
      <div>
        <h1 class="page-title">Mapping</h1>
        <p class="text-xs text-gray-400 mt-0.5">Link external accounts and identities to companies and people.</p>
      </div>
      <Link href="/data-relations/our-company" class="btn btn-secondary">Our Organization</Link>
    </div>

    <!-- Global stats -->
    <div class="grid grid-cols-3 gap-4 mb-8">
      <div v-for="card in cards" :key="card.label"
           :class="['rounded-lg border p-4', card.value === 0 ? 'border-green-200 bg-green-50' : 'border-amber-300 bg-amber-50']">
        <p class="text-sm text-gray-600">{{ card.label }}</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">{{ card.value.toLocaleString() }}</p>
        <p class="text-xs text-gray-500 mt-0.5">of {{ card.total.toLocaleString() }} total</p>
      </div>
    </div>

    <!-- Account-based systems -->
    <div v-if="accountSystems.length" class="card mb-6">
      <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="font-semibold text-gray-800">Account-based Systems</h2>
        <p class="text-xs text-gray-400 mt-0.5">WHMCS, MetricsCube — accounts link to companies</p>
      </div>
      <table class="w-full text-sm">
        <thead class="tbl-header">
          <tr>
            <th class="px-4 py-2.5 text-left">System / Slug</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-right">Companies unlinked</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-right">Contacts unlinked</th>
            <th class="px-4 py-2.5 text-left">Linked %</th>
            <th class="px-4 py-2.5"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sys in accountSystems" :key="sys.system_slug" class="tbl-row">
            <td class="px-4 py-2">
              <div class="flex items-center gap-2">
                <span class="badge badge-gray text-xs">{{ sys.system_type }}</span>
                <span class="font-mono text-xs text-gray-700">{{ sys.system_slug }}</span>
              </div>
            </td>
            <td :class="['col-mobile-hidden px-4 py-2 text-right', sys.unlinked > 0 ? 'text-amber-600 font-semibold' : 'text-green-600']">
              {{ Number(sys.unlinked).toLocaleString() }}
            </td>
            <td :class="['col-mobile-hidden px-4 py-2 text-right', sys.contacts_unlinked > 0 ? 'text-amber-600 font-semibold' : 'text-green-600']">
              {{ Number(sys.contacts_unlinked).toLocaleString() }}
            </td>
            <td class="px-4 py-2">
              <LinkedPctBar :pct="sys.total > 0 ? Math.round((sys.total - sys.unlinked) / sys.total * 100) : 100" />
            </td>
            <td class="px-4 py-2 text-right">
              <Link :href="`/configuration/mapping/${sys.system_type}/${sys.system_slug}`" class="btn btn-sm btn-muted" title="Manage Mapping">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span class="hidden sm:inline">Manage Mapping</span>
              </Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Identity-based systems -->
    <div v-if="identitySystems.length" class="card mb-6">
      <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="font-semibold text-gray-800">Identity-based Systems</h2>
        <p class="text-xs text-gray-400 mt-0.5">IMAP, Slack, Discord — identities link to people</p>
      </div>
      <table class="w-full text-sm">
        <thead class="tbl-header">
          <tr>
            <th class="px-4 py-2.5 text-left">System / Slug</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-right">Unlinked</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-right">Total</th>
            <th class="px-4 py-2.5 text-left">Linked %</th>
            <th class="px-4 py-2.5"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sys in identitySystems" :key="sys.system_slug + sys.type" class="tbl-row">
            <td class="px-4 py-2">
              <div class="flex items-center gap-2">
                <span class="badge badge-gray text-xs">{{ sys.type }}</span>
                <span class="font-mono text-xs text-gray-700">{{ sys.system_slug }}</span>
              </div>
            </td>
            <td :class="['col-mobile-hidden px-4 py-2 text-right', sys.unlinked > 0 ? 'text-amber-600 font-semibold' : 'text-green-600']">
              {{ Number(sys.unlinked).toLocaleString() }}
            </td>
            <td class="col-mobile-hidden px-4 py-2 text-right text-gray-500">{{ Number(sys.total).toLocaleString() }}</td>
            <td class="px-4 py-2">
              <LinkedPctBar :pct="sys.total > 0 ? Math.round((sys.total - sys.unlinked) / sys.total * 100) : 100" />
            </td>
            <td class="px-4 py-2 text-right">
              <Link :href="`/configuration/mapping/${sys.system_type}/${sys.system_slug}`" class="btn btn-sm btn-muted" title="Manage Mapping">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span class="hidden sm:inline">Manage Mapping</span>
              </Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const LinkedPctBar = {
  props: { pct: Number },
  template: `<div class="flex items-center gap-2">
    <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden">
      <div :class="['h-full rounded-full', pct >= 80 ? 'bg-green-500' : pct >= 50 ? 'bg-amber-400' : 'bg-red-400']"
           :style="{ width: pct + '%' }"></div>
    </div>
    <span class="text-xs text-gray-500">{{ pct }}%</span>
  </div>`
}

defineProps({
  stats: Object,
  accountSystems: { type: Array, default: () => [] },
  identitySystems: { type: Array, default: () => [] },
  cards: { type: Array, default: () => [] },
})
</script>
