<template>
  <div class="login-bg d-flex align-center justify-center">
  <v-card class="mx-4" width="440" elevation="8" border="0">
    <div class="text-center pt-8 pb-2">
      <v-avatar color="primary" size="56"><v-icon icon="mdi-content-cut" size="28" /></v-avatar>
      <div class="text-h5 font-weight-bold mt-3">Salon Manager</div>
      <div class="text-body-2 text-medium-emphasis">Sign in to run your salon</div>
    </div>
    <v-tabs v-model="tab" grow color="primary" class="mt-2">
      <v-tab value="otp">Mobile OTP</v-tab>
      <v-tab value="password">Email / password</v-tab>
    </v-tabs>
    <v-card-text>
      <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>

      <v-window v-model="tab">
        <v-window-item value="otp">
          <v-text-field v-model="mobile" label="Mobile number" prefix="+91" inputmode="numeric" maxlength="10" :disabled="otpSent" />
          <v-text-field v-if="otpSent" v-model="code" label="6-digit code" inputmode="numeric" maxlength="6" autofocus />
          <v-btn v-if="!otpSent" block color="primary" :loading="busy" :disabled="!/^[6-9]\d{9}$/.test(mobile)" @click="sendOtp">Send OTP</v-btn>
          <v-btn v-else block color="primary" :loading="busy" :disabled="code.length !== 6" @click="verify">Verify and continue</v-btn>
        </v-window-item>

        <v-window-item value="password">
          <v-text-field v-model="login" label="Email or mobile" />
          <v-text-field v-model="password" label="Password" type="password" @keyup.enter="passwordLogin" />
          <v-btn block color="primary" :loading="busy" :disabled="!login || !password" @click="passwordLogin">Sign in</v-btn>
        </v-window-item>
      </v-window>
    </v-card-text>
  </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { authApi, type TokenResponse } from './api'
import { useAuthStore } from '../stores/auth'
import { ApiError } from '../shared/api/client'

const router = useRouter()
const auth = useAuthStore()
const tab = ref('otp')
const mobile = ref('')
const code = ref('')
const otpSent = ref(false)
const login = ref('')
const password = ref('')
const busy = ref(false)
const error = ref('')

async function run(fn: () => Promise<void>) {
  busy.value = true
  error.value = ''
  try {
    await fn()
  } catch (e) {
    const err = e as ApiError
    error.value = Object.values(err.errors ?? {})[0]?.[0] ?? err.message
  } finally {
    busy.value = false
  }
}
const sendOtp = () => run(async () => { await authApi.sendOtp(mobile.value); otpSent.value = true })
const verify = () => run(async () => finish(await authApi.verifyOtp(mobile.value, code.value)))
const passwordLogin = () => run(async () => finish(await authApi.login(login.value, password.value)))

async function finish(res: TokenResponse) {
  auth.setToken(res.token)
  await router.replace('/')
}
</script>

<style scoped>
.login-bg { min-height: 100vh; background: linear-gradient(135deg, #7c3aed 0%, #ec4899 100%); }
</style>
