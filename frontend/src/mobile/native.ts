/** Capacitor glue. Everything here is a no-op in a normal browser, so the web/PWA build is unaffected. */
import { App } from '@capacitor/app'
import { Capacitor } from '@capacitor/core'
import { Preferences } from '@capacitor/preferences'
import { PushNotifications } from '@capacitor/push-notifications'
import { Share } from '@capacitor/share'
import { watch } from 'vue'
import { api } from '../shared/api/client'
import { useAuthStore } from '../stores/auth'

export const isNative = Capacitor.isNativePlatform()
const TOKEN_KEY = 'salon.token'

export async function initNative(): Promise<void> {
  if (!isNative) return
  const auth = useAuthStore()

  // Token lives in native Preferences (survives WebView storage clears); mirror it both ways.
  if (!auth.token) {
    const { value } = await Preferences.get({ key: TOKEN_KEY })
    if (value) auth.setToken(value)
  }
  watch(() => auth.token, (t) => (t ? Preferences.set({ key: TOKEN_KEY, value: t }) : Preferences.remove({ key: TOKEN_KEY })))

  // Android back button: go back, or leave the app at the root.
  App.addListener('backButton', ({ canGoBack }) => (canGoBack ? window.history.back() : App.exitApp()))

  // Push: register the FCM token with the API once the user is signed in.
  const register = async () => {
    if (!auth.token) return
    const perm = await PushNotifications.requestPermissions()
    if (perm.receive !== 'granted') return
    await PushNotifications.register()
  }
  PushNotifications.addListener('registration', (t) => void api('/devices', { method: 'POST', body: JSON.stringify({ token: t.value, platform: 'android' }) }).catch(() => undefined))
  watch(() => auth.token, (t) => t && void register(), { immediate: true })
}

/** Share an invoice link/PDF through the Android share sheet (falls back to the Web Share API / wa.me). */
export async function shareInvoice(number: string, url: string): Promise<void> {
  const text = `Your bill ${number}`
  if (isNative) return void (await Share.share({ title: text, text, url, dialogTitle: 'Send bill' }))
  if (navigator.share) return void (await navigator.share({ title: text, text, url }))
  window.open(`https://wa.me/?text=${encodeURIComponent(`${text} ${url}`)}`, '_blank')
}
