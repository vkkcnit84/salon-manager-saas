<template>
  <v-card class="pa-0 assistant d-flex flex-column">
    <div class="head pa-4 d-flex align-center ga-3">
      <v-avatar color="white" size="36"><v-icon icon="mdi-robot-happy-outline" color="primary" /></v-avatar>
      <div><div class="font-weight-bold">Ask your salon</div><div class="text-caption">English, Hinglish or हिन्दी · type or speak</div></div>
    </div>
    <div ref="box" class="body pa-3 flex-grow-1">
      <div v-if="!log.length" class="text-body-2 text-medium-emphasis">
        Try one of these:
        <div class="mt-2"><v-chip v-for="q in samples" :key="q" size="small" class="mr-1 mb-1" variant="outlined" @click="ask(q)">{{ q }}</v-chip></div>
      </div>
      <div v-for="(m, i) in log" :key="i" :class="['msg', m.me ? 'me' : 'ai']">
        <div class="bubble">
          <div style="white-space: pre-wrap">{{ m.text }}</div>
          <div v-if="m.buttons?.length" class="d-flex ga-2 mt-2">
            <v-btn v-for="b in m.buttons" :key="b.id" size="x-small" variant="flat" color="primary" @click="act(b.id, i)">{{ b.title }}</v-btn>
          </div>
        </div>
      </div>
      <div v-if="busy" class="text-caption text-medium-emphasis ml-2">Thinking…</div>
    </div>
    <div class="pa-3 d-flex ga-2 align-center foot">
      <v-text-field v-model="q" placeholder="e.g. Is mahine Ravi ka commission kitna hua?" hide-details density="compact" variant="outlined" @keyup.enter="ask(q)" />
      <v-btn v-if="speechOk" :icon="listening ? 'mdi-microphone-off' : 'mdi-microphone'" :color="listening ? 'error' : undefined" variant="tonal" aria-label="Speak" @click="toggleMic" />
      <v-btn icon="mdi-send" color="primary" :disabled="!q.trim() || busy" aria-label="Send" @click="ask(q)" />
    </div>
  </v-card>
</template>

<script setup lang="ts">
import { nextTick, ref } from 'vue'
import { assistantApi, marketingApi } from '../marketing/api'
import { ApiError } from './api/client'

type Msg = { me: boolean; text: string; buttons?: { id: string; title: string }[] }
const log = ref<Msg[]>([]); const q = ref(''); const busy = ref(false); const box = ref<HTMLElement>()
const samples = ['Aaj ki sales kitni hui?', 'Is mahine Ravi ka commission kitna hua?', 'Top services this month', 'Baki kitna hai?', 'Send win-back offer']

async function scroll() { await nextTick(); box.value?.scrollTo({ top: 99999, behavior: 'smooth' }) }
async function ask(text: string, voice = false) {
  if (!text.trim() || busy.value) return
  log.value.push({ me: true, text: (voice ? '🎤 ' : '') + text }); q.value = ''; busy.value = true; scroll()
  try {
    const r = voice ? await assistantApi.voice(text, 'hi-IN') : await assistantApi.ask(text)
    log.value.push({ me: false, text: r.answer, buttons: r.buttons })
  } catch (e) { log.value.push({ me: false, text: '⚠️ ' + (e as ApiError).message }) }
  finally { busy.value = false; scroll() }
}
async function act(id: string, idx: number) {
  const [kind, cid] = id.split(':'); log.value[idx].buttons = []
  try {
    if (kind === 'send') { const c = await marketingApi.send(Number(cid)); log.value.push({ me: false, text: `✅ Sent to ${c.stats.sent} customers (${c.stats.failed} failed).` }) }
    else { await assistantApi.skip(Number(cid)); log.value.push({ me: false, text: 'Okay, skipped.' }) }
  } catch (e) { log.value.push({ me: false, text: '⚠️ ' + (e as ApiError).message }) }
  scroll()
}

// Voice: the browser's speech recognition produces the transcript; the server's speech-to-text provider is swappable later.
const SR = (window as unknown as { SpeechRecognition?: new () => SpeechRec; webkitSpeechRecognition?: new () => SpeechRec })
interface SpeechRec { lang: string; onresult: (e: { results: { 0: { 0: { transcript: string } } } }) => void; onend: () => void; onerror: () => void; start(): void; stop(): void }
const Rec = SR.SpeechRecognition ?? SR.webkitSpeechRecognition
const speechOk = !!Rec
const listening = ref(false)
let rec: SpeechRec | null = null
function toggleMic() {
  if (listening.value) return rec?.stop()
  rec = new Rec!(); rec.lang = 'hi-IN'
  rec.onresult = (e) => ask(e.results[0][0].transcript, true)
  rec.onend = () => (listening.value = false); rec.onerror = () => (listening.value = false)
  listening.value = true; rec.start()
}
</script>

<style scoped>
.assistant { height: 460px; overflow: hidden; }
.head { background: linear-gradient(120deg, #6d28d9, #ec4899); color: #fff; }
.body { overflow-y: auto; background: rgba(var(--v-theme-on-surface), 0.03); }
.msg { display: flex; margin-bottom: 8px; } .msg.me { justify-content: flex-end; }
.bubble { max-width: 88%; padding: 8px 12px; border-radius: 12px; background: rgb(var(--v-theme-surface)); border: 1px solid rgba(var(--v-border-color), 0.15); }
.me .bubble { background: rgb(var(--v-theme-primary)); color: #fff; border: 0; }
</style>
