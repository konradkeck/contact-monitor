<template>
  <AppLayout>
    <Head title="AI Pricing Overrides" />

    <div class="page-header">
      <div>
        <Link href="/configuration/ai-costs" class="page-breadcrumb-back">&larr; AI Costs</Link>
        <h1 class="page-title">Pricing Overrides</h1>
      </div>
    </div>

    <div class="card p-5 mb-5 max-w-2xl">
      <p class="text-xs text-gray-500 mb-1">
        Set custom prices per 1M tokens. Leave the table empty to use built-in defaults.<br>
        Defaults are sourced from provider pricing pages.
      </p>
    </div>

    <form @submit.prevent="submit">
      <div class="flex items-center justify-between mb-4">
        <h2 class="section-header-title">Overrides</h2>
        <button type="button" @click="addRow" class="btn btn-sm btn-secondary">+ Add Override</button>
      </div>

      <div class="card-xl-overflow mb-5">
        <table class="w-full text-sm">
          <thead class="tbl-header">
            <tr>
              <th class="px-4 py-2.5 text-left">Model</th>
              <th class="px-4 py-2.5 text-right w-40">Input ($/1M)</th>
              <th class="px-4 py-2.5 text-right w-40">Output ($/1M)</th>
              <th class="px-4 py-2.5 w-10"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in rows" :key="idx" class="tbl-row">
              <td class="px-4 py-2">
                <input type="text" v-model="row.model" class="input w-full font-mono text-xs" placeholder="model-id" required>
              </td>
              <td class="px-4 py-2">
                <input type="number" v-model.number="row.input_price" class="input w-full text-right" step="0.0001" min="0" required>
              </td>
              <td class="px-4 py-2">
                <input type="number" v-model.number="row.output_price" class="input w-full text-right" step="0.0001" min="0" required>
              </td>
              <td class="px-4 py-2 text-center">
                <button type="button" @click="rows.splice(idx, 1)" class="text-gray-400 hover:text-red-500 text-xs">&#10005;</button>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="4" class="px-4 py-6 text-center empty-state italic">No overrides. Add one above or save to use defaults.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm" :disabled="processing">Save Overrides</button>
        <Link href="/configuration/ai-costs" class="btn btn-secondary btn-sm">Cancel</Link>
      </div>
    </form>

    <!-- Defaults reference -->
    <div class="card-xl-overflow mt-6">
      <div class="card-header">
        <span class="section-header-title">Default Prices (read-only reference)</span>
      </div>
      <table class="w-full text-sm">
        <thead class="tbl-header">
          <tr>
            <th class="px-4 py-2.5 text-left">Model</th>
            <th class="px-4 py-2.5 text-right">Input ($/1M)</th>
            <th class="px-4 py-2.5 text-right">Output ($/1M)</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(prices, model) in defaults" :key="model" class="tbl-row">
            <td class="px-4 py-2 font-mono text-xs">{{ model }}</td>
            <td class="px-4 py-2 text-right text-gray-600">${{ prices.input }}</td>
            <td class="px-4 py-2 text-right text-gray-600">${{ prices.output }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../layouts/AppLayout.vue'

const props = defineProps({
  defaults: { type: Object, required: true },
  overrides: { type: Object, default: () => ({}) },
})

const processing = ref(false)

// Convert overrides object to rows array
const rows = reactive(
  Object.entries(props.overrides).map(([model, prices]) => ({
    model,
    input_price: prices.input,
    output_price: prices.output,
  }))
)

function addRow() {
  rows.push({ model: '', input_price: 0, output_price: 0 })
}

function submit() {
  processing.value = true
  router.post('/configuration/ai-costs/pricing', {
    overrides: rows,
  }, {
    onFinish: () => { processing.value = false },
  })
}
</script>
