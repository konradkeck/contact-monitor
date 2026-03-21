<template>
  <div>
    <div class="timeline-grid" :class="gridClass">
      <TimelineItem
        v-for="activity in allActivities"
        :key="activity.id"
        :activity="activity"
        :showPersonLink="showPersonLink"
        :showCompanyLink="showCompanyLink"
        @openModal="$emit('openModal', $event)"
      />
    </div>

    <!-- Loading sentinel -->
    <div v-if="nextCursor" ref="sentinel" class="h-4" />

    <!-- End marker -->
    <div v-else-if="allActivities.length" class="py-6 flex items-center px-8">
      <div class="flex-1 h-px bg-gray-100" />
    </div>

    <!-- Loading indicator -->
    <div v-if="loading" class="flex justify-center py-4">
      <div class="w-5 h-5 border-2 border-gray-200 border-t-brand-600 rounded-full animate-spin" />
    </div>

    <!-- Empty state -->
    <div v-if="!allActivities.length && !loading" class="py-8 text-center text-gray-400 italic text-sm">
      No activity yet.
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch } from 'vue'
import TimelineItem from './TimelineItem.vue'

const props = defineProps({
  activities: { type: Array, default: () => [] },
  initialCursor: { type: [String, null], default: null },
  timelineUrl: { type: String, required: true },
  showPersonLink: { type: Boolean, default: false },
  showCompanyLink: { type: Boolean, default: false },
  gridClass: { type: String, default: '' },
})

const emit = defineEmits(['openModal'])

const allActivities = ref([...props.activities])
const nextCursor = ref(props.initialCursor)
const loading = ref(false)
const sentinel = ref(null)
let observer = null

async function loadMore() {
  if (loading.value || !nextCursor.value) return
  loading.value = true

  try {
    const url = new URL(props.timelineUrl, window.location.origin)
    url.searchParams.set('cursor', nextCursor.value)
    url.searchParams.set('json', '1')

    const resp = await fetch(url.toString(), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const data = await resp.json()
    allActivities.value.push(...data.items)
    nextCursor.value = data.nextCursor || null
  } catch (e) {
    console.error('Timeline load error:', e)
    nextCursor.value = null
  } finally {
    loading.value = false
    await nextTick()
    observeSentinel()
  }
}

function observeSentinel() {
  if (observer) observer.disconnect()
  if (!sentinel.value || !nextCursor.value) return

  observer = new IntersectionObserver(entries => {
    if (entries[0].isIntersecting) loadMore()
  }, { rootMargin: '200px' })

  observer.observe(sentinel.value)
}

onMounted(() => {
  nextTick(observeSentinel)
})

onUnmounted(() => {
  if (observer) observer.disconnect()
})

watch(() => props.activities, (newVal) => {
  allActivities.value = [...newVal]
  nextCursor.value = props.initialCursor
  nextTick(observeSentinel)
})
</script>
