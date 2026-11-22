<template>
  <h1 class="text-h5 font-weight-bold">Roles & permissions</h1>
  <div class="text-body-2 text-medium-emphasis mb-4">Tick what each role may do. Changes apply to every salon immediately. The Shop Owner and Super Admin roles are fixed by the platform.</div>
  <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>

  <v-card v-if="data">
    <v-table>
      <thead>
        <tr><th>Permission</th><th v-for="r in data.roles" :key="r.name" class="text-center">{{ title(r.name) }}<v-icon v-if="r.locked" icon="mdi-lock" size="14" class="ml-1" /></th></tr>
      </thead>
      <tbody>
        <tr v-for="p in data.permissions" :key="p">
          <td><div class="font-weight-medium">{{ p }}</div><div class="text-caption text-medium-emphasis">{{ hint[p] }}</div></td>
          <td v-for="r in data.roles" :key="r.name" class="text-center">
            <v-checkbox-btn :model-value="r.permissions.includes(p)" :disabled="r.locked || busy" color="primary" @update:model-value="toggle(r.name, p, !!$event)" />
          </td>
        </tr>
      </tbody>
    </v-table>
  </v-card>
  <v-snackbar v-model="toast" timeout="1800" color="success">Permissions updated</v-snackbar>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { adminApi, type RolesPayload } from './api'
import { ApiError } from '../shared/api/client'

const data = ref<RolesPayload | null>(null)
const error = ref('')
const busy = ref(false)
const toast = ref(false)
const title = (n: string) => n.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())
const hint: Record<string, string> = {
  'billing.create': 'Create bills at the counter', 'billing.edit': 'Edit a saved bill', 'billing.refund': 'Refund bills',
  'customers.manage': 'Add and edit customers', 'attendance.manage': 'Staff attendance', 'discount.override': 'Give discounts above the limit',
  'reports.daily': 'Daily report', 'reports.today': "Today's totals only", 'reports.all': 'All reports',
  'commission.view': 'See staff commission rates', 'payouts.manage': 'Staff payouts', 'staff.manage': 'Manage staff',
  'pricing.manage': 'Change service prices', 'plan.settings': 'Plan & billing', 'campaigns.manage': 'Send campaigns',
  'own.earnings.view': 'Staff see their own earnings', 'tenants.manage': 'Platform: manage salons', 'plans.manage': 'Platform: manage plans', 'support.access': 'Platform: support access',
}

const load = async () => { data.value = await adminApi.roles() }
onMounted(() => load().catch((e) => (error.value = (e as ApiError).message)))

async function toggle(role: string, perm: string, on: boolean) {
  const cur = data.value!.roles.find((r) => r.name === role)!.permissions
  const next = on ? [...new Set([...cur, perm])] : cur.filter((x) => x !== perm)
  busy.value = true
  error.value = ''
  try { data.value = await adminApi.updateRole(role, next); toast.value = true }
  catch (e) { error.value = (e as ApiError).message }
  finally { busy.value = false }
}
</script>
