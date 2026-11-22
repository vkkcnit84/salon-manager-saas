<template>
  <div class="d-flex align-center mb-4">
    <div>
      <h1 class="text-h5 font-weight-bold">Marketing</h1>
      <div class="text-body-2 text-medium-emphasis">WhatsApp & SMS campaigns, automations, coupons and credits</div>
    </div>
    <v-spacer />
    <v-chip color="primary" variant="tonal" prepend-icon="mdi-message-badge" size="large" @click="tab = 'credits'">{{ balance ?? '…' }} credits</v-chip>
  </div>
  <v-alert type="info" variant="tonal" density="compact" class="mb-4" icon="mdi-flask-outline">
    Messages are sent to a local test log, not to real phones, until a WhatsApp/SMS provider is connected. Everything else works end to end.
  </v-alert>
  <v-tabs v-model="tab" color="primary" class="mb-4" show-arrows>
    <v-tab value="campaigns">Campaigns</v-tab><v-tab value="templates">Templates</v-tab><v-tab value="automations">Automations</v-tab>
    <v-tab value="coupons">Coupons</v-tab><v-tab value="credits">Credits</v-tab><v-tab value="log">Message log</v-tab>
  </v-tabs>
  <v-window v-model="tab" :touch="false">
    <v-window-item value="campaigns"><CampaignsTab @changed="loadBalance" /></v-window-item>
    <v-window-item value="templates"><TemplatesTab /></v-window-item>
    <v-window-item value="automations"><AutomationsTab /></v-window-item>
    <v-window-item value="coupons"><CouponsTab /></v-window-item>
    <v-window-item value="credits"><CreditsTab @changed="loadBalance" /></v-window-item>
    <v-window-item value="log"><LogTab v-if="tab === 'log'" /></v-window-item>
  </v-window>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { marketingApi } from './api'
import CampaignsTab from './CampaignsTab.vue'
import TemplatesTab from './TemplatesTab.vue'
import AutomationsTab from './AutomationsTab.vue'
import CouponsTab from './CouponsTab.vue'
import CreditsTab from './CreditsTab.vue'
import LogTab from './LogTab.vue'

const tab = ref((useRoute().query.tab as string) || 'campaigns')
const balance = ref<number | null>(null)
const loadBalance = async () => { try { balance.value = (await marketingApi.credits()).balance } catch { /* shown per tab */ } }
onMounted(loadBalance)
</script>
