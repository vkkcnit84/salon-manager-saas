import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore, type Me } from './auth'

const me = (tenant: Me['tenant'], tenant_id: number | null): Me => ({ id: 1, name: 'A', mobile: null, email: null, is_owner: true, tenant_id, tenant })

describe('auth store routing flags', () => {
  beforeEach(() => { localStorage.clear(); setActivePinia(createPinia()) })

  it('flags business step for a user without tenant', () => {
    const a = useAuthStore()
    a.me = me(null, null)
    expect(a.needsBusiness).toBe(true)
  })
  it('flags onboarding until completed', () => {
    const a = useAuthStore()
    a.me = me({ id: 1, business_name: 'x', onboarding_completed_at: null }, 1)
    expect(a.needsOnboarding).toBe(true)
    a.me = me({ id: 1, business_name: 'x', onboarding_completed_at: '2026-01-01' }, 1)
    expect(a.needsOnboarding).toBe(false)
  })
  it('persists and clears the token', () => {
    const a = useAuthStore()
    a.setToken('t')
    expect(localStorage.getItem('salon.token')).toBe('t')
    a.clear()
    expect(a.isLoggedIn).toBe(false)
  })
})
