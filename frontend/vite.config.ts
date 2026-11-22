import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'
import vuetify from 'vite-plugin-vuetify'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    vue(),
    vuetify({ autoImport: true }),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['icon.svg'],
      manifest: {
        name: 'Salon Manager',
        short_name: 'Salon',
        id: '/',
        start_url: '/pos', // opens straight on the billing counter
        scope: '/',
        display: 'standalone',
        orientation: 'any',
        theme_color: '#7c3aed',
        background_color: '#ffffff',
        categories: ['business', 'productivity'],
        icons: [
          { src: 'pwa-192.png', sizes: '192x192', type: 'image/png' },
          { src: 'pwa-512.png', sizes: '512x512', type: 'image/png' },
          { src: 'pwa-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
        shortcuts: [{ name: 'New bill', url: '/pos' }, { name: 'Reports', url: '/reports' }],
      },
      // App shell only. API calls are never cached, so numbers are always live (offline bills go through the IndexedDB queue instead).
      workbox: { navigateFallback: '/index.html', navigateFallbackDenylist: [/^\/api\//], runtimeCaching: [] },
    }),
  ],
  server: { proxy: { '/api': 'http://localhost:8000' } },
  test: { environment: 'jsdom', globals: true, exclude: ['e2e/**', 'node_modules/**'] },
})
