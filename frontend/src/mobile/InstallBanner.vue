<template>
  <v-btn v-if="event && !dismissed" variant="tonal" color="primary" prepend-icon="mdi-cellphone-arrow-down" class="mr-2" @click="install">
    Install app
    <v-btn icon="mdi-close" size="x-small" variant="text" class="ml-1" aria-label="Dismiss" @click.stop="dismiss" />
  </v-btn>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'

interface BeforeInstall extends Event { prompt(): Promise<void>; userChoice: Promise<{ outcome: string }> }
const KEY = 'salon.installDismissed'
const event = ref<BeforeInstall | null>(null)
const dismissed = ref(false)

onMounted(() => {
  try { dismissed.value = localStorage.getItem(KEY) === '1' } catch { /* storage unavailable */ }
  window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); event.value = e as BeforeInstall })
  window.addEventListener('appinstalled', () => (event.value = null))
})
async function install() {
  await event.value?.prompt()
  await event.value?.userChoice
  event.value = null
}
function dismiss() {
  dismissed.value = true
  try { localStorage.setItem(KEY, '1') } catch { /* ignore */ }
}
</script>
