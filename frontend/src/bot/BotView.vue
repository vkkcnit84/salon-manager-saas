<template>
  <div class="d-flex align-center mb-4">
    <div><h1 class="text-h5 font-weight-bold">WhatsApp assistant</h1>
      <div class="text-body-2 text-medium-emphasis">Run your salon from WhatsApp — try it here before a real number is connected</div></div>
  </div>
  <v-alert type="info" variant="tonal" density="compact" class="mb-4" icon="mdi-flask-outline">This is a simulator. Real WhatsApp delivery is switched on later by adding the Meta number; the same logic answers real messages.</v-alert>

  <v-row>
    <v-col cols="12" md="7">
      <v-card class="chat-card d-flex flex-column">
        <div class="chat-head pa-3 d-flex align-center ga-3">
          <v-avatar color="white" size="36"><v-icon icon="mdi-whatsapp" color="#128c7e" /></v-avatar>
          <div><div class="font-weight-bold">Your Salon</div><div class="text-caption">+{{ info?.whatsapp_number }}</div></div>
          <v-spacer />
          <v-select v-model="from" :items="idItems" item-title="label" item-value="mobile" density="compact" hide-details variant="solo-filled" bg-color="white" style="max-width: 260px" label="Chatting as" @update:model-value="chat = []" />
        </div>
        <div ref="scroller" class="chat-body pa-3 flex-grow-1">
          <div v-if="!chat.length" class="text-center text-medium-emphasis mt-8">Say <b>hi</b> or <b>menu</b> to begin.<br />Owners can also ask in Hindi/Hinglish: <i>“Is mahine Ravi ka commission kitna hua?”</i></div>
          <div v-for="(m, i) in chat" :key="i" :class="['row', m.me ? 'me' : 'bot']">
            <div class="bubble">
              <div style="white-space: pre-wrap">{{ m.text }}</div>
              <div v-if="m.buttons?.length" class="d-flex flex-wrap ga-2 mt-2">
                <v-btn v-for="b in m.buttons" :key="b.id" size="small" variant="outlined" color="#128c7e" @click="send(b.title, b.id)">{{ b.title }}</v-btn>
              </div>
            </div>
          </div>
        </div>
        <div class="pa-3 d-flex ga-2 align-center chat-foot">
          <v-text-field v-model="draft" placeholder="Type a message" hide-details density="compact" variant="solo" @keyup.enter="send(draft)" />
          <v-btn icon="mdi-send" color="#128c7e" :disabled="!draft.trim()" @click="send(draft)" />
        </div>
      </v-card>
    </v-col>

    <v-col cols="12" md="5">
      <v-card class="pa-4 mb-4">
        <div class="text-subtitle-1 font-weight-bold mb-2">Try these</div>
        <v-chip v-for="q in samples" :key="q" class="mr-2 mb-2" variant="outlined" @click="send(q)">{{ q }}</v-chip>
      </v-card>

      <v-card class="pa-4 mb-4">
        <div class="text-subtitle-1 font-weight-bold mb-1">Customer QR check-in</div>
        <div class="text-body-2 text-medium-emphasis mb-3">Customers scan the shop QR, which opens WhatsApp with this text. They are asked their name and consent, then join the waiting list on the billing screen.</div>
        <v-select v-model="checkin" :items="info?.checkin_codes ?? []" item-title="shop" item-value="text" label="Shop" hide-details density="compact" class="mb-2" />
        <v-text-field v-model="newNumber" label="New customer's mobile" prefix="+91" maxlength="10" hide-details density="compact" class="mb-2" />
        <v-btn block color="#128c7e" :disabled="newNumber.length !== 10 || !checkin" @click="simulateCheckin">Scan QR as this customer</v-btn>
      </v-card>

      <v-card class="pa-4">
        <div class="text-subtitle-1 font-weight-bold mb-1">Nightly summary</div>
        <div class="text-body-2 text-medium-emphasis mb-2">Get today's sales, cash vs UPI, top staff and dues on your WhatsApp every night at 10 PM.</div>
        <v-switch v-model="nightly" label="Send me the nightly summary" color="primary" hide-details @update:model-value="savePref" />
        <v-btn class="mt-2" variant="tonal" prepend-icon="mdi-send" :loading="sending" @click="sendNow">Send today's summary now</v-btn>
        <v-alert v-if="note" :type="noteOk ? 'success' : 'error'" variant="tonal" density="compact" class="mt-3">{{ note }}</v-alert>
      </v-card>
    </v-col>
  </v-row>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { botApi, type BotReply, type Identity } from '../marketing/api'
import { useAuthStore } from '../stores/auth'
import { ApiError } from '../shared/api/client'

type Msg = { me: boolean; text: string; buttons?: BotReply['buttons'] }
const auth = useAuthStore()
const info = ref<Awaited<ReturnType<typeof botApi.identities>> | null>(null)
const from = ref('')
const chat = ref<Msg[]>([])
const draft = ref('')
const scroller = ref<HTMLElement>()
const checkin = ref(''); const newNumber = ref('')
const nightly = ref(false); const sending = ref(false); const note = ref(''); const noteOk = ref(true)
const samples = ['menu', "Today's sales", 'Is mahine Ravi ka commission kitna hua?', 'dues', 'aaj kitni sales hui', 'Send offer']
const idItems = computed<Identity[]>(() => info.value?.identities ?? [])

onMounted(async () => {
  info.value = await botApi.identities()
  from.value = info.value.identities.find((i) => i.label.includes('Owner') || i.mobile === auth.me?.mobile)?.mobile ?? info.value.identities[0]?.mobile ?? ''
  checkin.value = info.value.checkin_codes[0]?.text ?? ''
  nightly.value = false
})

async function send(text: string, button?: string) {
  if (!text.trim() && !button) return
  chat.value.push({ me: true, text })
  draft.value = ''
  try {
    const r = await botApi.simulate(from.value, button ? '' : text, button)
    r.replies.forEach((x) => chat.value.push({ me: false, text: x.text, buttons: x.buttons }))
  } catch (e) { chat.value.push({ me: false, text: '⚠️ ' + (e as ApiError).message }) }
  await nextTick(); scroller.value?.scrollTo({ top: 99999, behavior: 'smooth' })
}
async function simulateCheckin() {
  from.value = newNumber.value
  chat.value = []
  await send(checkin.value)
}
async function savePref(v: boolean | null) {
  note.value = ''
  try { await botApi.prefs(!!v); noteOk.value = true; note.value = v ? 'You will get the nightly summary.' : 'Nightly summary turned off.' }
  catch (e) { nightly.value = false; noteOk.value = false; note.value = (e as ApiError).message }
}
async function sendNow() {
  sending.value = true; note.value = ''
  try { note.value = (await botApi.sendSummary()).message; noteOk.value = true } catch (e) { noteOk.value = false; note.value = (e as ApiError).message } finally { sending.value = false }
}
</script>

<style scoped>
.chat-card { height: 620px; overflow: hidden; }
.chat-head { background: #128c7e; color: #fff; }
.chat-body { background: #ece5dd; overflow-y: auto; }
.chat-foot { background: #f0f0f0; }
.row { display: flex; margin-bottom: 8px; }
.row.me { justify-content: flex-end; }
.bubble { max-width: 80%; padding: 8px 12px; border-radius: 10px; box-shadow: 0 1px 1px rgba(0,0,0,.12); color: #111; }
.me .bubble { background: #dcf8c6; }
.bot .bubble { background: #fff; }
</style>
