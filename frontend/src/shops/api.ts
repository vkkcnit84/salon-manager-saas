import { api } from '../shared/api/client'

export interface ShopSettings {
  opening_hours: { open: string; close: string }
  weekly_off: number[]
  rounding: 'none' | 'nearest_rupee'
  bill_footer: string | null
  commission_basis: { after_discount: boolean; before_gst: boolean }
  low_credit_alert: number
}
export interface Shop {
  id: number
  name: string
  slug: string
  address: string | null
  phone: string | null
  gstin: string | null
  invoice_prefix: string | null
  tax_mode: 'inclusive' | 'exclusive'
  timezone: string
  settings: ShopSettings
}
export type ShopInput = Partial<Omit<Shop, 'id' | 'slug' | 'settings'>> & { name: string; settings?: Partial<ShopSettings> }

export const shopsApi = {
  list: () => api<Shop[]>('/shops'),
  create: (data: ShopInput) => api<Shop>('/shops', { method: 'POST', body: JSON.stringify(data) }),
  update: (id: number, data: Partial<ShopInput>) => api<Shop>(`/shops/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  updateSettings: (id: number, settings: Partial<ShopSettings>) =>
    api<Shop>(`/shops/${id}/settings`, { method: 'PUT', body: JSON.stringify({ settings }) }),
}

export interface TeamUser {
  id: number
  name: string
  mobile: string | null
  roles: { name: string }[]
}
export const usersApi = {
  list: () => api<TeamUser[]>('/users'),
  create: (data: { name: string; mobile: string; shop_id: number; role: string }) =>
    api<TeamUser>('/users', { method: 'POST', body: JSON.stringify(data) }),
}
