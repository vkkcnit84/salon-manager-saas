<template>
  <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>
  <v-row>
    <v-col cols="12" md="5">
      <v-card class="pa-4">
        <div class="text-subtitle-1 font-weight-bold mb-3">New template</div>
        <v-select v-model="f.channel" :items="[{ title: 'WhatsApp', value: 'whatsapp' }, { title: 'SMS', value: 'sms' }]" label="Channel" />
        <v-text-field v-model="f.name" label="Name (lowercase_with_underscores)" hint="e.g. diwali_offer" />
        <v-select v-model="f.category" :items="['marketing', 'utility']" label="Category" />
        <v-textarea v-model="f.body" rows="4" label="Message" hint="{{1}} customer name · {{2}} shop · {{3}} offer · {{4}} expiry" persistent-hint />
        <v-btn class="mt-3" color="primary" block :disabled="!f.name || !f.body" @click="add">Save template</v-btn>
      </v-card>
    </v-col>
    <v-col cols="12" md="7">
      <v-card v-for="t in templates" :key="t.id" class="pa-4 mb-3">
        <div class="d-flex align-center"><b>{{ t.name }}</b><v-spacer /><v-chip size="x-small" class="mr-1">{{ t.channel }}</v-chip><v-chip size="x-small" class="mr-1">{{ t.category }}</v-chip><v-chip size="x-small" color="success" variant="tonal">{{ t.status }}</v-chip></div>
        <div class="wa-bubble pa-3 mt-2 rounded">{{ sample(t.body) }}</div>
      </v-card>
      <v-card v-if="!templates.length" class="pa-6 text-center text-medium-emphasis">No templates yet.</v-card>
    </v-col>
  </v-row>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { marketingApi, type Template } from './api'
import { ApiError } from '../shared/api/client'

const templates = ref<Template[]>([]); const error = ref('')
const sample = (b: string) => b.replace('{{1}}', 'Anita').replace('{{2}}', 'Your Salon').replace('{{3}}', '15% off').replace('{{4}}', '30 Nov')
const f = reactive({ channel: 'whatsapp', name: '', category: 'marketing', body: 'Hi {{1}}! {{3}} at {{2}}. Valid till {{4}}. Reply STOP to opt out.' })
const load = async () => (templates.value = await marketingApi.templates())
onMounted(() => load().catch((e) => (error.value = (e as ApiError).message)))
async function add() {
  error.value = ''
  try { await marketingApi.createTemplate(f as Partial<Template>); f.name = ''; await load() } catch (e) { const x = e as ApiError; error.value = Object.values(x.errors ?? {})[0]?.[0] ?? x.message }
}
</script>

<style scoped>
.wa-bubble { background: #dcf8c6; color: #111; white-space: pre-wrap; }
</style>
