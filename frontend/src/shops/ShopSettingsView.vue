<template>
  <div>
  <ProfileCard />
  <div v-if="shop">
    <h1 class="text-h5 mb-4">{{ shop.name }} — settings</h1>
    <v-alert v-if="msg" :type="ok ? 'success' : 'error'" variant="tonal" class="mb-3">{{ msg }}</v-alert>
    <v-row>
      <v-col cols="12" md="6">
        <v-text-field v-model="form.name" label="Shop name" />
        <v-text-field v-model="form.phone" label="Phone" />
        <v-textarea v-model="form.address" label="Address" rows="2" />
        <v-text-field v-model="form.gstin" label="GSTIN" />
        <v-text-field v-model="form.invoice_prefix" label="Invoice prefix" />
        <v-select v-model="form.tax_mode" label="GST mode" :items="[{ title: 'Exclusive', value: 'exclusive' }, { title: 'Inclusive', value: 'inclusive' }]" />
        <v-text-field v-model="form.timezone" label="Time zone" />
      </v-col>
      <v-col cols="12" md="6">
        <v-text-field v-model="s.opening_hours.open" label="Opens" type="time" />
        <v-text-field v-model="s.opening_hours.close" label="Closes" type="time" />
        <v-select v-model="s.weekly_off" label="Weekly off" multiple chips :items="days" />
        <v-select v-model="s.rounding" label="Rounding" :items="[{ title: 'Nearest rupee', value: 'nearest_rupee' }, { title: 'None', value: 'none' }]" />
        <v-text-field v-model="s.bill_footer" label="Bill footer" />
        <v-switch v-model="s.commission_basis.after_discount" label="Commission after discount" color="primary" />
        <v-switch v-model="s.commission_basis.before_gst" label="Commission before GST" color="primary" />
      </v-col>
    </v-row>
    <v-btn color="primary" :loading="busy" @click="save">Save</v-btn>
  </div>
  <p v-else>Select a shop from the header to edit its settings.</p>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watchEffect } from 'vue'
import ProfileCard from './ProfileCard.vue'
import { shopsApi, type ShopSettings } from './api'
import { useShopStore } from '../stores/shop'
import { useShopsStore } from '../stores/shops'
import { ApiError } from '../shared/api/client'

const ctx = useShopStore()
const shops = useShopsStore()
const shop = computed(() => shops.shops.find((x) => x.id === ctx.shopId))
const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((title, value) => ({ title, value }))
const form = reactive({ name: '', phone: '', address: '', gstin: '', invoice_prefix: '', tax_mode: 'exclusive', timezone: 'Asia/Kolkata' })
const s = reactive<ShopSettings>({ opening_hours: { open: '10:00', close: '21:00' }, weekly_off: [], rounding: 'nearest_rupee', bill_footer: '', commission_basis: { after_discount: true, before_gst: true }, low_credit_alert: 100 })
const busy = ref(false)
const msg = ref('')
const ok = ref(true)

watchEffect(() => {
  if (!shop.value) return
  Object.assign(form, { name: shop.value.name, phone: shop.value.phone ?? '', address: shop.value.address ?? '', gstin: shop.value.gstin ?? '', invoice_prefix: shop.value.invoice_prefix ?? '', tax_mode: shop.value.tax_mode, timezone: shop.value.timezone })
  Object.assign(s, JSON.parse(JSON.stringify(shop.value.settings)))
})

async function save() {
  if (!shop.value) return
  busy.value = true
  msg.value = ''
  try {
    const nullable = (v: string) => (v === '' ? null : v)
    await shopsApi.update(shop.value.id, { ...form, phone: nullable(form.phone), address: nullable(form.address), gstin: nullable(form.gstin), invoice_prefix: nullable(form.invoice_prefix), tax_mode: form.tax_mode as 'inclusive' | 'exclusive' })
    await shopsApi.updateSettings(shop.value.id, s)
    await shops.load()
    ok.value = true
    msg.value = 'Saved.'
  } catch (e) {
    ok.value = false
    const err = e as ApiError
    msg.value = Object.values(err.errors ?? {})[0]?.[0] ?? err.message
  } finally {
    busy.value = false
  }
}
</script>
