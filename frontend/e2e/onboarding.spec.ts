import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'

const LOG = '../backend/storage/logs/laravel.log'
const lastOtp = () => [...readFileSync(LOG, 'utf8').matchAll(/login code is (\d{6})/g)].at(-1)![1]

test('owner signs up by OTP, onboards, switches shops and manages team', async ({ page }) => {
  const mobile = `9${Math.floor(100000000 + Math.random() * 899999999)}`

  await page.goto('/')
  await expect(page).toHaveURL(/\/login/)

  await page.getByLabel('Mobile number').fill(mobile)
  await page.getByRole('button', { name: 'Send OTP' }).click()
  await expect(page.getByLabel('6-digit code')).toBeVisible() // shown only after the API accepted the send
  await page.getByLabel('6-digit code').fill(lastOtp())
  await page.getByRole('button', { name: 'Verify and continue' }).click()

  // Wizard: business -> shop -> bill format -> finish
  await expect(page).toHaveURL(/\/onboarding/)
  await page.getByLabel('Business name').fill('E2E Salon')
  await page.getByRole('button', { name: 'Next' }).last().click()
  await page.getByLabel('Shop name').fill('E2E Shop One')
  await page.getByLabel('Invoice prefix').fill('E1-')
  await page.getByRole('button', { name: 'Next' }).last().click()
  await page.getByLabel('Bill footer text').fill('See you soon')
  await page.getByRole('button', { name: 'Next' }).last().click()
  await page.getByRole('button', { name: 'Start billing' }).click()
  await expect(page).toHaveURL(/localhost:5173\/$/)
  await expect(page.getByText('Dashboard')).toBeVisible()

  // Settings persisted
  await page.getByRole('link', { name: 'Settings' }).click()
  await expect(page.getByLabel('Shop name')).toHaveValue('E2E Shop One')
  await expect(page.getByLabel('Bill footer')).toHaveValue('See you soon')
  await page.getByLabel('Phone').fill('9123456789')
  await page.getByRole('button', { name: 'Save' }).click()
  await expect(page.getByText('Saved.')).toBeVisible()

  // Team: add a cashier
  await page.getByRole('link', { name: 'Team' }).click()
  await expect(page).toHaveURL(/\/team/)
  await expect(page.getByRole('heading', { name: 'Team' })).toBeVisible()
  await page.getByLabel('Name', { exact: true }).fill('Ravi Cashier')
  await page.getByLabel('Mobile').fill('9000011122')
  await page.getByRole('main').locator('.v-field', { hasText: 'Shop' }).click()
  await page.getByRole('option', { name: 'E2E Shop One' }).click()
  await page.getByRole('button', { name: 'Add team member' }).click()
  await expect(page.getByText('Ravi Cashier')).toBeVisible()

  // Reload keeps the session; sign out returns to login
  await page.reload()
  await expect(page.getByText('Team', { exact: true }).first()).toBeVisible()
  await page.getByRole('button', { name: 'Sign out' }).click()
  await expect(page).toHaveURL(/\/login/)
})
