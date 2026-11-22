<template>
  <v-row class="pos">
    <v-col cols="12" md="7">
      <div class="d-flex align-center mb-3 ga-2">
        <v-btn icon="mdi-arrow-left" variant="text" to="/" aria-label="Back" />
        <h1 class="text-h6">Billing counter</h1>
        <v-spacer />
        <v-chip v-if="!online" color="warning" prepend-icon="mdi-wifi-off" size="small">Offline — bills are saved on this device</v-chip>
        <v-chip v-if="pendingCount" color="info" prepend-icon="mdi-cloud-upload-outline" size="small" @click="flush">{{ pendingCount }} bill{{ pendingCount > 1 ? 's' : '' }} waiting to sync</v-chip>
        <v-btn variant="text" prepend-icon="mdi-history" @click="loadHistory">Recent bills</v-btn>
      </div>
      <v-card v-if="waiting.length" variant="tonal" color="success" class="pa-3 mb-3">
        <div class="text-caption mb-1"><v-icon icon="mdi-qrcode-scan" size="small" /> Checked in by QR — waiting ({{ waiting.length }})</div>
        <v-chip v-for="w in waiting" :key="w.id" class="mr-2 mb-1" closable @click="pickWaiting(w)" @click:close="dropWaiting(w.id)">{{ w.customer.name }}</v-chip>
      </v-card>
      <v-text-field v-model="q" prepend-inner-icon="mdi-magnify" placeholder="Search services & products" clearable hide-details class="mb-3" />
      <v-row dense>
        <v-col v-for="i in filtered" :key="i.type + i.id" cols="6" sm="4">
          <v-card class="pa-3 tile" hover @click="add(i)">
            <div class="font-weight-medium text-truncate">{{ i.name }}</div>
            <div class="text-primary">{{ formatPaise(i.price_paise) }}</div>
            <div class="text-caption text-medium-emphasis">{{ i.type === 'service' ? 'Service' : `Stock ${i.stock}` }}</div>
          </v-card>
        </v-col>
        <v-col v-if="!filtered.length" cols="12"><v-alert variant="tonal" type="info">No items yet. Add services in <router-link to="/catalog">Catalog</router-link>.</v-alert></v-col>
      </v-row>
    </v-col>

    <v-col cols="12" md="5">
      <v-card class="pa-3">
        <v-autocomplete v-model="customer" v-model:search="custSearch" :items="custItems" item-title="label" item-value="id" return-object label="Customer (mobile / name)" clearable hide-details="auto" no-filter @update:search="searchCustomers">
          <template #append-item>
            <v-list-item v-if="/^[6-9]\d{9}$/.test(custSearch)" prepend-icon="mdi-account-plus" :title="`Add ${custSearch} as new customer`" @click="quickAdd" />
          </template>
        </v-autocomplete>

        <v-list density="compact" class="mt-2">
          <v-list-item v-for="(l, n) in cart" :key="n" :title="l.name" :subtitle="formatPaise(l.price_paise) + ' × ' + l.qty">
            <template #append>
              <v-select v-model="l.staff_id" :items="staff" item-title="name" item-value="id" placeholder="Staff" density="compact" hide-details clearable class="staff-pick" />
              <v-btn icon="mdi-minus" size="x-small" variant="text" @click="dec(n)" />
              <v-btn icon="mdi-plus" size="x-small" variant="text" @click="l.qty++" />
            </template>
          </v-list-item>
          <v-list-item v-if="!cart.length" title="Cart is empty" subtitle="Tap an item to add it" />
        </v-list>

        <v-divider class="my-2" />
        <div class="d-flex ga-2">
          <v-select v-model="discType" :items="[{ title: '₹', value: 'flat' }, { title: '%', value: 'percent' }]" density="compact" hide-details style="max-width: 80px" />
          <v-text-field v-model.number="discValue" label="Discount" type="number" min="0" density="compact" hide-details />
          <v-text-field v-model.number="tipRupees" label="Tip ₹" type="number" min="0" density="compact" hide-details />
        </div>

        <div class="d-flex ga-2 mt-2">
          <v-text-field v-model="couponCode" label="Coupon code" density="compact" hide-details :disabled="!!coupon" @keyup.enter="applyCoupon" />
          <v-btn v-if="!coupon" variant="tonal" height="40" :disabled="!couponCode" @click="applyCoupon">Apply</v-btn>
          <v-btn v-else variant="text" height="40" @click="coupon = null; couponCode = ''">Remove</v-btn>
        </div>
        <div v-if="coupon" class="text-caption text-success mt-1">Coupon {{ coupon.code }} applied ({{ coupon.type === 'percent' ? coupon.value / 100 + '%' : formatPaise(coupon.value) }} off). It replaces the manual discount.</div>
        <div class="mt-3 text-body-2">
          <div class="d-flex justify-space-between"><span>Subtotal</span><span>{{ formatPaise(t.subtotal) }}</span></div>
          <div class="d-flex justify-space-between"><span>Discount</span><span>- {{ formatPaise(t.discount) }}</span></div>
          <div class="d-flex justify-space-between"><span>GST</span><span>{{ formatPaise(t.tax) }}</span></div>
          <div v-if="t.tip" class="d-flex justify-space-between"><span>Tip</span><span>{{ formatPaise(t.tip) }}</span></div>
          <div class="d-flex justify-space-between text-h6 mt-1"><span>Total</span><span>{{ formatPaise(t.total) }}</span></div>
        </div>

        <v-btn-toggle v-model="method" mandatory color="primary" class="my-3 w-100" density="comfortable">
          <v-btn value="cash" class="flex-1-1">Cash</v-btn><v-btn value="upi" class="flex-1-1">UPI</v-btn><v-btn value="card" class="flex-1-1">Card</v-btn>
        </v-btn-toggle>
        <v-text-field v-model.number="receivedRupees" label="Amount received ₹ (blank = full)" type="number" min="0" density="compact" hide-details />
        <v-alert v-if="error" type="error" variant="tonal" class="mt-3">{{ error }}</v-alert>
        <v-btn block size="large" color="primary" class="mt-3" :loading="busy" :disabled="!cart.length" @click="checkout">Save & collect {{ formatPaise(paying) }}</v-btn>
        <v-btn v-if="cart.length" block variant="text" class="mt-1" @click="reset">Clear</v-btn>
      </v-card>
    </v-col>
  </v-row>

  <v-dialog v-model="done" max-width="420">
    <v-card class="pa-4 text-center">
      <v-icon icon="mdi-check-circle" color="success" size="56" />
      <div class="text-h6 mt-2">Bill {{ lastInvoice?.number }}</div>
      <div class="text-body-2 text-medium-emphasis mb-3">{{ formatPaise(lastInvoice?.total_paise ?? 0) }} · {{ lastInvoice?.status }}</div>
      <v-alert v-if="savedOffline" type="warning" variant="tonal" density="compact" class="mb-3">Saved on this device. The official bill number is assigned when it syncs.</v-alert>
      <v-btn block prepend-icon="mdi-printer" variant="tonal" class="mb-2" @click="print">Print</v-btn>
      <v-btn block color="primary" @click="done = false">New bill</v-btn>
    </v-card>
  </v-dialog>

  <v-dialog v-model="historyOpen" max-width="560">
    <v-card>
      <v-card-title>Recent bills</v-card-title>
      <v-list>
        <v-list-item v-for="b in history" :key="b.id" :title="b.number" :subtitle="`${b.customer?.name ?? 'Walk-in'} · ${b.status}`">
          <template #append>{{ formatPaise(b.total_paise) }}</template>
        </v-list-item>
        <v-list-item v-if="!history.length" title="No bills yet" />
      </v-list>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { catalogApi, couponsApi, customersApi, invoicesApi, staffApi, type Customer, type Invoice, type Staff } from './api'
import { botApi } from '../marketing/api'
import { enqueue, flush, isOffline, pendingCount } from '../mobile/offlineQueue'
import { previewTotals } from './totals'
import { formatPaise } from '../shared/money'
import { ApiError } from '../shared/api/client'
import { useShopStore } from '../stores/shop'
import { useShopsStore } from '../stores/shops'

type Item = { type: 'service' | 'product'; id: number; name: string; price_paise: number; gst_bps: number; stock?: number }
type Row = Item & { qty: number; staff_id: number | null }

const shopCtx = useShopStore()
const shops = useShopsStore()
const taxMode = computed(() => shops.shops.find((s) => s.id === shopCtx.shopId)?.tax_mode ?? 'exclusive')

const items = ref<Item[]>([])
const staff = ref<Staff[]>([])
const q = ref('')
const cart = ref<Row[]>([])
const customer = ref<(Customer & { label: string }) | null>(null)
const custSearch = ref('')
const custItems = ref<(Customer & { label: string })[]>([])
const discType = ref<'flat' | 'percent'>('flat')
const discValue = ref<number | null>(null)
const tipRupees = ref<number | null>(null)
const method = ref<'cash' | 'upi' | 'card'>('cash')
const receivedRupees = ref<number | null>(null)
const busy = ref(false)
const error = ref('')
const done = ref(false)
const lastInvoice = ref<Invoice | null>(null)
const historyOpen = ref(false)
const history = ref<Invoice[]>([])
let key = crypto.randomUUID()

const filtered = computed(() => items.value.filter((i) => i.name.toLowerCase().includes((q.value ?? '').toLowerCase())))
const coupon = ref<{ code: string; type: 'percent' | 'flat'; value: number } | null>(null)
const couponCode = ref('')
const waiting = ref<{ id: number; customer: Customer }[]>([])
const online = ref(navigator.onLine)
const savedOffline = ref(false)
const discount = computed(() => (coupon.value ? { type: coupon.value.type, value: coupon.value.value } : discValue.value ? { type: discType.value, value: discType.value === 'flat' ? Math.round(discValue.value * 100) : Math.round(discValue.value * 100) } : null))
const t = computed(() => previewTotals(cart.value.map((l) => ({ unit: l.price_paise, qty: l.qty, gst_bps: l.gst_bps })), taxMode.value, discount.value, Math.round((tipRupees.value ?? 0) * 100)))
const paying = computed(() => (receivedRupees.value === null || receivedRupees.value === ('' as unknown) ? t.value.total : Math.min(t.value.total, Math.round(receivedRupees.value * 100))))

onMounted(async () => {
  if (shopCtx.shopId === null && shops.shops.length) shopCtx.setShop(shops.shops[0].id) // billing needs a concrete shop
  const [s, p, st] = await Promise.all([catalogApi.services(), catalogApi.products(), staffApi.list()])
  items.value = [
    ...s.filter((x) => x.active).map((x) => ({ type: 'service' as const, id: x.id, name: x.name, price_paise: x.price_paise, gst_bps: x.gst_bps })),
    ...p.filter((x) => x.active).map((x) => ({ type: 'product' as const, id: x.id, name: x.name, price_paise: x.price_paise, gst_bps: x.gst_bps, stock: x.stock })),
  ]
  staff.value = st.filter((x) => x.active)
  loadWaiting()
  window.addEventListener('online', () => (online.value = true))
  window.addEventListener('offline', () => (online.value = false))
})

async function loadWaiting() { try { waiting.value = await botApi.waiting() } catch { waiting.value = [] } }
let servedId: number | null = null
function pickWaiting(w: { id: number; customer: Customer }) {
  customer.value = { ...w.customer, label: `${w.customer.name} · ${w.customer.mobile}` }
  servedId = w.id
}
async function dropWaiting(id: number) { await botApi.serve(id, 'left'); await loadWaiting() }
async function applyCoupon() {
  error.value = ''
  try { coupon.value = await couponsApi.validate(couponCode.value) } catch (e) { error.value = (e as ApiError).message }
}

function add(i: Item) {
  const ex = cart.value.find((l) => l.type === i.type && l.id === i.id)
  ex ? ex.qty++ : cart.value.push({ ...i, qty: 1, staff_id: null })
}
const dec = (n: number) => (cart.value[n].qty > 1 ? cart.value[n].qty-- : cart.value.splice(n, 1))

async function searchCustomers(v: string) {
  if (!v || v.length < 3 || customer.value) return
  const r = await customersApi.search(v)
  custItems.value = r.data.map((c) => ({ ...c, label: `${c.name} · ${c.mobile}` }))
}
async function quickAdd() {
  const c = await customersApi.create({ name: custSearch.value, mobile: custSearch.value })
  customer.value = { ...c, label: `${c.name} · ${c.mobile}` }
}

function reset() {
  cart.value = []; coupon.value = null; couponCode.value = ''; servedId = null; discValue.value = null; tipRupees.value = null; receivedRupees.value = null; customer.value = null; error.value = ''
  key = crypto.randomUUID()
}

async function checkout() {
  busy.value = true; error.value = ''; savedOffline.value = false
  const payload = {
    customer_id: customer.value?.id ?? null,
    items: cart.value.map((l) => ({ type: l.type, id: l.id, qty: l.qty, staff_id: l.staff_id })),
    discount: coupon.value ? null : discount.value,
    coupon_code: coupon.value?.code ?? null,
    tip: Math.round((tipRupees.value ?? 0) * 100),
    payments: paying.value > 0 ? [{ method: method.value, amount: paying.value }] : [],
    idempotency_key: key,
  }
  try {
    lastInvoice.value = await invoicesApi.create(payload)
    if (servedId) { void botApi.serve(servedId, 'served').then(loadWaiting) }
    done.value = true
    reset()
  } catch (e) {
    if (isOffline(e) && shopCtx.shopId !== null) {
      // No connection: keep the sale safely on this device; it syncs with the same idempotency key.
      await enqueue({ key, shopId: shopCtx.shopId, payload, createdAt: Date.now() })
      lastInvoice.value = { id: 0, number: `PENDING-${key.slice(0, 6).toUpperCase()}`, status: 'queued', total_paise: t.value.total, paid_paise: 0, due_paise: 0, created_at: new Date().toISOString() }
      savedOffline.value = true
      done.value = true
      reset()
    } else {
      const err = e as ApiError
      error.value = Object.values(err.errors ?? {})[0]?.[0] ?? err.message
    }
  } finally {
    busy.value = false
  }
}
const print = () => window.print()
async function loadHistory() { history.value = (await invoicesApi.list()).data; historyOpen.value = true }
</script>

<style scoped>
.tile { cursor: pointer; }
.staff-pick { width: 120px; margin-right: 4px; }
@media print { .pos { display: none; } }
</style>
