<template>
  <AppLayout>
    <Head title="Team Access" />

    <div class="page-header">
      <div>
        <h1 class="page-title">Team Access</h1>
        <p class="text-xs text-gray-400 mt-0.5">Manage who can access this system and what they can do.</p>
      </div>
      <Link v-if="activeTab === 'users'" href="/configuration/team-access/users/create" class="btn btn-primary">
        + New User
      </Link>
      <Link v-else href="/configuration/team-access/groups/create" class="btn btn-primary">
        + New Group
      </Link>
    </div>

    <!-- Tabs -->
    <div class="flex gap-0 border-b border-gray-200 mb-5">
      <Link v-for="tab in tabs" :key="tab.key"
            :href="`/configuration/team-access?tab=${tab.key}`"
            :class="['px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition',
                     activeTab === tab.key ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
        {{ tab.label }}
      </Link>
    </div>

    <!-- Users Tab -->
    <div v-if="activeTab === 'users'" class="card overflow-hidden max-w-2xl">
      <table v-if="users.length > 0" class="w-full text-sm">
        <thead class="tbl-header">
          <tr>
            <th class="px-4 py-2.5 text-left font-medium">Name</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-left font-medium">Email</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-left font-medium">Group</th>
            <th class="px-4 py-2.5"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="user in users" :key="user.id" class="tbl-row">
            <td class="px-4 py-2.5">
              <div class="flex items-center gap-2">
                <img :src="gravatarUrl(user.email)" class="w-7 h-7 rounded-full shrink-0" alt="">
                <div class="min-w-0">
                  <span class="font-medium text-gray-800">{{ user.name }}</span>
                  <span class="md:hidden block text-xs text-gray-400 font-mono truncate">{{ user.email }}</span>
                </div>
              </div>
            </td>
            <td class="col-mobile-hidden px-4 py-2.5 text-gray-500 text-xs font-mono">{{ user.email }}</td>
            <td class="col-mobile-hidden px-4 py-2.5">
              <span :class="['badge', groupBadge(user.group?.name)]">{{ user.group?.name || '\u2014' }}</span>
            </td>
            <td class="px-4 py-2.5 text-right">
              <div class="row-actions-desktop items-center justify-end gap-1.5">
                <Link :href="`/configuration/team-access/users/${user.id}/edit`" class="btn btn-sm btn-muted">Edit</Link>
                <button v-if="user.id !== currentUserId" @click="deleteUser(user)" class="btn btn-sm btn-danger">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-else class="px-5 py-12 text-center text-sm text-gray-400 italic">No users yet.</div>
    </div>

    <!-- Groups Tab -->
    <div v-if="activeTab === 'groups'" class="card overflow-hidden max-w-3xl">
      <table v-if="groups.length > 0" class="w-full text-sm">
        <thead class="tbl-header">
          <tr>
            <th class="px-4 py-2.5 text-left font-medium w-40">Group</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-left font-medium">Permissions</th>
            <th class="col-mobile-hidden px-4 py-2.5 text-left font-medium w-36">Users</th>
            <th class="px-4 py-2.5 w-24"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="group in groups" :key="group.id" class="tbl-row">
            <td class="px-4 py-3">
              <div class="font-semibold text-gray-800">{{ group.name }}</div>
              <div class="md:hidden flex flex-wrap gap-1 mt-1">
                <span v-for="(label, key) in permLabels" :key="key"
                      v-show="group.permissions?.[key]"
                      class="badge badge-blue text-xs">{{ label }}</span>
              </div>
            </td>
            <td class="col-mobile-hidden px-4 py-3">
              <div class="flex flex-wrap gap-1">
                <span v-for="(label, key) in permLabels" :key="key"
                      v-show="group.permissions?.[key]"
                      class="badge badge-blue text-xs">{{ label }}</span>
              </div>
            </td>
            <td class="col-mobile-hidden px-4 py-3">
              <div v-if="group.users_count > 0" class="flex flex-wrap gap-1">
                <span v-for="u in groupUsers(group.id).slice(0, 3)" :key="u.id"
                      class="text-xs text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">{{ u.name }}</span>
                <span v-if="group.users_count > 3" class="text-xs text-gray-400">+{{ group.users_count - 3 }} more</span>
              </div>
              <span v-else class="text-gray-400 text-xs italic">No users</span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="row-actions-desktop items-center justify-end gap-1.5">
                <Link :href="`/configuration/team-access/groups/${group.id}/edit`" class="btn btn-sm btn-muted">Edit</Link>
                <button v-if="group.users_count === 0" @click="deleteGroup(group)" class="btn btn-sm btn-danger">Delete</button>
                <span v-else class="text-xs text-gray-300" title="Cannot delete: users assigned">Delete</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-else class="px-5 py-12 text-center text-sm text-gray-400 italic">No groups yet.</div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import md5 from '../../utils/md5.js'

const props = defineProps({
  groups: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  activeTab: { type: String, default: 'users' },
  permLabels: { type: Object, default: () => ({}) },
})

const page = usePage()
const currentUserId = computed(() => page.props.auth?.user?.id)

const tabs = computed(() => [
  { key: 'users', label: `Users (${props.users.length})` },
  { key: 'groups', label: `Groups (${props.groups.length})` },
])

function groupBadge(name) {
  if (name === 'Admin') return 'badge-blue'
  if (name === 'Analyst') return 'badge-green'
  return 'badge-gray'
}

function groupUsers(groupId) {
  return props.users.filter(u => u.group_id === groupId)
}

function gravatarUrl(email) {
  const hash = md5((email || '').trim().toLowerCase())
  return `https://www.gravatar.com/avatar/${hash}?s=28&d=mp`
}

function deleteUser(user) {
  if (confirm(`Delete user ${user.name}?`)) {
    router.delete(`/configuration/team-access/users/${user.id}`)
  }
}

function deleteGroup(group) {
  if (confirm(`Delete group ${group.name}?`)) {
    router.delete(`/configuration/team-access/groups/${group.id}`)
  }
}
</script>
