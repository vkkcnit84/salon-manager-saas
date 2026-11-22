<template>
  <h1 class="text-h5 font-weight-bold mb-4">Staff</h1>
  <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>
  <v-card class="pa-4 mb-4">
    <v-row dense>
      <v-col cols="12" sm="3"><v-text-field v-model="f.name" label="Name" hide-details /></v-col>
      <v-col cols="12" sm="3"><v-text-field v-model="f.mobile" label="Mobile" maxlength="10" hide-details /></v-col>
      <v-col cols="6" sm="2"><v-text-field v-model="f.title" label="Role (e.g. Stylist)" hide-details /></v-col>
      <v-col cols="6" sm="2"><v-text-field v-model.number="f.commission" label="Commission %" type="number" min="0" max="100" hide-details /></v-col>
      <v-col cols="12" sm="2"><v-btn block color="primary" height="48" :disabled="!f.name" @click="add">Add</v-btn></v-col>
    </v-row>
  </v-card>
  <v-card>
    <v-list>
      <v-list-item v-for="s in staff" :key="s.id" :title="s.name" :subtitle="[s.title, s.mobile].filter(Boolean).join(' · ')">
        <template #prepend><v-avatar color="primary" variant="tonal">{{ s.name[0] }}</v-avatar></template>
        <template v-if="s.commission_value !== undefined" #append><v-chip size="small">{{ s.commission_value / 100 }}% commission</v-chip></template>
      </v-list-item>
      <v-list-item v-if="!staff.length" title="No staff yet" subtitle="Add your stylists to track their work and commission" />
    </v-list>
  </v-card>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { staffApi, type Staff } from '../billing/api'
import { useShopsStore } from '../stores/shops'
import { ApiError } from '../shared/api/client'

const shops = useShopsStore()
const staff = ref<Staff[]>([])
const error = ref('')
const f = reactive({ name: '', mobile: '', title: '', commission: 10 })
const load = async () => (staff.value = await staffApi.list())
onMounted(load)

async function add() {
  error.value = ''
  try {
    await staffApi.create({ name: f.name, mobile: f.mobile || null, title: f.title || null, commission_type: 'percent', commission_value: Math.round(f.commission * 100), shop_ids: shops.shops.map((s) => s.id) })
    Object.assign(f, { name: '', mobile: '', title: '' })
    await load()
  } catch (e) {
    const err = e as ApiError
    error.value = Object.values(err.errors ?? {})[0]?.[0] ?? err.message
  }
}
</script>
