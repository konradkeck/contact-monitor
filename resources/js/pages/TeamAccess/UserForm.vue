<template>
  <AppLayout>
    <Head :title="user ? 'Edit User' : 'Add User'" />

    <div class="page-header">
      <div>
        <nav aria-label="Breadcrumb" class="page-breadcrumb">
          <Link href="/configuration/team-access?tab=users">Team Access</Link>
          <span class="sep">/</span>
          <span class="cur" aria-current="page">{{ user ? 'Edit User' : 'New User' }}</span>
        </nav>
        <h1 class="page-title mt-1">{{ user ? 'Edit User' : 'New User' }}</h1>
      </div>
    </div>

    <div class="card p-5 max-w-lg">
      <form @submit.prevent="submit">
        <div class="space-y-4">
          <div>
            <label class="label" for="f-name">Name</label>
            <input id="f-name" type="text" v-model="form.name" class="input" required placeholder="Full name">
            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
          </div>

          <div>
            <label class="label" for="f-email">Email</label>
            <input id="f-email" type="email" v-model="form.email" class="input" required placeholder="user@example.com">
            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
          </div>

          <div>
            <label class="label" for="f-group">Group</label>
            <select id="f-group" v-model="form.group_id" class="input" required>
              <option value="">&mdash; select group &mdash;</option>
              <option v-for="group in groups" :key="group.id" :value="group.id">{{ group.name }}</option>
            </select>
            <p v-if="form.errors.group_id" class="mt-1 text-xs text-red-600">{{ form.errors.group_id }}</p>
          </div>

          <div>
            <label class="label" for="f-password">
              Password
              <span v-if="user" class="text-gray-400 font-normal">(leave blank to keep)</span>
            </label>
            <input id="f-password" type="password" v-model="form.password" class="input"
                   :required="!user" placeholder="Min. 8 characters">
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
          </div>

          <div>
            <label class="label" for="f-password-confirm">Confirm Password</label>
            <input id="f-password-confirm" type="password" v-model="form.password_confirmation"
                   class="input" :required="!user">
          </div>
        </div>

        <div class="mt-5 pt-4 border-t border-gray-100 flex items-center gap-2">
          <button type="submit" class="btn btn-primary" :disabled="form.processing">
            {{ user ? 'Save changes' : 'Add User' }}
          </button>
          <Link href="/configuration/team-access?tab=users" class="btn btn-secondary">Cancel</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({
  user: { type: Object, default: null },
  groups: { type: Array, default: () => [] },
})

const form = useForm({
  name: props.user?.name || '',
  email: props.user?.email || '',
  group_id: props.user?.group_id || '',
  password: '',
  password_confirmation: '',
})

function submit() {
  if (props.user) {
    form.put(`/configuration/team-access/users/${props.user.id}`)
  } else {
    form.post('/configuration/team-access/users')
  }
}
</script>
