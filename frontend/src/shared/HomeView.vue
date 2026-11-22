<template>
  <div>
    <!-- Hero -->
    <v-card class="hero pa-6 mb-6" border="0" elevation="0">
      <div class="d-flex flex-wrap align-center ga-4">
        <div>
          <div class="text-body-2 hero-sub">{{ greeting }}, {{ firstName }} 👋</div>
          <div class="text-h5 font-weight-bold">{{ auth.me?.tenant?.business_name }}</div>
          <div v-if="d" class="mt-4">
            <div class="text-caption hero-sub">TODAY'S SALES</div>
            <div class="text-h3 font-weight-bold">{{ formatPaise(d.today.sales) }}</div>
            <v-chip size="small" class="mt-2" :color="deltaToday >= 0 ? 'success' : 'error'" variant="flat">
              <v-icon start :icon="deltaToday >= 0 ? 'mdi-trending-up' : 'mdi-trending-down'" />
              {{ deltaLabel(deltaToday) }} vs yesterday
            </v-chip>
          </div>
        </div>
        <v-spacer />
        <div class="d-flex flex-column ga-2">
          <v-btn to="/pos" size="large" color="white" class="text-primary" prepend-icon="mdi-receipt-text-plus">New bill</v-btn>
          <v-btn variant="outlined" color="white" prepend-icon="mdi-whatsapp" :loading="sharing" @click="shareSummary">Send this on WhatsApp</v-btn>
          <v-btn to="/reports" variant="text" color="white" prepend-icon="mdi-chart-box-outline">View reports</v-btn>
        </div>
      </div>
    </v-card>

    <v-alert v-if="error" type="info" variant="tonal" class="mb-6">{{ error }}</v-alert>
    <div v-if="d?.alerts.length" class="mb-6 d-flex flex-column ga-2">
      <v-alert v-for="a in d.alerts" :key="a.text" :type="a.level" variant="tonal" density="comfortable">
        {{ a.text }} <router-link :to="a.link" class="ml-2">Open</router-link>
      </v-alert>
    </div>
    <v-snackbar v-model="toast" timeout="3500" color="success">{{ toastText }}</v-snackbar>
    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-6" />

    <template v-if="d">
      <!-- KPIs -->
      <v-row>
        <v-col v-for="k in kpis" :key="k.label" cols="6" md="3">
          <v-card class="pa-4 kpi h-100">
            <div class="d-flex align-center mb-2">
              <v-avatar :color="k.color" variant="tonal" rounded="lg" size="40"><v-icon :icon="k.icon" /></v-avatar>
              <v-spacer />
              <v-chip v-if="k.delta !== null" size="x-small" :color="k.delta >= 0 ? 'success' : 'error'" variant="tonal">{{ deltaLabel(k.delta) }}</v-chip>
            </div>
            <div class="text-h5 font-weight-bold">{{ k.value }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ k.label }}</div>
          </v-card>
        </v-col>
      </v-row>

      <v-row class="mt-1">
        <v-col cols="12" lg="8">
          <v-card class="pa-5 h-100">
            <div class="d-flex align-center mb-1">
              <div>
                <div class="text-subtitle-1 font-weight-bold">Revenue — last 30 days</div>
                <div class="text-body-2 text-medium-emphasis">{{ formatPaise(d.last30.totals.sales) }} from {{ d.last30.totals.bills }} bills</div>
              </div>
              <v-spacer />
              <v-chip :color="growth >= 0 ? 'success' : 'error'" variant="tonal"><v-icon start :icon="growth >= 0 ? 'mdi-arrow-up' : 'mdi-arrow-down'" />{{ deltaLabel(growth) }} vs previous 30d</v-chip>
            </div>
            <TrendChart :data="trend" label="Daily revenue" />
          </v-card>
        </v-col>
        <v-col cols="12" lg="4">
          <v-card class="pa-5 h-100">
            <div class="text-subtitle-1 font-weight-bold mb-3">How customers pay</div>
            <DonutChart :items="methods" :centre="`${d.last30.totals.bills} bills`" />
            <v-divider class="my-4" />
            <div class="d-flex align-center">
              <v-icon icon="mdi-cash-clock" color="warning" class="mr-2" />
              <div>
                <div class="text-body-2">Pending dues</div>
                <div class="font-weight-bold">{{ formatPaise(d.dues_total) }}</div>
              </div>
              <v-spacer /><v-btn size="small" variant="text" to="/reports?tab=dues">Collect</v-btn>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <v-row v-if="d.shop_comparison.length > 1">
        <v-col cols="12" lg="5">
          <v-card class="pa-5 h-100">
            <div class="text-subtitle-1 font-weight-bold mb-4">Shop comparison — last 30 days</div>
            <BarList color="#ec4899" :items="d.shop_comparison.map((s) => ({ label: s.name, value: s.sales, display: formatPaise(s.sales) }))" />
          </v-card>
        </v-col>
        <v-col cols="12" lg="7"><AssistantPanel /></v-col>
      </v-row>
      <v-row v-else><v-col cols="12"><AssistantPanel /></v-col></v-row>

      <v-row>
        <v-col cols="12" md="6" lg="4">
          <v-card class="pa-5 h-100">
            <div class="text-subtitle-1 font-weight-bold mb-4">Top services</div>
            <BarList :items="d.top_services.map((s) => ({ label: s.name, value: s.revenue, display: formatPaise(s.revenue) }))" />
          </v-card>
        </v-col>
        <v-col cols="12" md="6" lg="4">
          <v-card class="pa-5 h-100">
            <div class="text-subtitle-1 font-weight-bold mb-4">Staff leaderboard 🏆</div>
            <v-list lines="two" class="pa-0 bg-transparent">
              <v-list-item v-for="(s, i) in d.staff" :key="s.staff_id" class="px-0" :title="s.name" :subtitle="`${s.services} services · earned ${formatPaise(s.commission)}`">
                <template #prepend><v-avatar :color="['warning', 'secondary', 'info', 'primary', 'primary'][i]" variant="flat" size="36" class="mr-3">{{ i + 1 }}</v-avatar></template>
                <template #append><span class="font-weight-bold">{{ formatPaise(s.revenue) }}</span></template>
              </v-list-item>
            </v-list>
            <div v-if="!d.staff.length" class="text-medium-emphasis text-body-2">Assign staff on bills to see rankings.</div>
          </v-card>
        </v-col>
        <v-col cols="12" lg="4">
          <v-card class="pa-5 h-100">
            <div class="d-flex align-center mb-2"><div class="text-subtitle-1 font-weight-bold">Recent bills</div><v-spacer /><v-btn size="small" variant="text" to="/pos">New</v-btn></div>
            <v-list class="pa-0 bg-transparent" density="compact">
              <v-list-item v-for="b in d.recent" :key="b.number" class="px-0" :title="b.customer" :subtitle="b.number">
                <template #append>
                  <div class="text-right"><div class="font-weight-bold">{{ formatPaise(b.total) }}</div>
                    <v-chip size="x-small" :color="b.status === 'paid' ? 'success' : 'warning'" variant="tonal">{{ b.status }}</v-chip></div>
                </template>
              </v-list-item>
            </v-list>
            <div v-if="!d.recent.length" class="text-medium-emphasis text-body-2">No bills yet.</div>
          </v-card>
        </v-col>
      </v-row>
    </template>

    <v-row v-if="!d && !loading" class="mt-2">
      <v-col v-for="a in actions" :key="a.title" cols="12" sm="6" md="4">
        <v-card :to="a.to" class="pa-4 d-flex align-center ga-4" hover>
          <v-avatar :color="a.color" size="48"><v-icon :icon="a.icon" /></v-avatar>
          <div><div class="font-weight-bold">{{ a.title }}</div><div class="text-body-2 text-medium-emphasis">{{ a.desc }}</div></div>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useShopStore } from '../stores/shop'
import { reportsApi, type Dashboard } from '../reports/api'
import { formatPaise } from './money'
import TrendChart from './charts/TrendChart.vue'
import DonutChart from './charts/DonutChart.vue'
import BarList from './charts/BarList.vue'
import AssistantPanel from './AssistantPanel.vue'
import { botApi } from '../marketing/api'

const auth = useAuthStore()
const shop = useShopStore()
const d = ref<Dashboard | null>(null)
const loading = ref(true)
const error = ref('')
const sharing = ref(false)
const toast = ref(false)
const toastText = ref('')
async function shareSummary() {
  sharing.value = true
  try { toastText.value = (await botApi.sendSummary()).message } catch (e) { toastText.value = (e as Error).message }
  toast.value = true; sharing.value = false
}

const hour = new Date().getHours()
const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening'
const firstName = computed(() => (auth.me?.name ?? '').split(' ')[0])

const pct = (a: number, b: number) => (b > 0 ? ((a - b) / b) * 100 : a > 0 ? 100 : 0)
const deltaLabel = (v: number) => `${v >= 0 ? '+' : ''}${v.toFixed(0)}%`
const deltaToday = computed(() => (d.value ? pct(d.value.today.sales, d.value.yesterday.sales) : 0))
const growth = computed(() => (d.value ? pct(d.value.last30.totals.sales, d.value.last30.previous_sales) : 0))

const kpis = computed(() => {
  const x = d.value!
  return [
    { label: 'Bills today', value: String(x.today.bills), icon: 'mdi-receipt-text', color: 'primary', delta: pct(x.today.bills, x.yesterday.bills) },
    { label: 'Sales this month', value: formatPaise(x.month.sales), icon: 'mdi-calendar-month', color: 'secondary', delta: null },
    { label: 'Average bill', value: formatPaise(x.last30.totals.avg_bill), icon: 'mdi-cash-multiple', color: 'info', delta: null },
    { label: 'Unique customers (30d)', value: String(x.last30.totals.customers), icon: 'mdi-account-heart', color: 'success', delta: null },
  ]
})
const trend = computed(() => d.value!.last30.daily.map((x) => ({
  label: new Date(x.date + 'T00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short' }), value: x.sales, sub: `${x.bills} bills`,
})))
const methods = computed(() => d.value!.payment_methods.map((m) => ({ label: m.method.toUpperCase(), value: m.amount })))

const actions = [
  { title: 'New bill', desc: 'Open the billing counter', icon: 'mdi-cash-register', color: 'primary', to: '/pos' },
  { title: 'Customers', desc: 'Find or add a customer', icon: 'mdi-account-heart', color: 'secondary', to: '/customers' },
]

async function load() {
  loading.value = true
  try {
    d.value = await reportsApi.dashboard()
  } catch {
    error.value = 'Business insights are visible to the shop owner.'
  } finally {
    loading.value = false
  }
}
onMounted(load)
watch(() => shop.shopId, load)
</script>

<style scoped>
.hero { background: linear-gradient(120deg, #6d28d9 0%, #a21caf 55%, #ec4899 100%); color: #fff; border-radius: 20px !important; }
.hero-sub { opacity: 0.85; }
.kpi .text-h5 { white-space: nowrap; }
@media (max-width: 600px) { .kpi .text-h5 { font-size: 1.05rem !important; } .hero .text-h3 { font-size: 2rem !important; } }
.kpi { transition: transform .15s, box-shadow .15s; }
.kpi:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(124, 58, 237, 0.12); }
</style>
