<template>
  <!-- LEFT cell (customer) -->
  <div class="flex items-center justify-end py-1.5 pr-2 min-w-0 overflow-hidden">
    <TimelineCell
      v-if="activity.display.isCustomer"
      :activity="activity"
      :showPersonLink="showPersonLink"
      :showCompanyLink="showCompanyLink"
      side="left"
      @openModal="$emit('openModal', $event)"
    />
  </div>

  <!-- CENTER dot -->
  <div class="flex items-center justify-center py-2 relative z-10">
    <div class="w-2.5 h-2.5 rounded-full ring-2 border-2 border-white shrink-0" :class="activity.dot_color" />
  </div>

  <!-- RIGHT cell (internal) -->
  <div class="flex items-center justify-start py-1.5 pl-2 min-w-0 overflow-hidden">
    <TimelineCell
      v-if="!activity.display.isCustomer"
      :activity="activity"
      :showPersonLink="showPersonLink"
      :showCompanyLink="showCompanyLink"
      side="right"
      @openModal="$emit('openModal', $event)"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import ChannelBadge from './ChannelBadge.vue'

defineProps({
  activity: { type: Object, required: true },
  showPersonLink: { type: Boolean, default: false },
  showCompanyLink: { type: Boolean, default: false },
})

defineEmits(['openModal'])

const TimelineCell = {
  props: ['activity', 'showPersonLink', 'showCompanyLink', 'side'],
  emits: ['openModal'],
  components: { ChannelBadge },
  computed: {
    d() { return this.activity.display },
    isLeft() { return this.side === 'left' },
    textAlign() { return this.isLeft ? 'text-right' : '' },
    justifyEnd() { return this.isLeft ? 'justify-end' : '' },
  },
  methods: {
    openModal(url) { this.$emit('openModal', url) },
    timeAgo(dateStr) {
      if (!dateStr) return ''
      const d = new Date(dateStr)
      const now = new Date()
      const seconds = Math.floor((now - d) / 1000)
      if (seconds < 60) return 'now'
      const minutes = Math.floor(seconds / 60)
      if (minutes < 60) return `${minutes}m`
      const hours = Math.floor(minutes / 60)
      if (hours < 24) return `${hours}h`
      const days = Math.floor(hours / 24)
      if (days < 30) return `${days}d`
      const months = Math.floor(days / 30)
      if (months < 12) return `${months}mo`
      return `${Math.floor(months / 12)}y`
    },
    fullDate(dateStr) {
      if (!dateStr) return ''
      return new Date(dateStr).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    },
  },
  template: `
    <div class="flex items-center gap-1.5 max-w-full group rounded-lg px-2 py-1 transition-colors min-w-0"
         :class="[d.rowClickable ? 'hover:bg-gray-100 cursor-pointer' : 'hover:bg-gray-50']"
         @click="d.rowClickable && d.modalUrl ? openModal(d.modalUrl) : null">

      <!-- Icon -->
      <span v-if="d.chType" class="shrink-0" :title="d.badgeTitle || undefined">
        <ChannelBadge :type="d.chType" :label="false" />
      </span>
      <span v-else class="hidden md:inline shrink-0 text-xs font-medium px-2 py-0.5 rounded-full border whitespace-nowrap"
            :class="activity.timeline_color">
        {{ activity.timeline_label }}
      </span>

      <!-- Email: 2-line layout -->
      <div v-if="d.isEmail" class="min-w-0 flex-1 flex flex-col gap-px" :class="textAlign">
        <div v-if="d.sourceLabel" class="flex items-center gap-1 min-w-0" :class="justifyEnd">
          <span class="text-[10px] text-gray-400 shrink-0">{{ d.isOutbound ? 'to:' : 'from:' }}</span>
          <a v-if="d.sourcePerson" :href="'/people/' + d.sourcePerson" class="text-[10px] text-brand-600 hover:underline truncate max-w-[120px] md:max-w-[260px]">{{ d.sourceLabel }}</a>
          <span v-else class="text-[10px] text-gray-500 truncate max-w-[120px] md:max-w-[260px]">{{ d.sourceLabel }}</span>
        </div>
        <div v-if="d.counterpartLabel" class="flex items-center gap-1 min-w-0" :class="justifyEnd">
          <span class="text-[10px] text-gray-400 shrink-0">{{ d.isOutbound ? 'from:' : 'to:' }}</span>
          <a v-if="showPersonLink && activity.person" :href="'/people/' + activity.person.id" class="text-[10px] text-brand-600 hover:underline truncate max-w-[120px] md:max-w-[260px]">{{ d.counterpartLabel }}</a>
          <a v-else-if="showCompanyLink && activity.company" :href="'/companies/' + activity.company.id" class="text-[10px] text-brand-600 hover:underline truncate max-w-[120px] md:max-w-[260px]">{{ d.counterpartLabel }}</a>
          <span v-else class="text-[10px] text-gray-500 truncate max-w-[120px] md:max-w-[260px]">{{ d.counterpartLabel }}</span>
        </div>
        <div class="flex items-center gap-1.5 min-w-0" :class="justifyEnd">
          <div v-if="isLeft" class="flex-1 min-w-0" />
          <button v-if="d.titleText && d.modalUrl" type="button" @click.stop="openModal(d.modalUrl)"
                  class="text-xs link truncate max-w-[120px] md:max-w-[260px] cursor-pointer" :class="textAlign" :title="d.hoverText">{{ d.titleText }}</button>
          <a v-else-if="d.titleText && d.url" :href="d.url" class="text-xs link truncate max-w-[120px] md:max-w-[260px]" :class="textAlign" :title="d.hoverText">{{ d.titleText }}</a>
          <span v-else-if="d.titleText" class="text-xs text-gray-600 truncate max-w-[120px] md:max-w-[260px]" :title="d.hoverText">{{ d.titleText }}</span>
          <div v-if="!isLeft" class="flex-1 min-w-0" />
          <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap" :title="fullDate(activity.occurred_at)">{{ timeAgo(activity.occurred_at) }}</span>
        </div>
      </div>

      <!-- Slack / Discord: 2-line layout -->
      <div v-else-if="d.isSlack || d.isDiscord" class="min-w-0 flex-1 flex flex-col gap-px" :class="textAlign">
        <div class="flex items-center gap-1.5 min-w-0" :class="justifyEnd">
          <span v-if="d.sourceLabel" class="text-xs text-gray-400 shrink-0 font-mono truncate max-w-[100px] md:max-w-[200px]" :title="d.sourceLabel">{{ d.sourceLabel }}</span>
          <div v-if="!isLeft" class="flex-1 min-w-0" />
          <a v-if="showPersonLink && activity.person" :href="'/people/' + activity.person.id" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[70px] md:max-w-[130px] truncate">{{ activity.person.full_name }}</a>
          <a v-if="showCompanyLink && activity.company" :href="'/companies/' + activity.company.id" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[70px] md:max-w-[130px] truncate">{{ activity.company.name }}</a>
          <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap" :title="fullDate(activity.occurred_at)">{{ timeAgo(activity.occurred_at) }}</span>
        </div>
        <div v-if="d.participantLinks" class="flex items-center flex-wrap gap-x-1 gap-y-0" :class="justifyEnd">
          <template v-for="(part, idx) in d.participantLinks" :key="idx">
            <a v-if="part.person" :href="'/people/' + part.person" class="text-[10px] text-brand-600 hover:underline">{{ part.name }}</a>
            <span v-else class="text-[10px] text-gray-400">{{ part.name }}</span>
            <span v-if="idx < d.participantLinks.length - 1" class="text-[10px] text-gray-300">,</span>
          </template>
        </div>
        <div v-else-if="d.participants" class="text-[10px] text-gray-400 truncate max-w-full" :class="textAlign">{{ d.participants }}</div>
      </div>

      <!-- Ticket (WHMCS): 2-line layout -->
      <div v-else-if="d.isTicket" class="min-w-0 flex-1 flex flex-col gap-px" :class="textAlign">
        <div class="flex items-center gap-1.5 min-w-0" :class="justifyEnd">
          <span v-if="d.ticketNotFound" class="text-xs text-red-400 truncate max-w-[140px] md:max-w-[300px]" :title="d.hoverText">{{ d.titleText || '#' + d.ticketNotFound }}</span>
          <a v-else-if="d.titleText && d.url" :href="d.url" class="text-xs link truncate max-w-[140px] md:max-w-[300px]" :class="textAlign" :title="d.hoverText">{{ d.titleText }}</a>
          <span v-else-if="d.titleText" class="text-xs text-gray-600 truncate max-w-[140px] md:max-w-[300px]" :title="d.hoverText">{{ d.titleText }}</span>
          <div v-if="!isLeft" class="flex-1 min-w-0" />
          <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap" :title="fullDate(activity.occurred_at)">{{ timeAgo(activity.occurred_at) }}</span>
        </div>
        <div class="flex items-center gap-1 flex-wrap" :class="justifyEnd">
          <span v-if="d.department" class="text-[10px] text-gray-400 truncate max-w-full">{{ d.department }}</span>
          <a v-if="activity.person" :href="'/people/' + activity.person.id" class="text-[10px] truncate max-w-[140px]" :class="activity.person.is_our_org ? 'text-brand-600 hover:underline' : 'text-gray-500 hover:underline'">{{ activity.person.full_name }}</a>
        </div>
      </div>

      <!-- MetricsCube: 2-line layout -->
      <div v-else-if="d.isMetricscube" class="min-w-0 flex-1 flex flex-col gap-px" :class="textAlign">
        <div class="flex items-center gap-1.5 min-w-0" :class="justifyEnd">
          <span v-if="d.ticketNotFound" class="text-xs text-red-400 truncate max-w-[140px] md:max-w-[300px]" :title="d.hoverText">{{ d.titleText || '#' + d.ticketNotFound }}</span>
          <a v-else-if="d.titleText && d.url" :href="d.url" class="text-xs link truncate max-w-[140px] md:max-w-[300px]" :class="textAlign" :title="d.hoverText">{{ d.titleText }}</a>
          <span v-else-if="d.titleText" class="text-xs text-gray-600 truncate max-w-[140px] md:max-w-[300px]" :title="d.hoverText">{{ d.titleText }}</span>
          <div v-if="!isLeft" class="flex-1 min-w-0" />
          <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap" :title="fullDate(activity.occurred_at)">{{ timeAgo(activity.occurred_at) }}</span>
        </div>
        <div class="flex items-center gap-1 flex-wrap" :class="justifyEnd">
          <span v-if="d.mcType" class="text-[10px] text-gray-400 truncate max-w-full">{{ d.mcType }}</span>
          <a v-if="activity.person" :href="'/people/' + activity.person.id" class="text-[10px] truncate max-w-[140px]" :class="activity.person.is_our_org ? 'text-brand-600 hover:underline' : 'text-gray-500 hover:underline'">{{ activity.person.full_name }}</a>
        </div>
      </div>

      <!-- Other: single-line layout -->
      <template v-else>
        <span v-if="d.sourceLabel" class="text-xs text-gray-400 shrink-0 font-mono truncate max-w-[100px] md:max-w-[200px]" :title="d.sourceLabel">{{ d.sourceLabel }}</span>
        <button v-if="d.titleText && d.modalUrl && !d.rowClickable" type="button" @click.stop="openModal(d.modalUrl)"
                class="text-xs link truncate max-w-[140px] md:max-w-[300px] cursor-pointer" :class="textAlign" :title="d.hoverText">{{ d.titleText }}</button>
        <span v-else-if="d.titleText && d.ticketNotFound" class="text-xs text-red-500 truncate max-w-[140px] md:max-w-[300px]" :title="d.hoverText">{{ d.titleText }}</span>
        <a v-else-if="d.titleText && d.url && !d.modalUrl" :href="d.url" class="text-xs link truncate max-w-[140px] md:max-w-[300px]" :class="textAlign" :title="d.hoverText">{{ d.titleText }}</a>
        <span v-else-if="d.titleText" class="text-xs text-gray-600 truncate max-w-[140px] md:max-w-[300px]" :title="d.hoverText">{{ d.titleText }}</span>
        <span v-else-if="d.ticketNotFound" class="text-xs text-red-400 font-mono truncate max-w-[140px] md:max-w-[300px]" :title="d.hoverText">#{{ d.ticketNotFound }}</span>
        <div class="flex-1 min-w-0" />
        <a v-if="showPersonLink && activity.person" :href="'/people/' + activity.person.id" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] md:max-w-[140px] truncate">{{ activity.person.full_name }}</a>
        <a v-if="showCompanyLink && activity.company" :href="'/companies/' + activity.company.id" class="text-xs text-brand-600 hover:underline shrink-0 max-w-[80px] md:max-w-[140px] truncate">{{ activity.company.name }}</a>
        <span class="text-xs text-gray-400 shrink-0 tabular-nums whitespace-nowrap" :title="fullDate(activity.occurred_at)">{{ timeAgo(activity.occurred_at) }}</span>
        <a v-if="!d.modalUrl && d.url" :href="d.url" class="opacity-20 group-hover:opacity-60 hover:!opacity-100 shrink-0 transition-opacity">
          <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
          </svg>
        </a>
      </template>
    </div>
  `,
}
</script>
