<template>
  <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>
  <v-alert v-if="ok" type="success" variant="tonal" class="mb-3" closable @click:close="ok = ''">{{ ok }}</v-alert>
  <v-row v-if="info">
    <v-col cols="12" md="4">
      <v-card class="pa-5 text-center hero-credit">
        <div class="text-caption">AVAILABLE CREDITS</div><div class="text-h3 font-weight-bold">{{ info.balance }}</div>
        <v-chip v-if="info.balance < 50" color="warning" class="mt-2" size="small">Running low</v-chip>
      </v-card>
      <div class="text-subtitle-1 font-weight-bold mt-5 mb-2">Buy credits</div>
      <v-card v-for="p in info.packs" :key="p.code" class="pa-3 mb-2 d-flex align-center">
        <div><b>{{ p.credits.toLocaleString('en-IN') }} credits</b><div class="text-caption text-medium-emphasis">{{ formatPaise(p.price_paise) }}</div></div><v-spacer />
        <v-btn size="small" color="primary" :loading="busy === p.code" @click="buy(p.code)">Buy</v-btn>
      </v-card>
      <div class="text-caption text-medium-emphasis">Payments are simulated locally ({{ info.payment_driver }}). Razorpay plugs in here later.</div>
    </v-col>
    <v-col cols="12" md="8">
      <v-card><v-table density="comfortable">
        <thead><tr><th>When</th><th>Type</th><th>Reference</th><th class="text-right">Credits</th><th class="text-right">Balance</th></tr></thead>
        <tbody>
          <tr v-for="l in info.ledger" :key="l.id"><td>{{ new Date(l.created_at).toLocaleString('en-IN') }}</td><td><v-chip size="x-small" :color="l.credits > 0 ? 'success' : 'default'" variant="tonal">{{ l.type }}</v-chip></td><td class="text-caption">{{ l.reference }}</td>
            <td class="text-right" :class="l.credits > 0 ? 'text-success' : ''">{{ l.credits > 0 ? '+' : '' }}{{ l.credits }}</td><td class="text-right">{{ l.balance_after }}</td></tr>
          <tr v-if="!info.ledger.length"><td colspan="5" class="text-center pa-6 text-medium-emphasis">No transactions yet.</td></tr>
        </tbody>
      </v-table></v-card>
    </v-col>
  </v-row>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { marketingApi, type CreditsInfo } from './api'
import { formatPaise } from '../shared/money'
import { ApiError } from '../shared/api/client'

const emit = defineEmits<{ changed: [] }>()
const info = ref<CreditsInfo | null>(null); const error = ref(''); const ok = ref(''); const busy = ref('')
const load = async () => (info.value = await marketingApi.credits())
onMounted(() => load().catch((e) => (error.value = (e as ApiError).message)))
async function buy(code: string) {
  busy.value = code; error.value = ''
  try { const r = await marketingApi.topup(code); ok.value = `Credits added. Reference ${r.reference}`; await load(); emit('changed') } catch (e) { error.value = (e as ApiError).message } finally { busy.value = '' }
}
</script>

<style scoped>
.hero-credit { background: linear-gradient(120deg, #6d28d9, #ec4899) !important; color: #fff; border: 0 !important; }
</style>
