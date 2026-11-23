<template>
  <v-card class="pa-4 mb-6" variant="outlined">
    <div class="text-subtitle-1 font-weight-medium mb-1">My profile</div>
    <div class="text-body-2 text-medium-emphasis mb-3">
      Your own mobile number is used for WhatsApp updates such as the nightly summary. (The shop's phone is set below.)
    </div>
    <v-alert v-if="msg" :type="ok ? 'success' : 'error'" variant="tonal" density="compact" class="mb-3">{{ msg }}</v-alert>
    <v-row>
      <v-col cols="12" md="5"><v-text-field v-model="name" label="Your name" hide-details="auto" /></v-col>
      <v-col cols="12" md="5">
        <v-text-field v-model="mobile" label="Your mobile number" prefix="+91" inputmode="numeric" maxlength="10" hide-details="auto" />
      </v-col>
      <v-col cols="12" md="2" class="d-flex align-center">
        <v-btn color="primary" block :loading="busy" :disabled="!name || !/^[6-9]\d{9}$/.test(mobile)" @click="save">Save</v-btn>
      </v-col>
    </v-row>
  </v-card>
</template>

<script setup lang="ts">
import { ref, watchEffect } from 'vue'
import { authApi } from '../auth/api'
import { useAuthStore } from '../stores/auth'
import { ApiError } from '../shared/api/client'

const auth = useAuthStore()
const name = ref('')
const mobile = ref('')
const busy = ref(false)
const msg = ref('')
const ok = ref(true)

watchEffect(() => {
  name.value = auth.me?.name ?? ''
  mobile.value = auth.me?.mobile ?? ''
})

async function save() {
  busy.value = true
  msg.value = ''
  try {
    auth.me = await authApi.updateProfile({ name: name.value, mobile: mobile.value })
    ok.value = true
    msg.value = 'Profile saved.'
  } catch (e) {
    ok.value = false
    const err = e as ApiError
    msg.value = Object.values(err.errors ?? {})[0]?.[0] ?? err.message
  } finally {
    busy.value = false
  }
}
</script>
