<template>
  <AppLayout>
    <Head title="Change Password" />

    <div class="max-w-md">
      <div class="page-header">
        <h1 class="page-title">Change Password</h1>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 px-6 py-6">
        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="label" for="current_password">Current password</label>
            <input id="current_password" v-model="form.current_password" type="password"
                   class="input" required autocomplete="current-password">
            <p v-if="form.errors.current_password" class="mt-1 text-xs text-red-600">{{ form.errors.current_password }}</p>
          </div>

          <div>
            <label class="label" for="password">New password</label>
            <input id="password" v-model="form.password" type="password"
                   class="input" required autocomplete="new-password">
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
          </div>

          <div>
            <label class="label" for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password"
                   class="input" required autocomplete="new-password">
          </div>

          <button type="submit" class="btn btn-primary w-full py-2.5 justify-center" :disabled="form.processing">
            Change Password
          </button>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '../layouts/AppLayout.vue'

const form = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
})

function submit() {
  form.post('/change-password', {
    onSuccess: () => form.reset(),
  })
}
</script>
