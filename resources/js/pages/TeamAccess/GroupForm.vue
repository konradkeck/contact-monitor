<template>
  <AppLayout>
    <Head :title="group ? 'Edit Group' : 'Create Group'" />

    <div class="page-header">
      <div>
        <nav aria-label="Breadcrumb" class="page-breadcrumb">
          <Link href="/configuration/team-access?tab=groups">Team Access</Link>
          <span class="sep">/</span>
          <span class="cur" aria-current="page">{{ group ? 'Edit Group' : 'New Group' }}</span>
        </nav>
        <h1 class="page-title mt-1">{{ group ? 'Edit Group' : 'New Group' }}</h1>
      </div>
    </div>

    <div class="card p-5 max-w-lg">
      <form @submit.prevent="submit">
        <div class="space-y-4">
          <div>
            <label class="label" for="f-name">Group name</label>
            <input id="f-name" type="text" v-model="form.name" class="input" required placeholder="e.g. Support">
            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
          </div>

          <div class="space-y-2">
            <p class="label">Permissions (ACL)</p>
            <label v-for="(def, key) in permLabels" :key="key" class="flex items-start gap-3 cursor-pointer">
              <input type="checkbox" v-model="form.permissions[key]"
                     :true-value="true" :false-value="false"
                     class="mt-0.5 rounded border-gray-300 cursor-pointer">
              <span>
                <span class="text-sm font-medium text-gray-800">{{ def.label }}</span>
                <span class="block text-xs text-gray-400">{{ def.desc }}</span>
              </span>
            </label>
          </div>
        </div>

        <div class="mt-5 pt-4 border-t border-gray-100 flex items-center gap-2">
          <button type="submit" class="btn btn-primary" :disabled="form.processing">
            {{ group ? 'Save changes' : 'Create Group' }}
          </button>
          <Link href="/configuration/team-access?tab=groups" class="btn btn-secondary">Cancel</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'

const props = defineProps({
  group: { type: Object, default: null },
  permLabels: { type: Object, required: true },
})

const permKeys = Object.keys(props.permLabels)
const initialPerms = {}
for (const key of permKeys) {
  initialPerms[key] = props.group?.permissions?.[key] ?? false
}

const form = useForm({
  name: props.group?.name || '',
  permissions: initialPerms,
})

function submit() {
  if (props.group) {
    form.put(`/configuration/team-access/groups/${props.group.id}`)
  } else {
    form.post('/configuration/team-access/groups')
  }
}
</script>
