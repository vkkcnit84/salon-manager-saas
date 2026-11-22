import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createVuetify } from 'vuetify'
import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import App from './App.vue'
import { router } from './router'
import { initNative } from './mobile/native'

const pinia = createPinia()
createApp(App)
  .use(pinia)
  .use(createVuetify({
    defaults: { VCard: { rounded: 'lg', elevation: 0, border: true }, VBtn: { rounded: 'lg' }, VTextField: { variant: 'outlined', density: 'comfortable' } },
    theme: {
      defaultTheme: 'salon',
      themes: {
        salon: {
          dark: false,
          colors: { primary: '#7c3aed', secondary: '#ec4899', background: '#f6f4fb', surface: '#ffffff', success: '#16a34a', info: '#0ea5e9' },
        },
      },
    },
  }))
  .use(router)
  .mount('#app')

void initNative()
