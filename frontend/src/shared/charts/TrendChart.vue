<template>
  <div class="trend" @mouseleave="hover = null">
    <svg :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none" role="img" :aria-label="label">
      <defs>
        <linearGradient :id="gid" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="rgb(var(--v-theme-primary))" stop-opacity="0.35" />
          <stop offset="100%" stop-color="rgb(var(--v-theme-primary))" stop-opacity="0" />
        </linearGradient>
      </defs>
      <g class="grid">
        <line v-for="g in 4" :key="g" x1="0" :x2="W" :y1="pad + ((H - 2 * pad) / 4) * (g - 1)" :y2="pad + ((H - 2 * pad) / 4) * (g - 1)" />
      </g>
      <path :d="area" :fill="`url(#${gid})`" />
      <path :d="line" fill="none" stroke="rgb(var(--v-theme-primary))" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
      <rect v-for="(p, i) in pts" :key="i" :x="p.x - step / 2" y="0" :width="step" :height="H" fill="transparent" @mouseenter="hover = i" />
      <g v-if="hover !== null">
        <line :x1="pts[hover].x" :x2="pts[hover].x" y1="0" :y2="H" stroke="rgb(var(--v-theme-primary))" stroke-opacity="0.3" vector-effect="non-scaling-stroke" />
        <circle :cx="pts[hover].x" :cy="pts[hover].y" r="4" fill="rgb(var(--v-theme-primary))" />
      </g>
    </svg>
    <div v-if="hover !== null" class="tip" :style="{ left: `${(pts[hover].x / W) * 100}%` }">
      <div class="font-weight-bold">{{ formatPaise(data[hover].value) }}</div>
      <div class="text-caption">{{ data[hover].label }}<span v-if="data[hover].sub"> · {{ data[hover].sub }}</span></div>
    </div>
    <div class="axis"><span>{{ data[0]?.label }}</span><span>{{ data[data.length - 1]?.label }}</span></div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { formatPaise } from '../money'

const props = defineProps<{ data: { label: string; value: number; sub?: string }[]; label?: string }>()
const W = 600
const H = 180
const pad = 12
const hover = ref<number | null>(null)
const gid = `g${Math.random().toString(36).slice(2, 8)}`

const max = computed(() => Math.max(1, ...props.data.map((d) => d.value)))
const step = computed(() => W / Math.max(1, props.data.length - 1))
const pts = computed(() => props.data.map((d, i) => ({ x: i * step.value, y: H - pad - (d.value / max.value) * (H - 2 * pad) })))
const line = computed(() => pts.value.map((p, i) => `${i ? 'L' : 'M'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' '))
const area = computed(() => (pts.value.length ? `${line.value} L${W},${H} L0,${H} Z` : ''))
</script>

<style scoped>
.trend { position: relative; }
svg { width: 100%; height: 200px; display: block; }
.grid line { stroke: rgba(var(--v-border-color), 0.12); stroke-width: 1; vector-effect: non-scaling-stroke; }
.axis { display: flex; justify-content: space-between; font-size: 12px; opacity: 0.6; margin-top: 4px; }
.tip { position: absolute; top: 0; transform: translateX(-50%); background: rgb(var(--v-theme-surface)); border: 1px solid rgba(var(--v-border-color), 0.2); border-radius: 8px; padding: 6px 10px; box-shadow: 0 4px 16px rgba(0,0,0,.12); pointer-events: none; white-space: nowrap; }
</style>
