<template>
  <h1 class="text-h5 font-weight-bold mb-1">Expenses</h1>
  <div class="text-body-2 text-medium-emphasis mb-4">Track rent, salaries and supplies so the profit & loss report is accurate</div>
  <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>
  <v-card class="pa-4 mb-4">
    <v-row dense>
      <v-col cols="12" sm="3"><v-select v-model="f.shop_id" :items="shops.shops" item-title="name" item-value="id" label="Shop" hide-details /></v-col>
      <v-col cols="6" sm="3"><v-select v-model="f.category" :items="categories" label="Category" hide-details /></v-col>
      <v-col cols="6" sm="2"><v-text-field v-model.number="f.amount" label="Amount ₹" type="number" min="1" hide-details /></v-col>
      <v-col cols="6" sm="2"><v-text-field v-model="f.spent_on" label="Date" type="date" :max="today" hide-details /></v-col>
      <v-col cols="6" sm="2"><v-btn block color="primary" height="48" :disabled="!f.shop_id || !f.amount" @click="add">Add</v-btn></v-col>
    </v-row>
  </v-card>
  <v-row class="mb-2">
    <v-col v-for="(v, k) in byCategory" :key="k" cols="6" md="3"><v-card class="pa-3"><div class="text-caption text-medium-emphasis">{{ k }}</div><div class="font-weight-bold">{{ formatPaise(v) }}</div></v-card></v-col>
  </v-row>
  <v-card><v-table hover>
    <thead><tr><th>Date</th><th>Category</th><th>Shop</th><th class="text-right">Amount</th><th /></tr></thead>
    <tbody>
      <tr v-for="e in rows" :key="e.id"><td>{{ e.spent_on.slice(0, 10) }}</td><td>{{ e.category }}</td><td>{{ shops.shops.find((s) => s.id === e.shop_id)?.name }}</td><td class="text-right">{{ formatPaise(e.amount_paise) }}</td>
        <td class="text-right"><v-btn icon="mdi-delete-outline" size="small" variant="text" @click="remove(e.id)" /></td></tr>
      <tr v-if="!rows.length"><td colspan="5" class="text-center pa-6 text-medium-emphasis">No expenses recorded yet.</td></tr>
    </tbody>
  </v-table></v-card>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { expensesApi, type Expense } from '../marketing/api'
import { useShopsStore } from '../stores/shops'
import { useShopStore } from '../stores/shop'
import { formatPaise } from '../shared/money'
import { ApiError } from '../shared/api/client'

const shops = useShopsStore(); const ctx = useShopStore()
const rows = ref<Expense[]>([]); const categories = ref<string[]>([]); const error = ref('')
const today = new Date().toISOString().slice(0, 10)
const f = reactive({ shop_id: ctx.shopId ?? shops.shops[0]?.id ?? null, category: 'Rent', amount: null as number | null, spent_on: today })
const byCategory = computed(() => rows.value.filter((e) => e.spent_on.slice(0, 7) === today.slice(0, 7)).reduce<Record<string, number>>((a, e) => ({ ...a, [e.category]: (a[e.category] ?? 0) + e.amount_paise }), {}))
const load = async () => { const r = await expensesApi.list(); rows.value = r.data; categories.value = r.categories }
onMounted(() => load().catch((e) => (error.value = (e as ApiError).message)))
async function add() {
  error.value = ''
  try { await expensesApi.create({ shop_id: f.shop_id, category: f.category, amount_paise: Math.round(f.amount! * 100), spent_on: f.spent_on }); f.amount = null; await load() }
  catch (e) { const x = e as ApiError; error.value = Object.values(x.errors ?? {})[0]?.[0] ?? x.message }
}
async function remove(id: number) { await expensesApi.remove(id); await load() }
</script>
