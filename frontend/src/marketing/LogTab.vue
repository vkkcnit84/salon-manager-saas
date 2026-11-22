<template>
  <v-alert type="info" variant="tonal" density="compact" class="mb-3">Local test outbox: this is exactly what would be sent to customers and team by WhatsApp/SMS.</v-alert>
  <v-card><v-table density="comfortable">
    <thead><tr><th>When</th><th></th><th>To</th><th>Template</th><th>Message</th></tr></thead>
    <tbody>
      <tr v-for="m in rows" :key="m.id"><td class="text-no-wrap">{{ new Date(m.created_at).toLocaleString('en-IN') }}</td>
        <td><v-icon :icon="m.direction === 'out' ? 'mdi-arrow-top-right' : 'mdi-arrow-bottom-left'" :color="m.direction === 'out' ? 'primary' : 'success'" size="small" /></td>
        <td>{{ m.to }}</td><td class="text-caption">{{ m.template ?? '—' }}</td><td style="white-space: pre-wrap">{{ m.body }}</td></tr>
      <tr v-if="!rows.length"><td colspan="5" class="text-center pa-6 text-medium-emphasis">Nothing sent yet.</td></tr>
    </tbody>
  </v-table></v-card>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { marketingApi, type MessageRow } from './api'

const rows = ref<MessageRow[]>([])
onMounted(async () => (rows.value = await marketingApi.messages()))
</script>
