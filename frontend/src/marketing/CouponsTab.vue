<template>
  <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>
  <v-card class="pa-4 mb-4">
    <v-row dense>
      <v-col cols="6" sm="3"><v-text-field v-model="f.code" label="Code" hide-details /></v-col>
      <v-col cols="6" sm="2"><v-select v-model="f.type" :items="[{ title: '% off', value: 'percent' }, { title: '₹ off', value: 'flat' }]" label="Type" hide-details /></v-col>
      <v-col cols="6" sm="2"><v-text-field v-model.number="f.value" :label="f.type === 'percent' ? 'Percent' : 'Rupees'" type="number" min="1" hide-details /></v-col>
      <v-col cols="6" sm="2"><v-text-field v-model.number="f.limit" label="Max uses" type="number" min="1" hide-details /></v-col>
      <v-col cols="12" sm="3"><v-btn block color="primary" height="48" :disabled="!f.code || !f.value" @click="add">Create coupon</v-btn></v-col>
    </v-row>
  </v-card>
  <v-card><v-table>
    <thead><tr><th>Code</th><th>Discount</th><th>Used</th><th>Active</th></tr></thead>
    <tbody>
      <tr v-for="c in coupons" :key="c.id"><td><b>{{ c.code }}</b></td><td>{{ c.type === 'percent' ? c.value / 100 + '%' : formatPaise(c.value) }}</td><td>{{ c.used }}{{ c.usage_limit ? ' / ' + c.usage_limit : '' }}</td>
        <td><v-switch :model-value="c.active" color="primary" hide-details density="compact" @update:model-value="(v: boolean | null) => toggle(c, !!v)" /></td></tr>
      <tr v-if="!coupons.length"><td colspan="4" class="text-center pa-6 text-medium-emphasis">No coupons yet. Customers can use a code at the billing counter.</td></tr>
    </tbody>
  </v-table></v-card>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { marketingApi, type Coupon } from './api'
import { formatPaise } from '../shared/money'
import { ApiError } from '../shared/api/client'

const coupons = ref<Coupon[]>([]); const error = ref('')
const f = reactive({ code: '', type: 'percent', value: 10, limit: null as number | null })
const load = async () => (coupons.value = await marketingApi.coupons())
onMounted(() => load().catch((e) => (error.value = (e as ApiError).message)))
async function add() {
  error.value = ''
  try {
    await marketingApi.createCoupon({ code: f.code, type: f.type, value: f.type === 'percent' ? Math.round(f.value * 100) : Math.round(f.value * 100), usage_limit: f.limit || null })
    f.code = ''; await load()
  } catch (e) { const x = e as ApiError; error.value = Object.values(x.errors ?? {})[0]?.[0] ?? x.message }
}
async function toggle(c: Coupon, active: boolean) { await marketingApi.toggleCoupon(c.id, active); await load() }
</script>
