<template>
  <div class="d-flex mb-3"><v-spacer /><v-btn color="primary" prepend-icon="mdi-plus" @click="openWizard">New campaign</v-btn></div>
  <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>

  <v-row>
    <v-col v-for="c in campaigns" :key="c.id" cols="12" md="6">
      <v-card class="pa-4 h-100">
        <div class="d-flex align-center">
          <div class="font-weight-bold">{{ c.name }}</div><v-spacer />
          <v-chip size="small" :color="statusColor[c.status]" variant="tonal">{{ c.status }}</v-chip>
        </div>
        <div class="text-caption text-medium-emphasis mb-3">{{ c.channel.toUpperCase() }} · {{ c.template?.name }}</div>
        <v-row dense class="text-center">
          <v-col v-for="m in metrics(c)" :key="m.label" cols="3"><div class="text-subtitle-1 font-weight-bold">{{ m.value }}</div><div class="text-caption text-medium-emphasis">{{ m.label }}</div></v-col>
        </v-row>
        <v-progress-linear v-if="c.stats.sent" :model-value="(c.stats.read / c.stats.sent) * 100" color="success" height="6" rounded class="my-3" />
        <div v-if="c.stats.attributed_revenue_paise" class="text-body-2">💰 Revenue from targeted customers in 7 days: <b>{{ formatPaise(c.stats.attributed_revenue_paise) }}</b></div>
        <div class="d-flex ga-2 mt-3">
          <v-btn v-if="c.status === 'draft'" size="small" color="primary" :loading="sending === c.id" @click="send(c)">Send now</v-btn>
          <v-btn size="small" variant="tonal" @click="openReport(c)">Report</v-btn>
        </div>
      </v-card>
    </v-col>
    <v-col v-if="!campaigns.length" cols="12"><v-card class="pa-8 text-center text-medium-emphasis">No campaigns yet. Create your first win-back offer.</v-card></v-col>
  </v-row>

  <!-- Wizard -->
  <v-dialog v-model="wizard" max-width="720" scrollable>
    <v-card>
      <v-stepper v-model="step" alt-labels flat :items="['Audience', 'Template', 'Schedule', 'Cost', 'Confirm']" hide-actions>
        <template #item.1>
          <div class="text-subtitle-1 font-weight-bold mb-2">Who should receive it?</div>
          <v-chip-group v-model="preset" mandatory selected-class="text-primary" @update:model-value="refreshEstimate">
            <v-chip v-for="p in presets" :key="p.key" :value="p.key" filter variant="outlined">{{ p.label }}</v-chip>
          </v-chip-group>
          <v-select v-model="channel" :items="[{ title: 'WhatsApp', value: 'whatsapp' }, { title: 'SMS', value: 'sms' }]" label="Channel" class="mt-3" hide-details @update:model-value="refreshEstimate" />
          <v-alert v-if="est" :type="est.audience ? 'success' : 'warning'" variant="tonal" class="mt-4">
            <b>{{ est.audience }}</b> customers can be messaged (consented and not opted out).
          </v-alert>
        </template>
        <template #item.2>
          <v-select v-model="templateId" :items="templates" item-title="name" item-value="id" label="Template" />
          <v-text-field v-model="offer" label="Offer text ({{3}})" /><v-text-field v-model="expiry" label="Valid till ({{4}})" />
          <v-card variant="tonal" class="pa-3 wa-bubble">{{ preview }}</v-card>
          <v-alert v-if="!templates.length" type="warning" variant="tonal" class="mt-3">No approved template for this channel. Add one in the Templates tab.</v-alert>
        </template>
        <template #item.3>
          <v-text-field v-model="name" label="Campaign name" />
          <v-radio-group v-model="when"><v-radio label="Send right after I confirm" value="now" /><v-radio label="Schedule for later" value="later" /></v-radio-group>
          <v-text-field v-if="when === 'later'" v-model="scheduledAt" type="datetime-local" label="Send at" />
        </template>
        <template #item.4>
          <v-card variant="tonal" class="pa-4" v-if="est">
            <div class="d-flex"><span>Audience</span><v-spacer /><b>{{ est.audience }}</b></div>
            <div class="d-flex"><span>Credits needed</span><v-spacer /><b>{{ est.credits_needed }}</b></div>
            <div class="d-flex"><span>Your balance</span><v-spacer /><b>{{ est.balance }}</b></div>
          </v-card>
          <v-alert v-if="est && !est.sufficient" type="error" variant="tonal" class="mt-3">Not enough credits. Top up in the Credits tab first.</v-alert>
        </template>
        <template #item.5>
          <div class="text-subtitle-1 mb-2">Ready to go</div>
          <div>{{ name }} → {{ est?.audience }} customers via {{ channel }}.</div>
          <div class="text-medium-emphasis">{{ when === 'now' ? 'Sends immediately.' : `Scheduled for ${scheduledAt}.` }}</div>
          <v-alert v-if="wizardError" type="error" variant="tonal" class="mt-3">{{ wizardError }}</v-alert>
        </template>
      </v-stepper>
      <v-card-actions>
        <v-btn v-if="step > 1" @click="step--">Back</v-btn><v-spacer />
        <v-btn @click="wizard = false">Cancel</v-btn>
        <v-btn v-if="step < 5" color="primary" variant="flat" :disabled="!canNext" @click="next">Next</v-btn>
        <v-btn v-else color="primary" variant="flat" :loading="busy" @click="confirm">{{ when === 'now' ? 'Send campaign' : 'Schedule' }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Report -->
  <v-dialog v-model="reportOpen" max-width="560">
    <v-card v-if="report" class="pa-5">
      <div class="text-h6">{{ report.campaign.name }}</div>
      <v-row class="my-2 text-center">
        <v-col v-for="m in metrics(report.campaign, report.stats)" :key="m.label" cols="3"><div class="text-h6">{{ m.value }}</div><div class="text-caption">{{ m.label }}</div></v-col>
      </v-row>
      <div class="mb-2">Credits spent: <b>{{ report.stats.credits_spent }}</b> · Attributed revenue (7 days): <b>{{ formatPaise(report.stats.attributed_revenue_paise) }}</b></div>
      <div v-if="report.failed.length" class="mt-2"><div class="text-subtitle-2">Failed ({{ report.failed.length }})</div>
        <div v-for="f in report.failed" :key="f.customer.mobile" class="text-body-2">{{ f.customer.name }} · {{ f.customer.mobile }} — {{ f.error }}</div></div>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { marketingApi, type Campaign, type Estimate, type Stats, type Template } from './api'
import { formatPaise } from '../shared/money'
import { ApiError } from '../shared/api/client'

const emit = defineEmits<{ changed: [] }>()
const campaigns = ref<Campaign[]>([])
const allTemplates = ref<Template[]>([])
const error = ref('')
const sending = ref<number | null>(null)
const statusColor: Record<string, string> = { draft: 'default', scheduled: 'info', sending: 'warning', completed: 'success', stopped: 'error' }

const load = async () => { [campaigns.value, allTemplates.value] = await Promise.all([marketingApi.campaigns(), marketingApi.templates()]) }
onMounted(() => load().catch((e) => (error.value = (e as ApiError).message)))
const metrics = (c: Campaign, s: Stats = c.stats) => [{ label: 'Sent', value: s.sent }, { label: 'Delivered', value: s.delivered }, { label: 'Read', value: s.read }, { label: 'Failed', value: s.failed }]

async function send(c: Campaign) {
  sending.value = c.id; error.value = ''
  try { await marketingApi.send(c.id); await load(); emit('changed') } catch (e) { error.value = (e as ApiError).message } finally { sending.value = null }
}

// report
const reportOpen = ref(false)
const report = ref<Awaited<ReturnType<typeof marketingApi.report>> | null>(null)
async function openReport(c: Campaign) { report.value = await marketingApi.report(c.id); reportOpen.value = true }

// wizard
const presets = [
  { key: 'all', label: 'All consented', filter: {} }, { key: 'lapsed', label: 'Not visited in 45 days', filter: { inactive_days: 45 } },
  { key: 'vip', label: 'High spenders (₹5,000+)', filter: { min_spend_paise: 500000 } }, { key: 'bday', label: 'Birthday this month', filter: { birthday_month: new Date().getMonth() + 1 } },
  { key: 'due', label: 'Has pending dues', filter: { has_due: true } },
]
const wizard = ref(false); const step = ref(1); const preset = ref('lapsed'); const channel = ref('whatsapp')
const est = ref<Estimate | null>(null); const templateId = ref<number | null>(null)
const offer = ref('Flat 15% off this week'); const expiry = ref(''); const name = ref(''); const when = ref('now'); const scheduledAt = ref('')
const busy = ref(false); const wizardError = ref('')
const templates = computed(() => allTemplates.value.filter((t) => t.channel === channel.value && t.status === 'approved'))
const filter = computed(() => presets.find((p) => p.key === preset.value)!.filter)
const preview = computed(() => (templates.value.find((t) => t.id === templateId.value)?.body ?? 'Pick a template').replace('{{1}}', 'Anita').replace('{{2}}', 'Your Salon').replace('{{3}}', offer.value).replace('{{4}}', expiry.value))
const canNext = computed(() => (step.value === 1 ? !!est.value?.audience : step.value === 2 ? !!templateId.value : step.value === 3 ? !!name.value && (when.value === 'now' || !!scheduledAt.value) : step.value === 4 ? !!est.value?.sufficient : true))

async function refreshEstimate() { est.value = await marketingApi.estimate(channel.value, filter.value) }
async function openWizard() {
  const d = new Date(); d.setDate(d.getDate() + 7); expiry.value = d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })
  step.value = 1; wizardError.value = ''; name.value = ''; templateId.value = templates.value[0]?.id ?? null
  wizard.value = true; await refreshEstimate()
}
async function next() { if (step.value === 3 || step.value === 1) await refreshEstimate(); step.value++ }
async function confirm() {
  busy.value = true; wizardError.value = ''
  try {
    const c = await marketingApi.createCampaign({ name: name.value, channel: channel.value, template_id: templateId.value, audience: filter.value, meta: { offer: offer.value, expiry: expiry.value }, scheduled_at: when.value === 'later' ? new Date(scheduledAt.value).toISOString() : null })
    if (when.value === 'now') await marketingApi.send(c.id)
    wizard.value = false; await load(); emit('changed')
  } catch (e) { wizardError.value = (e as ApiError).message } finally { busy.value = false }
}
</script>

<style scoped>
.wa-bubble { background: #dcf8c6 !important; color: #111; white-space: pre-wrap; }
</style>
