import { api } from '../shared/api/client'

export interface Service { id: number; name: string; price_paise: number; gst_bps: number; duration_min: number; active: boolean; commission_type?: string | null; commission_value?: number | null }
export interface Product { id: number; name: string; price_paise: number; gst_bps: number; stock: number; active: boolean }
export interface Staff { id: number; name: string; mobile: string | null; title: string | null; active: boolean; commission_type?: string; commission_value?: number }
export interface Customer { id: number; name: string; mobile: string; consent_whatsapp?: boolean; opted_out_at?: string | null; birthday?: string | null }
export interface Invoice { id: number; number: string; status: string; total_paise: number; paid_paise: number; due_paise: number; created_at: string; customer?: Customer | null; items?: { name: string; qty: number; total_paise: number }[] }
export interface InvoiceInput {
  customer_id?: number | null
  items: { type: 'service' | 'product'; id: number; qty?: number; staff_id?: number | null }[]
  discount?: { type: 'flat' | 'percent'; value: number } | null
  tip?: number
  payments: { method: 'cash' | 'upi' | 'card'; amount: number }[]
  idempotency_key: string
  coupon_code?: string | null
}

const post = <T>(path: string, body: unknown) => api<T>(path, { method: 'POST', body: JSON.stringify(body) })
const put = <T>(path: string, body: unknown) => api<T>(path, { method: 'PUT', body: JSON.stringify(body) })

export const catalogApi = {
  services: () => api<Service[]>('/services'),
  createService: (d: Partial<Service>) => post<Service>('/services', d),
  updateService: (id: number, d: Partial<Service>) => put<Service>(`/services/${id}`, d),
  products: () => api<Product[]>('/products'),
  createProduct: (d: Partial<Product>) => post<Product>('/products', d),
}
export const staffApi = {
  list: () => api<Staff[]>('/staff'),
  create: (d: Partial<Staff> & { shop_ids?: number[] }) => post<Staff>('/staff', d),
}
export const customersApi = {
  search: (q: string) => api<{ data: Customer[] }>(`/customers?q=${encodeURIComponent(q)}`),
  create: (d: { name: string; mobile: string; consent_whatsapp?: boolean; birthday?: string | null }) => post<Customer>('/customers', d),
  update: (id: number, d: Record<string, unknown>) => put<Customer>(`/customers/${id}`, d),
}
export const couponsApi = { validate: (code: string) => api<{ code: string; type: 'percent' | 'flat'; value: number }>(`/coupons/validate?code=${encodeURIComponent(code)}`) }
export const invoicesApi = {
  list: () => api<{ data: Invoice[] }>('/invoices'),
  create: (d: InvoiceInput) => post<Invoice>('/invoices', d),
}
