<template>
  <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>
  <v-card>
    <v-list lines="two">
      <v-list-item v-for="a in rows" :key="a.trigger" :title="meta[a.trigger].title" :subtitle="meta[a.trigger].desc">
        <template #prepend><v-avatar color="primary" variant="tonal"><v-icon :icon="meta[a.trigger].icon" /></v-avatar></template>
        <template #append>
          <v-select :model-value="a.template_id" :items="templates" item-title="name" item-value="id" density="compact" hide-details placeholder="Template" style="width: 190px" class="mr-3" @update:model-value="(v: number) => save(a, { template_id: v })" />
          <v-switch :model-value="a.active" color="primary" hide-details density="compact" @update:model-value="(v: boolean | null) => save(a, { active: !!v })" />
        </template>
      </v-list-item>
    </v-list>
  </v-card>
  <div class="text-caption text-medium-emphasis mt-2">Birthday/anniversary run at 9:00 AM, win-back daily, due reminders weekly. Each message uses one credit and only goes to customers who opted in.</div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { marketingApi, type Automation, type Template } from './api'
import { ApiError } from '../shared/api/client'

const rows = ref<Automation[]>([]); const templates = ref<Template[]>([]); const error = ref('')
const meta: Record<string, { title: string; desc: string; icon: string }> = {
  invoice_created: { title: 'Thank-you + bill after a visit', desc: 'Sent right after a bill is saved', icon: 'mdi-receipt-text-check' },
  birthday: { title: 'Birthday wishes', desc: 'With an offer, on the customer’s birthday', icon: 'mdi-cake-variant' },
  anniversary: { title: 'Anniversary wishes', desc: 'On the customer’s anniversary', icon: 'mdi-heart' },
  winback: { title: 'Win-back', desc: 'Customers who haven’t visited in 45 days', icon: 'mdi-account-reactivate' },
  due_reminder: { title: 'Due reminders', desc: 'Weekly nudge for pending balances', icon: 'mdi-cash-clock' },
  feedback: { title: 'Feedback request', desc: 'Ask for a rating after the visit', icon: 'mdi-star-face' },
}
const load = async () => { [rows.value, templates.value] = await Promise.all([marketingApi.automations(), marketingApi.templates()]) }
onMounted(() => load().catch((e) => (error.value = (e as ApiError).message)))
async function save(a: Automation, d: Partial<Automation>) {
  error.value = ''
  try { await marketingApi.saveAutomation(a.trigger, { template_id: a.template_id, ...d }) } catch (e) { error.value = (e as ApiError).message }
  await load()
}
</script>
