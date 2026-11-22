<template>
  <div class="d-flex align-center ga-4 flex-wrap">
    <svg viewBox="0 0 42 42" width="140" height="140" role="img" aria-label="Breakdown">
      <circle cx="21" cy="21" r="15.9155" fill="none" stroke="rgba(var(--v-border-color),0.12)" stroke-width="6" />
      <circle v-for="(s, i) in segs" :key="i" cx="21" cy="21" r="15.9155" fill="none" :stroke="s.color" stroke-width="6"
        :stroke-dasharray="`${s.pct} ${100 - s.pct}`" :stroke-dashoffset="25 - s.start" />
      <text x="21" y="22.5" text-anchor="middle" font-size="5" font-weight="700" fill="currentColor">{{ centre }}</text>
    </svg>
    <div>
      <div v-for="s in segs" :key="s.label" class="d-flex align-center ga-2 mb-1">
        <span class="dot" :style="{ background: s.color }" /><span class="text-body-2">{{ s.label }}</span>
        <span class="text-body-2 font-weight-bold ml-auto pl-3">{{ Math.round(s.pct) }}%</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ items: { label: string; value: number }[]; centre?: string }>()
const colors = ['#7c3aed', '#ec4899', '#0ea5e9', '#16a34a', '#f59e0b']
const segs = computed(() => {
  const total = props.items.reduce((a, b) => a + b.value, 0) || 1
  let acc = 0
  return props.items.map((it, i) => {
    const pct = (it.value / total) * 100
    const s = { label: it.label, pct, start: acc, color: colors[i % colors.length] }
    acc += pct
    return s
  })
})
</script>

<style scoped>
.dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
</style>
