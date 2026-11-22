import { defineStore } from 'pinia'

const KEY = 'salon.token'

export interface Tenant {
  id: number
  business_name: string
  onboarding_completed_at: string | null
  plan?: { code: string; name: string; max_shops: number; max_staff: number }
}
export interface Me {
  id: number
  name: string
  mobile: string | null
  email: string | null
  is_owner: boolean
  is_super_admin?: boolean
  tenant_id: number | null
  tenant: Tenant | null
}

function read(): string | null {
  try {
    return localStorage.getItem(KEY)
  } catch {
    return null
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({ token: read(), me: null as Me | null }),
  getters: {
    isLoggedIn: (s) => !!s.token,
    needsBusiness: (s) => !!s.me && !s.me.tenant_id,
    needsOnboarding: (s) => !!s.me?.tenant && !s.me.tenant.onboarding_completed_at,
  },
  actions: {
    setToken(token: string | null) {
      this.token = token
      try {
        token ? localStorage.setItem(KEY, token) : localStorage.removeItem(KEY)
      } catch {
        /* ignore */
      }
    },
    clear() {
      this.setToken(null)
      this.me = null
    },
  },
})
