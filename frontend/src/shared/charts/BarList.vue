<template>
  <div>
    <div v-for="(r, i) in rows" :key="r.label" class="mb-3">
      <div class="d-flex text-body-2 mb-1">
        <span class="text-truncate">{{ i + 1 }}. {{ r.label }}</span>
        <span class="ml-auto pl-2 font-weight-bold">{{ r.display }}</span>
      </div>
      <div class="track"><div class="fill" :style="{ width: `${r.pct}%`, background: color }" /></div>
    </div>
    <div v-if="!rows.length" class="text-medium-emphasis text-body-2">No data in this period.</div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{ items: { label: string; value: number; display: string }[]; color?: string }>(), { color: '#7c3aed' })
const rows = computed(() => {
  const max = Math.max(1, ...props.items.map((i) => i.value))
  return props.items.map((i) => ({ ...i, pct: Math.max(3, (i.value / max) * 100) }))
})
</script>

<style scoped>
.track { height: 8px; border-radius: 99px; background: rgba(var(--v-border-color), 0.12); overflow: hidden; }
.fill { height: 100%; border-radius: 99px; transition: width .4s; }
</style>
