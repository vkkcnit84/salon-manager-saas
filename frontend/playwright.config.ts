import { defineConfig } from '@playwright/test'

export default defineConfig({
  testDir: 'e2e',
  timeout: 30_000,
  use: { baseURL: 'http://localhost:5173', trace: 'retain-on-failure' },
  webServer: [
    { command: 'cd ../backend && php artisan serve --port=8000', url: 'http://localhost:8000/up', reuseExistingServer: true },
    { command: 'npm run dev -- --port 5173', url: 'http://localhost:5173', reuseExistingServer: true },
  ],
})
