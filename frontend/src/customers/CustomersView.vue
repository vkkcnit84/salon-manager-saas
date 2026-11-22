<template>
  <h1 class="text-h5 font-weight-bold mb-4">Customers</h1>
  <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>
  <v-card class="pa-4 mb-4">
    <v-row dense>
      <v-col cols="12" sm="3"><v-text-field v-model="f.name" label="Name" hide-details /></v-col>
      <v-col cols="12" sm="3"><v-text-field v-model="f.mobile" label="Mobile" maxlength="10" prefix="+91" hide-details /></v-col>
      <v-col cols="6" sm="2"><v-text-field v-model="f.birthday" label="Birthday" type="date" hide-details /></v-col>
      <v-col cols="6" sm="2" class="d-flex align-center"><v-checkbox v-model="f.consent" label="WhatsApp OK" hide-details density="compact" /></v-col>
      <v-col cols="12" sm="2"><v-btn block color="primary" height="48" :disabled="!f.name || f.mobile.length !== 10" @click="add">Add</v-btn></v-col>
    </v-row>
    <div class="text-caption text-medium-emphasis mt-1">Only tick “WhatsApp OK” if the customer agreed to receive bills and offers. Offers are never sent without it.</div>
  </v-card>
  <v-text-field v-model="q" prepend-inner-icon="mdi-magnify" placeholder="Search by name or mobile" clearable hide-details class="mb-3" @update:model-value="load" />
  <v-card>
    <v-list>
      <v-list-item v-for="c in customers" :key="c.id" :title="c.name" :subtitle="c.mobile + (c.birthday ? ' · 🎂 ' + c.birthday.slice(5) : '')">
        <template #prepend><v-avatar color="secondary" variant="tonal">{{ c.name[0] }}</v-avatar></template>
        <template #append>
          <v-chip v-if="c.opted_out_at" size="small" color="error" variant="tonal" class="mr-2">Opted out</v-chip>
          <v-chip v-else-if="c.consent_whatsapp" size="small" color="success" variant="tonal" prepend-icon="mdi-whatsapp" class="mr-2">Opted in</v-chip>
          <v-chip v-else size="small" variant="tonal" class="mr-2">No consent</v-chip>
          <v-btn v-if="c.consent_whatsapp || c.opted_out_at" size="x-small" variant="text" @click="toggleOptOut(c)">{{ c.opted_out_at ? 'Opt back in' : 'Opt out' }}</v-btn>
          <v-btn v-else size="x-small" variant="text" @click="grant(c)">Record consent</v-btn>
        </template>
      </v-list-item>
      <v-list-item v-if="!customers.length" title="No customers found" />
    </v-list>
  </v-card>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { customersApi, type Customer } from '../billing/api'
import { ApiError } from '../shared/api/client'

const customers = ref<Customer[]>([])
const q = ref('')
const error = ref('')
const f = reactive({ name: '', mobile: '', birthday: '', consent: false })
const load = async () => (customers.value = (await customersApi.search(q.value ?? '')).data)
onMounted(load)
const guard = async (fn: () => Promise<unknown>) => {
  error.value = ''
  try { await fn(); await load() } catch (e) { const x = e as ApiError; error.value = Object.values(x.errors ?? {})[0]?.[0] ?? x.message }
}
const add = () => guard(async () => { await customersApi.create({ name: f.name, mobile: f.mobile, consent_whatsapp: f.consent, birthday: f.birthday || null }); Object.assign(f, { name: '', mobile: '', birthday: '', consent: false }) })
const toggleOptOut = (c: Customer) => guard(() => customersApi.update(c.id, { opted_out: !c.opted_out_at }))
const grant = (c: Customer) => guard(() => customersApi.update(c.id, { consent_whatsapp: true }))
</script>
