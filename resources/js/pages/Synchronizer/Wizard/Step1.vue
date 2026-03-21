<template>
  <AppLayout>
    <Head title="Add Synchronizer Server" />

    <div class="page-header">
      <h1 class="page-title">Add Synchronizer Server</h1>
      <Link href="/configuration/synchronizer-servers" class="btn btn-secondary btn-sm">Cancel</Link>
    </div>

    <p class="text-sm text-gray-500 mb-6">Choose how you want to connect a synchronizer server.</p>

    <div class="grid grid-cols-2 gap-4 max-w-2xl">
      <Link href="/configuration/synchronizer-servers/wizard/configure-new"
            class="card p-6 flex flex-col gap-4 hover:border-brand-400 hover:shadow-sm transition group cursor-pointer no-underline">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-blue-50">
          <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </div>
        <div>
          <div class="font-semibold text-gray-900 group-hover:text-brand-700 transition">Configure New Server</div>
          <div class="text-xs text-gray-500 mt-1 leading-relaxed">
            Install a fresh synchronizer on a new machine. You'll get a one-liner setup command to run.
          </div>
        </div>
      </Link>

      <Link href="/configuration/synchronizer-servers/wizard/connect-existing"
            class="card p-6 flex flex-col gap-4 hover:border-brand-400 hover:shadow-sm transition group cursor-pointer no-underline">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-green-50">
          <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
          </svg>
        </div>
        <div>
          <div class="font-semibold text-gray-900 group-hover:text-brand-700 transition">Connect to Existing Server</div>
          <div class="text-xs text-gray-500 mt-1 leading-relaxed">
            Connect to a synchronizer that's already running. You'll need the URL and API token.
          </div>
        </div>
      </Link>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '../../../layouts/AppLayout.vue'
</script>
