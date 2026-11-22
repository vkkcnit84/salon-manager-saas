import { api } from '../shared/api/client'

export interface Totals { sales: number; bills: number; avg_bill: number; tax: number; discount: number; tips: number; collected: number; due: number; customers: number }
export interface Day { date: string; sales: number; bills: number }
export interface TopItem { name: string; qty: number; revenue: number }
export interface Method { method: string; amount: number; count: number }
export interface StaffRow { staff_id: number; name: string; services: number; revenue: number; commission: number }
export interface SalesReport { from: string; to: string; totals: Totals; daily: Day[]; top_services: TopItem[]; top_products: TopItem[]; payment_methods: Method[] }
export interface DueRow { number: string; date: string; customer: string; mobile: string | null; total: number; due: number }
export interface Dashboard {
  today: Totals; yesterday: Totals; month: Totals
  last30: { totals: Totals; previous_sales: number; daily: Day[] }
  top_services: TopItem[]; payment_methods: Method[]; staff: StaffRow[]
  recent: { number: string; customer: string; total: number; status: string; at: string }[]
  dues_total: number
  shop_comparison: { shop_id: number; name: string; sales: number; bills: number }[]
  alerts: { type: string; level: 'info' | 'warning'; text: string; link: string }[]
}

const qs = (from: string, to: string) => `?from=${from}&to=${to}`
export const reportsApi = {
  dashboard: () => api<Dashboard>('/reports/dashboard'),
  sales: (from: string, to: string) => api<SalesReport>(`/reports/sales${qs(from, to)}`),
  staff: (from: string, to: string) => api<StaffRow[]>(`/reports/staff${qs(from, to)}`),
  dues: () => api<DueRow[]>('/reports/dues'),
}

/** Paise → plain rupees for CSV (no symbol, no grouping so Excel parses it). */
export const rupees = (p: number) => (p / 100).toFixed(2)

export function downloadCsv(filename: string, rows: (string | number)[][]) {
  const esc = (v: string | number) => `"${String(v).replace(/"/g, '""')}"`
  const blob = new Blob(['﻿' + rows.map((r) => r.map(esc).join(',')).join('\n')], { type: 'text/csv;charset=utf-8' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = filename
  a.click()
  URL.revokeObjectURL(a.href)
}
