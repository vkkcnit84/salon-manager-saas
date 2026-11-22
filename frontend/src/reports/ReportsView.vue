<template>
  <div class="d-flex flex-wrap align-center ga-3 mb-4">
    <h1 class="text-h5 font-weight-bold">Reports</h1>
    <v-spacer />
    <v-menu>
      <template #activator="{ props }"><v-btn v-bind="props" variant="tonal" prepend-icon="mdi-download" append-icon="mdi-chevron-down" :disabled="!sales">Export</v-btn></template>
      <v-list density="compact">
        <v-list-item v-for="f in ['csv', 'xlsx', 'pdf']" :key="f" :title="f === 'xlsx' ? 'Excel (.xlsx)' : f.toUpperCase()" @click="exportAs(f)" />
      </v-list>
    </v-menu>
    <v-btn variant="tonal" prepend-icon="mdi-printer" @click="print">Print</v-btn>
  </div>

  <v-card class="pa-3 mb-4 no-print">
    <div class="d-flex flex-wrap align-center ga-2">
      <v-chip-group v-model="preset" mandatory selected-class="text-primary" @update:model-value="applyPreset">
        <v-chip v-for="p in presets" :key="p.key" :value="p.key" filter variant="outlined">{{ p.label }}</v-chip>
      </v-chip-group>
      <v-spacer />
      <v-text-field v-model="from" type="date" label="From" density="compact" hide-details style="max-width: 160px" @update:model-value="preset = 'custom'; load()" />
      <v-text-field v-model="to" type="date" label="To" density="compact" hide-details style="max-width: 160px" @update:model-value="preset = 'custom'; load()" />
    </div>
  </v-card>

  <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
  <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-4" />

  <template v-if="sales">
    <v-row>
      <v-col v-for="k in summary" :key="k.label" cols="6" md="3">
        <v-card class="pa-4"><div class="text-body-2 text-medium-emphasis">{{ k.label }}</div><div class="text-h6 font-weight-bold">{{ k.value }}</div></v-card>
      </v-col>
    </v-row>

    <v-tabs v-model="tab" color="primary" class="my-4 no-print" show-arrows>
      <v-tab value="sales">Sales</v-tab><v-tab value="items">Services & products</v-tab><v-tab value="staff">Staff & commission</v-tab>
      <v-tab value="payments">Payments</v-tab><v-tab value="dues">Dues</v-tab>
      <v-tab v-for="t in tableTabs" :key="t.key" :value="t.key" @click="loadTable(t.key)">{{ t.label }}</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="sales">
        <v-card class="pa-5 mb-4"><div class="text-subtitle-1 font-weight-bold mb-2">Daily revenue</div><TrendChart :data="trend" /></v-card>
        <v-card><v-table density="comfortable" hover>
          <thead><tr><th>Date</th><th class="text-right">Bills</th><th class="text-right">Sales</th></tr></thead>
          <tbody>
            <tr v-for="r in dailyDesc" :key="r.date"><td>{{ fmtDate(r.date) }}</td><td class="text-right">{{ r.bills }}</td><td class="text-right">{{ formatPaise(r.sales) }}</td></tr>
          </tbody>
        </v-table></v-card>
      </v-window-item>

      <v-window-item value="items">
        <v-row>
          <v-col cols="12" md="6"><v-card class="pa-5"><div class="text-subtitle-1 font-weight-bold mb-4">Services</div>
            <BarList :items="sales.top_services.map((s) => ({ label: `${s.name} (${s.qty})`, value: s.revenue, display: formatPaise(s.revenue) }))" /></v-card></v-col>
          <v-col cols="12" md="6"><v-card class="pa-5"><div class="text-subtitle-1 font-weight-bold mb-4">Products</div>
            <BarList color="#ec4899" :items="sales.top_products.map((s) => ({ label: `${s.name} (${s.qty})`, value: s.revenue, display: formatPaise(s.revenue) }))" /></v-card></v-col>
        </v-row>
      </v-window-item>

      <v-window-item value="staff">
        <v-card><v-table hover>
          <thead><tr><th>Staff</th><th class="text-right">Services</th><th class="text-right">Revenue</th><th class="text-right">Commission</th></tr></thead>
          <tbody>
            <tr v-for="s in staff" :key="s.staff_id"><td>{{ s.name }}</td><td class="text-right">{{ s.services }}</td><td class="text-right">{{ formatPaise(s.revenue) }}</td><td class="text-right font-weight-bold">{{ formatPaise(s.commission) }}</td></tr>
            <tr v-if="!staff.length"><td colspan="4" class="text-center pa-6 text-medium-emphasis">No staff-attributed sales in this period.</td></tr>
          </tbody>
          <tfoot v-if="staff.length"><tr><td class="font-weight-bold">Total</td><td class="text-right">{{ staff.reduce((a, s) => a + s.services, 0) }}</td><td class="text-right">{{ formatPaise(staff.reduce((a, s) => a + s.revenue, 0)) }}</td><td class="text-right font-weight-bold">{{ formatPaise(staff.reduce((a, s) => a + s.commission, 0)) }}</td></tr></tfoot>
        </v-table></v-card>
      </v-window-item>

      <v-window-item value="payments">
        <v-card class="pa-5"><DonutChart :items="sales.payment_methods.map((m) => ({ label: m.method.toUpperCase(), value: m.amount }))" :centre="formatPaise(sales.totals.collected)" />
          <v-table class="mt-4"><thead><tr><th>Method</th><th class="text-right">Payments</th><th class="text-right">Amount</th></tr></thead>
            <tbody><tr v-for="m in sales.payment_methods" :key="m.method"><td>{{ m.method.toUpperCase() }}</td><td class="text-right">{{ m.count }}</td><td class="text-right">{{ formatPaise(m.amount) }}</td></tr></tbody></v-table></v-card>
      </v-window-item>

      <v-window-item value="dues">
        <v-card><v-table hover>
          <thead><tr><th>Bill</th><th>Date</th><th>Customer</th><th class="text-right">Total</th><th class="text-right">Due</th></tr></thead>
          <tbody>
            <tr v-for="r in dues" :key="r.number"><td>{{ r.number }}</td><td>{{ fmtDate(r.date) }}</td><td>{{ r.customer }}<div class="text-caption text-medium-emphasis">{{ r.mobile }}</div></td><td class="text-right">{{ formatPaise(r.total) }}</td><td class="text-right text-error font-weight-bold">{{ formatPaise(r.due) }}</td></tr>
            <tr v-if="!dues.length"><td colspan="5" class="text-center pa-6 text-medium-emphasis">🎉 No pending dues.</td></tr>
          </tbody>
        </v-table></v-card>
      </v-window-item>
      <v-window-item v-for="t in tableTabs" :key="t.key" :value="t.key">
        <v-progress-linear v-if="tableLoading" indeterminate color="primary" class="mb-2" />
        <template v-if="tables[t.key]">
          <v-row v-if="Object.keys(tables[t.key].summary ?? {}).length" class="mb-1">
            <v-col v-for="(v, k) in tables[t.key].summary" :key="k" cols="6" md="3"><v-card class="pa-3"><div class="text-caption text-medium-emphasis">{{ k }}</div><div class="text-subtitle-1 font-weight-bold">{{ fmtSummary(String(k), v) }}</div></v-card></v-col>
          </v-row>
          <v-card><v-table hover density="comfortable">
            <thead><tr><th v-for="(c, i) in tables[t.key].columns" :key="c" :class="tables[t.key].money.includes(i) ? 'text-right' : ''">{{ c }}</th></tr></thead>
            <tbody>
              <tr v-for="(r, ri) in tables[t.key].rows" :key="ri" :class="t.key === 'pnl' && ri === 4 ? 'font-weight-bold' : ''"><td v-for="(v, i) in r" :key="i" :class="tables[t.key].money.includes(i) ? 'text-right' : ''">{{ tables[t.key].money.includes(i) && typeof v === 'number' ? formatPaise(v) : v }}</td></tr>
              <tr v-if="!tables[t.key].rows.length"><td :colspan="tables[t.key].columns.length" class="text-center pa-6 text-medium-emphasis">Nothing to show for this period.</td></tr>
            </tbody>
          </v-table></v-card>
        </template>
      </v-window-item>
    </v-window>
  </template>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useShopStore } from '../stores/shop'
import { useRoute } from 'vue-router'
import { reportsApi, type DueRow, type SalesReport, type StaffRow } from './api'
import { formatPaise } from '../shared/money'
import { ApiError, api, download } from '../shared/api/client'
import TrendChart from '../shared/charts/TrendChart.vue'
import DonutChart from '../shared/charts/DonutChart.vue'
import BarList from '../shared/charts/BarList.vue'

const route = useRoute()
const iso = (d: Date) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10)
const daysAgo = (n: number) => { const d = new Date(); d.setDate(d.getDate() - n); return iso(d) }
const presets = [
  { key: 'today', label: 'Today' }, { key: '7', label: 'Last 7 days' }, { key: '30', label: 'Last 30 days' }, { key: 'month', label: 'This month' }, { key: 'custom', label: 'Custom' },
]
const preset = ref('30')
const from = ref(daysAgo(29))
const to = ref(iso(new Date()))
const tab = ref((route.query.tab as string) || 'sales')
const sales = ref<SalesReport | null>(null)
const staff = ref<StaffRow[]>([])
const dues = ref<DueRow[]>([])
const loading = ref(false)
const error = ref('')

function applyPreset(k: string) {
  to.value = iso(new Date())
  if (k === 'today') from.value = to.value
  else if (k === '7') from.value = daysAgo(6)
  else if (k === '30') from.value = daysAgo(29)
  else if (k === 'month') from.value = to.value.slice(0, 8) + '01'
  if (k !== 'custom') load()
}

async function load() {
  if (!from.value || !to.value) return
  loading.value = true
  error.value = ''
  try {
    ;[sales.value, staff.value, dues.value] = await Promise.all([reportsApi.sales(from.value, to.value), reportsApi.staff(from.value, to.value), reportsApi.dues()])
    if (tableTabs.some((t) => t.key === tab.value)) await loadTable(tab.value)
    else tables.value = {}
  } catch (e) {
    error.value = (e as ApiError).message
  } finally {
    loading.value = false
  }
}
onMounted(load)
watch(() => useShopStore().shopId, load)

const summary = computed(() => {
  const t = sales.value!.totals
  return [
    { label: 'Total sales', value: formatPaise(t.sales) }, { label: 'Bills', value: String(t.bills) },
    { label: 'Average bill', value: formatPaise(t.avg_bill) }, { label: 'Unique customers', value: String(t.customers) },
    { label: 'GST collected', value: formatPaise(t.tax) }, { label: 'Discounts given', value: formatPaise(t.discount) },
    { label: 'Tips', value: formatPaise(t.tips) }, { label: 'Outstanding dues', value: formatPaise(t.due) },
  ]
})
const fmtDate = (s: string) => new Date(s + 'T00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
const trend = computed(() => sales.value!.daily.map((x) => ({ label: fmtDate(x.date), value: x.sales, sub: `${x.bills} bills` })))
const dailyDesc = computed(() => [...sales.value!.daily].reverse().filter((r) => r.bills > 0))

const tableTabs = [
  { key: 'pnl', label: 'Profit & loss' }, { key: 'customers', label: 'Customers' }, { key: 'discounts', label: 'Discounts' },
  { key: 'gst', label: 'GST' }, { key: 'expenses', label: 'Expenses' }, { key: 'campaigns', label: 'Campaign ROI' },
]
type Table = { title: string; columns: string[]; rows: (string | number)[][]; money: number[]; summary: Record<string, number> }
const tables = ref<Record<string, Table>>({})
const tableLoading = ref(false)
async function loadTable(key: string) {
  tableLoading.value = true
  try { tables.value[key] = await api<Table>(`/reports/table/${key}?from=${from.value}&to=${to.value}`) }
  catch (e) { error.value = (e as ApiError).message } finally { tableLoading.value = false }
}
const fmtSummary = (k: string, v: number) => (/profit|sales|commission|GST|discount|expenses|due/i.test(k) && !/%|count/i.test(k) ? formatPaise(Number(v)) : String(v))
const exportKey = computed(() => ({ items: 'services', payments: 'payments' } as Record<string, string>)[tab.value] ?? tab.value)
async function exportAs(format: string) {
  error.value = ''
  try { await download(`/reports/${exportKey.value}/export?format=${format}&from=${from.value}&to=${to.value}`, `salon-${exportKey.value}-${from.value}_${to.value}.${format}`) }
  catch (e) { error.value = (e as ApiError).message }
}
const print = () => window.print()
</script>

<style>
@media print { .no-print, .v-navigation-drawer, .v-app-bar { display: none !important; } .v-main { padding: 0 !important; } }
</style>
