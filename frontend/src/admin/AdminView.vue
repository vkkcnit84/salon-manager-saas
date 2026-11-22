<template>
  <v-card class="hero pa-6 mb-6" border="0" elevation="0">
    <div class="text-body-2 hero-sub">Platform console</div>
    <div class="text-h5 font-weight-bold mb-4">All salons on the platform</div>
    <v-row v-if="o">
      <v-col v-for="k in kpis" :key="k.label" cols="6" md="3"><div class="text-caption hero-sub">{{ k.label }}</div><div class="text-h5 font-weight-bold">{{ k.value }}</div></v-col>
    </v-row>
  </v-card>

  <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
  <v-text-field v-model="q" prepend-inner-icon="mdi-magnify" placeholder="Search salon or owner" clearable hide-details class="mb-3" />

  <v-card>
    <v-table hover>
      <thead><tr><th>Salon</th><th>Owner</th><th>Plan</th><th>Status</th><th class="text-right">Shops</th><th class="text-right">Users</th><th class="text-right">Bills</th><th class="text-right">Sales</th></tr></thead>
      <tbody>
        <tr v-for="t in filtered" :key="t.id">
          <td><div class="font-weight-medium">{{ t.business_name }}</div><div class="text-caption text-medium-emphasis">Joined {{ new Date(t.created_at).toLocaleDateString('en-IN') }}</div></td>
          <td>{{ t.owner?.name }}<div class="text-caption text-medium-emphasis">{{ t.owner?.email ?? t.owner?.mobile }}</div></td>
          <td style="min-width: 140px">
            <v-select :model-value="t.plan?.id" :items="plans" item-title="name" item-value="id" density="compact" hide-details variant="underlined" @update:model-value="(v: number) => save(t, { plan_id: v })" />
          </td>
          <td>
            <v-menu>
              <template #activator="{ props }"><v-chip v-bind="props" :color="color[t.status]" size="small" append-icon="mdi-chevron-down" style="cursor: pointer">{{ t.status }}</v-chip></template>
              <v-list density="compact"><v-list-item v-for="s in ['trial', 'active', 'suspended']" :key="s" :title="s" @click="save(t, { status: s })" /></v-list>
            </v-menu>
          </td>
          <td class="text-right">{{ t.shops }}</td><td class="text-right">{{ t.users }}</td><td class="text-right">{{ t.invoices }}</td><td class="text-right font-weight-bold">{{ formatPaise(t.sales_paise) }}</td>
        </tr>
        <tr v-if="!filtered.length"><td colspan="8" class="text-center pa-6 text-medium-emphasis">No salons found.</td></tr>
      </tbody>
    </v-table>
  </v-card>
  <v-snackbar v-model="toast" timeout="2000" color="success">Saved</v-snackbar>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { adminApi, type Overview, type PlanRow, type TenantRow } from './api'
import { formatPaise } from '../shared/money'
import { ApiError } from '../shared/api/client'

const o = ref<Overview | null>(null)
const tenants = ref<TenantRow[]>([])
const plans = ref<PlanRow[]>([])
const q = ref('')
const error = ref('')
const toast = ref(false)
const color = { trial: 'info', active: 'success', suspended: 'error' } as const

const kpis = computed(() => [
  { label: 'SALONS', value: `${o.value!.tenants} (${o.value!.active} paying)` },
  { label: 'MONTHLY RECURRING', value: formatPaise(o.value!.mrr_paise) },
  { label: 'TOTAL BILLED VOLUME', value: formatPaise(o.value!.gmv_paise) },
  { label: 'SHOPS · USERS', value: `${o.value!.shops} · ${o.value!.users}` },
])
const filtered = computed(() => {
  const s = (q.value ?? '').toLowerCase()
  return tenants.value.filter((t) => !s || t.business_name.toLowerCase().includes(s) || (t.owner?.name ?? '').toLowerCase().includes(s))
})

async function load() {
  try { [o.value, tenants.value, plans.value] = await Promise.all([adminApi.overview(), adminApi.tenants(), adminApi.plans()]) }
  catch (e) { error.value = (e as ApiError).message }
}
async function save(t: TenantRow, d: { plan_id?: number; status?: string }) {
  error.value = ''
  try { await adminApi.updateTenant(t.id, d); toast.value = true; await load() }
  catch (e) { error.value = (e as ApiError).message }
}
onMounted(load)
</script>

<style scoped>
.hero { background: linear-gradient(120deg, #1e1b4b 0%, #4c1d95 60%, #7c3aed 100%); color: #fff; border-radius: 20px !important; }
.hero-sub { opacity: 0.8; }
</style>
