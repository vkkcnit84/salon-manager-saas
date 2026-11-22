<template>
  <v-card class="mx-auto mt-6" max-width="640">
    <v-card-title>Set up your salon</v-card-title>
    <v-stepper v-model="step" flat alt-labels hide-actions>
      <v-stepper-header>
        <v-stepper-item :value="1" title="Business" />
        <v-stepper-item :value="2" title="First shop" />
        <v-stepper-item :value="3" title="Bill format" />
        <v-stepper-item :value="4" title="Services & staff" />
      </v-stepper-header>

      <v-stepper-window>
        <v-stepper-window-item :value="1">
          <v-text-field v-model="business" label="Business name" />
          <v-btn color="primary" :loading="busy" :disabled="!business.trim()" @click="saveBusiness">Next</v-btn>
        </v-stepper-window-item>

        <v-stepper-window-item :value="2">
          <v-text-field v-model="shop.name" label="Shop name" />
          <v-text-field v-model="shop.phone" label="Phone" />
          <v-textarea v-model="shop.address" label="Address" rows="2" />
          <v-text-field v-model="shop.gstin" label="GSTIN (optional)" />
          <v-text-field v-model="shop.invoice_prefix" label="Invoice prefix (e.g. HYD1-)" />
          <v-btn color="primary" :loading="busy" :disabled="!shop.name.trim()" @click="saveShop">Next</v-btn>
        </v-stepper-window-item>

        <v-stepper-window-item :value="3">
          <v-radio-group v-model="shop.tax_mode" label="Prices are">
            <v-radio label="GST exclusive (added on top)" value="exclusive" />
            <v-radio label="GST inclusive (already in price)" value="inclusive" />
          </v-radio-group>
          <v-text-field v-model="footer" label="Bill footer text" />
          <v-btn color="primary" :loading="busy" @click="saveFormat">Next</v-btn>
        </v-stepper-window-item>

        <v-stepper-window-item :value="4">
          <v-alert type="info" variant="tonal" class="mb-3">
            Loading default services and adding staff arrives in the next release. You can finish now and add them later.
          </v-alert>
          <v-btn color="primary" :loading="busy" @click="finish">Start billing</v-btn>
        </v-stepper-window-item>
      </v-stepper-window>
    </v-stepper>
    <v-alert v-if="error" type="error" variant="tonal" class="ma-4">{{ error }}</v-alert>
  </v-card>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { authApi } from './api'
import { shopsApi, type Shop } from '../shops/api'
import { useAuthStore } from '../stores/auth'
import { useShopsStore } from '../stores/shops'
import { useShopStore } from '../stores/shop'
import { ApiError } from '../shared/api/client'

const router = useRouter()
const auth = useAuthStore()
const shops = useShopsStore()
const ctx = useShopStore()

const step = ref(auth.me?.tenant_id ? 2 : 1)
const business = ref('')
const footer = ref('Thank you, visit again!')
const shop = reactive({ name: '', phone: '', address: '', gstin: '', invoice_prefix: '', tax_mode: 'exclusive' as Shop['tax_mode'] })
const created = ref<Shop | null>(null)
const busy = ref(false)
const error = ref('')

async function run(fn: () => Promise<void>) {
  busy.value = true
  error.value = ''
  try {
    await fn()
  } catch (e) {
    const err = e as ApiError
    error.value = Object.values(err.errors ?? {})[0]?.[0] ?? err.message
  } finally {
    busy.value = false
  }
}
const clean = <T extends object>(o: T) => Object.fromEntries(Object.entries(o).filter(([, v]) => v !== '')) as Partial<T>

const saveBusiness = () => run(async () => {
  await authApi.createBusiness(business.value.trim())
  auth.me = await authApi.me()
  step.value = 2
})
const saveShop = () => run(async () => {
  const { tax_mode, ...rest } = shop
  created.value = await shopsApi.create({ ...clean(rest), tax_mode, name: shop.name } as never)
  ctx.setShop(created.value.id)
  step.value = 3
})
const saveFormat = () => run(async () => {
  if (created.value) {
    await shopsApi.update(created.value.id, { tax_mode: shop.tax_mode })
    await shopsApi.updateSettings(created.value.id, { bill_footer: footer.value })
  }
  step.value = 4
})
const finish = () => run(async () => {
  await authApi.completeOnboarding()
  auth.me = await authApi.me()
  await shops.load()
  await router.replace('/')
})
</script>
