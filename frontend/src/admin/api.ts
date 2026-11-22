import { api } from '../shared/api/client'

export interface Overview { tenants: number; active: number; trial: number; suspended: number; shops: number; users: number; invoices: number; gmv_paise: number; mrr_paise: number }
export interface PlanRow { id: number; code: string; name: string; price_paise: number; max_shops: number; max_staff: number }
export interface TenantRow {
  id: number; business_name: string; status: 'trial' | 'active' | 'suspended'; plan: { id: number; code: string; name: string } | null
  trial_ends_at: string | null; created_at: string; owner: { name: string; email: string | null; mobile: string | null } | null
  shops: number; users: number; invoices: number; sales_paise: number
}
export interface RolesPayload { permissions: string[]; roles: { name: string; locked: boolean; permissions: string[] }[] }

const put = <T>(path: string, body: unknown) => api<T>(path, { method: 'PUT', body: JSON.stringify(body) })
export const adminApi = {
  overview: () => api<Overview>('/admin/overview'),
  tenants: () => api<TenantRow[]>('/admin/tenants'),
  plans: () => api<PlanRow[]>('/admin/plans'),
  updateTenant: (id: number, d: { plan_id?: number; status?: string }) => put<TenantRow>(`/admin/tenants/${id}`, d),
  roles: () => api<RolesPayload>('/admin/roles'),
  updateRole: (name: string, permissions: string[]) => put<RolesPayload>(`/admin/roles/${name}/permissions`, { permissions }),
}
