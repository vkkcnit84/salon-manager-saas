<template>
  <div class="d-flex align-center mb-4"><h1 class="text-h5 font-weight-bold">Catalog</h1></div>
  <v-tabs v-model="tab" color="primary" class="mb-4"><v-tab value="services">Services</v-tab><v-tab value="products">Products</v-tab></v-tabs>
  <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>

  <v-window v-model="tab">
    <v-window-item value="services">
      <v-card class="pa-4 mb-4">
        <v-row dense>
          <v-col cols="12" sm="4"><v-text-field v-model="svc.name" label="Service name" hide-details /></v-col>
          <v-col cols="6" sm="2"><v-text-field v-model.number="svc.price" label="Price ₹" type="number" min="0" hide-details /></v-col>
          <v-col cols="6" sm="2"><v-text-field v-model.number="svc.duration" label="Minutes" type="number" min="5" hide-details /></v-col>
          <v-col cols="6" sm="2"><v-select v-model="svc.gst" :items="gstOptions" label="GST" hide-details /></v-col>
          <v-col cols="6" sm="2"><v-btn block color="primary" height="48" :disabled="!svc.name || svc.price === null" @click="addService">Add</v-btn></v-col>
        </v-row>
      </v-card>
      <v-card>
        <v-table>
          <thead><tr><th>Service</th><th>Price</th><th>Time</th><th>GST</th><th>Active</th></tr></thead>
          <tbody>
            <tr v-for="s in services" :key="s.id">
              <td>{{ s.name }}</td><td>{{ formatPaise(s.price_paise) }}</td><td>{{ s.duration_min }} min</td><td>{{ s.gst_bps / 100 }}%</td>
              <td><v-switch :model-value="s.active" density="compact" hide-details color="primary" @update:model-value="toggleService(s, !!$event)" /></td>
            </tr>
            <tr v-if="!services.length"><td colspan="5" class="text-center text-medium-emphasis pa-6">No services yet — add your first above.</td></tr>
          </tbody>
        </v-table>
      </v-card>
    </v-window-item>

    <v-window-item value="products">
      <v-card class="pa-4 mb-4">
        <v-row dense>
          <v-col cols="12" sm="5"><v-text-field v-model="prod.name" label="Product name" hide-details /></v-col>
          <v-col cols="6" sm="2"><v-text-field v-model.number="prod.price" label="Price ₹" type="number" min="0" hide-details /></v-col>
          <v-col cols="6" sm="2"><v-text-field v-model.number="prod.stock" label="Stock" type="number" hide-details /></v-col>
          <v-col cols="12" sm="3"><v-btn block color="primary" height="48" :disabled="!prod.name || prod.price === null" @click="addProduct">Add</v-btn></v-col>
        </v-row>
      </v-card>
      <v-card>
        <v-table>
          <thead><tr><th>Product</th><th>Price</th><th>Stock</th></tr></thead>
          <tbody>
            <tr v-for="p in products" :key="p.id"><td>{{ p.name }}</td><td>{{ formatPaise(p.price_paise) }}</td><td>{{ p.stock }}</td></tr>
            <tr v-if="!products.length"><td colspan="3" class="text-center text-medium-emphasis pa-6">No products yet.</td></tr>
          </tbody>
        </v-table>
      </v-card>
    </v-window-item>
  </v-window>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { catalogApi, type Product, type Service } from '../billing/api'
import { formatPaise } from '../shared/money'
import { ApiError } from '../shared/api/client'

const tab = ref('services')
const services = ref<Service[]>([])
const products = ref<Product[]>([])
const error = ref('')
const gstOptions = [{ title: '0%', value: 0 }, { title: '5%', value: 500 }, { title: '12%', value: 1200 }, { title: '18%', value: 1800 }]
const svc = reactive({ name: '', price: null as number | null, duration: 30, gst: 1800 })
const prod = reactive({ name: '', price: null as number | null, stock: 0 })

const load = async () => { [services.value, products.value] = await Promise.all([catalogApi.services(), catalogApi.products()]) }
onMounted(load)

async function guard(fn: () => Promise<unknown>) {
  error.value = ''
  try { await fn(); await load() } catch (e) {
    const err = e as ApiError
    error.value = Object.values(err.errors ?? {})[0]?.[0] ?? err.message
  }
}
const addService = () => guard(async () => {
  await catalogApi.createService({ name: svc.name, price_paise: Math.round(svc.price! * 100), duration_min: svc.duration, gst_bps: svc.gst })
  Object.assign(svc, { name: '', price: null })
})
const toggleService = (s: Service, active: boolean) => guard(() => catalogApi.updateService(s.id, { active }))
const addProduct = () => guard(async () => {
  await catalogApi.createProduct({ name: prod.name, price_paise: Math.round(prod.price! * 100), stock: prod.stock })
  Object.assign(prod, { name: '', price: null, stock: 0 })
})
</script>
