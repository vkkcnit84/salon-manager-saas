<template>
  <h1 class="text-h5 mb-4">Team</h1>
  <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>
  <v-row>
    <v-col cols="12" md="5">
      <v-text-field v-model="form.name" label="Name" />
      <v-text-field v-model="form.mobile" label="Mobile" maxlength="10" />
      <v-select v-model="form.shop_id" label="Shop" :items="shops.shops" item-title="name" item-value="id" />
      <v-select v-model="form.role" label="Role" :items="roles" />
      <v-btn color="primary" :loading="busy" :disabled="!form.name || !form.mobile || !form.shop_id" @click="add">Add team member</v-btn>
    </v-col>
    <v-col cols="12" md="7">
      <v-list>
        <v-list-item v-for="u in users" :key="u.id" :title="u.name" :subtitle="u.mobile ?? ''" />
      </v-list>
    </v-col>
  </v-row>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { usersApi, type TeamUser } from './api'
import { useShopsStore } from '../stores/shops'
import { ApiError } from '../shared/api/client'

const shops = useShopsStore()
const users = ref<TeamUser[]>([])
const roles = [{ title: 'Manager', value: 'manager' }, { title: 'Cashier / Receptionist', value: 'cashier' }, { title: 'Staff', value: 'staff' }]
const form = reactive({ name: '', mobile: '', shop_id: null as number | null, role: 'cashier' })
const busy = ref(false)
const error = ref('')

const load = async () => (users.value = await usersApi.list())
onMounted(load)

async function add() {
  busy.value = true
  error.value = ''
  try {
    await usersApi.create({ ...form, shop_id: form.shop_id! })
    Object.assign(form, { name: '', mobile: '' })
    await load()
  } catch (e) {
    const err = e as ApiError
    error.value = Object.values(err.errors ?? {})[0]?.[0] ?? err.message
  } finally {
    busy.value = false
  }
}
</script>
