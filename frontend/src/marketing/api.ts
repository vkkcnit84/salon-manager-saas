import { api } from '../shared/api/client'

export interface Template { id: number; channel: 'whatsapp' | 'sms'; name: string; category: 'marketing' | 'utility'; body: string; status: string }
export interface Stats { total: number; sent: number; delivered: number; read: number; failed: number; queued: number; credits_spent: number; attributed_revenue_paise: number }
export interface Campaign { id: number; name: string; channel: string; status: string; sent_at: string | null; created_at: string; template?: { name: string }; stats: Stats; audience: Record<string, number | boolean | string> | null }
export interface Estimate { audience: number; credits_needed: number; balance: number; sufficient: boolean }
export interface Automation { trigger: string; active: boolean; delay_hours: number; template_id: number | null }
export interface Coupon { id: number; code: string; type: 'percent' | 'flat'; value: number; usage_limit: number | null; used: number; expires_at: string | null; active: boolean }
export interface CreditsInfo { balance: number; packs: { code: string; credits: number; price_paise: number }[]; ledger: { id: number; type: string; credits: number; balance_after: number; reference: string | null; created_at: string }[]; payment_driver: string }
export interface MessageRow { id: number; channel: string; direction: 'in' | 'out'; to: string; template: string | null; body: string; status: string; created_at: string }

const post = <T>(p: string, b: unknown = {}) => api<T>(p, { method: 'POST', body: JSON.stringify(b) })
const put = <T>(p: string, b: unknown) => api<T>(p, { method: 'PUT', body: JSON.stringify(b) })

export const marketingApi = {
  templates: () => api<Template[]>('/marketing/templates'),
  createTemplate: (d: Partial<Template>) => post<Template>('/marketing/templates', d),
  campaigns: () => api<Campaign[]>('/marketing/campaigns'),
  estimate: (channel: string, audience: object) => post<Estimate>('/marketing/campaigns/estimate', { channel, audience }),
  createCampaign: (d: object) => post<Campaign>('/marketing/campaigns', d),
  send: (id: number) => post<Campaign>(`/marketing/campaigns/${id}/send`),
  report: (id: number) => api<{ campaign: Campaign; stats: Stats; failed: { customer: { name: string; mobile: string }; error: string }[] }>(`/marketing/campaigns/${id}/report`),
  automations: () => api<Automation[]>('/marketing/automations'),
  saveAutomation: (t: string, d: Partial<Automation>) => put<Automation>(`/marketing/automations/${t}`, d),
  coupons: () => api<Coupon[]>('/marketing/coupons'),
  createCoupon: (d: object) => post<Coupon>('/marketing/coupons', d),
  toggleCoupon: (id: number, active: boolean) => put<Coupon>(`/marketing/coupons/${id}`, { active }),
  credits: () => api<CreditsInfo>('/credits'),
  topup: (pack: string) => post<{ balance: number; reference: string }>('/credits/topup', { pack }),
  messages: () => api<MessageRow[]>('/marketing/messages'),
}

export interface BotReply { text: string; buttons?: { id: string; title: string }[] }
export interface Identity { label: string; mobile: string }
export const botApi = {
  identities: () => api<{ identities: Identity[]; checkin_codes: { shop: string; text: string }[]; whatsapp_number: string }>('/bot/identities'),
  simulate: (from: string, text: string, button?: string) => post<{ replies: BotReply[] }>('/bot/simulate', { from, text, button }),
  prefs: (nightly: boolean, nightly_time = '22:00') => put<{ nightly: boolean }>('/bot/prefs', { nightly, nightly_time }),
  sendSummary: () => post<{ message: string }>('/bot/send-summary'),
  waiting: () => api<{ id: number; customer: { id: number; name: string; mobile: string } }[]>('/waiting'),
  serve: (id: number, status: 'served' | 'left') => put(`/waiting/${id}`, { status }),
}

export interface Answer { answer: string; tool: string | null; language: string; buttons: { id: string; title: string }[]; cached: boolean; transcript?: string }
export const assistantApi = {
  ask: (question: string) => post<Answer>('/assistant/ask', { question }),
  voice: (transcript: string, language: string) => post<Answer>('/assistant/voice', { transcript, language }),
  skip: (id: number) => post(`/marketing/campaigns/${id}/skip`),
}

export interface Expense { id: number; shop_id: number; category: string; amount_paise: number; note: string | null; spent_on: string }
export const expensesApi = {
  list: () => api<{ categories: string[]; data: Expense[] }>('/expenses'),
  create: (d: object) => post<Expense>('/expenses', d),
  remove: (id: number) => api(`/expenses/${id}`, { method: 'DELETE' }),
}
