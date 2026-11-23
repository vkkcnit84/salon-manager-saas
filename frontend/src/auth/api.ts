import { api } from '../shared/api/client'
import type { Me } from '../stores/auth'

export interface TokenResponse {
  token: string
  user: Me
  needs_onboarding: boolean
}
const post = <T>(path: string, body: unknown) => api<T>(path, { method: 'POST', body: JSON.stringify(body) })

export const authApi = {
  sendOtp: (mobile: string) => post<{ message: string }>('/auth/otp/send', { mobile }),
  verifyOtp: (mobile: string, code: string) => post<TokenResponse>('/auth/otp/verify', { mobile, code }),
  login: (login: string, password: string) => post<TokenResponse>('/auth/login', { login, password }),
  register: (name: string, email: string, password: string) =>
    post<TokenResponse>('/auth/register', { name, email, password }),
  logout: () => post<{ message: string }>('/auth/logout', {}),
  me: () => api<Me>('/me'),
  updateProfile: (body: { name?: string; mobile?: string | null }) => api<Me>('/me', { method: 'PUT', body: JSON.stringify(body) }),
  createBusiness: (business_name: string) => post('/onboarding/business', { business_name }),
  completeOnboarding: () => post('/onboarding/complete', {}),
}
