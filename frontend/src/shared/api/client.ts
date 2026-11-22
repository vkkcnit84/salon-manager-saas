import { useAuthStore } from '../../stores/auth'
import { useShopStore } from '../../stores/shop'

export class ApiError extends Error {
  status: number
  errors: Record<string, string[]>

  constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

/** Single transport: adds bearer token and X-Shop-Id to every call. */
/** Absolute API origin for the Android app (the WebView has no dev proxy). Empty in the browser: same-origin /api. */
export const API_BASE = (import.meta.env.VITE_API_BASE as string | undefined) ?? ''

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const shop = useShopStore()
  const auth = useAuthStore()
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  if (init.body) headers.set('Content-Type', 'application/json')
  if (auth.token) headers.set('Authorization', `Bearer ${auth.token}`)
  if (shop.shopId !== null && !headers.has('X-Shop-Id')) headers.set('X-Shop-Id', String(shop.shopId))

  let res: Response
  try {
    res = await fetch(`${API_BASE}/api/v1${path}`, { ...init, headers })
  } catch {
    throw new ApiError(0, 'You are offline.') // status 0 = no connection; callers may queue the action
  }
  const body = await res.json().catch(() => ({}))
  if (res.status === 401 && auth.token) auth.clear()
  if (!res.ok) throw new ApiError(res.status, body.message ?? res.statusText, body.errors)
  return body as T
}

/** Authenticated file download (exports): the token cannot ride on a plain <a href>. */
export async function download(path: string, filename: string): Promise<void> {
  const shop = useShopStore()
  const auth = useAuthStore()
  const headers = new Headers({ Accept: '*/*' })
  if (auth.token) headers.set('Authorization', `Bearer ${auth.token}`)
  if (shop.shopId !== null) headers.set('X-Shop-Id', String(shop.shopId))
  const res = await fetch(`${API_BASE}/api/v1${path}`, { headers })
  if (!res.ok) {
    const body = await res.json().catch(() => ({}))
    throw new ApiError(res.status, body.message ?? res.statusText, body.errors)
  }
  const a = document.createElement('a')
  a.href = URL.createObjectURL(await res.blob())
  a.download = filename
  a.click()
  URL.revokeObjectURL(a.href)
}
