/**
 * Tiny IndexedDB queue for bills created while offline. Each entry keeps its client UUID, which the API treats
 * as an idempotency key, so a retry can never create a duplicate invoice. Invoice numbers are assigned by the
 * server when the bill syncs; the counter shows a provisional reference until then.
 */
import { ref } from 'vue'
import { invoicesApi, type InvoiceInput } from '../billing/api'
import { api, ApiError } from '../shared/api/client'

export interface QueuedBill { key: string; shopId: number; payload: InvoiceInput; createdAt: number; error?: string }
const DB = 'salon-offline'
const STORE = 'bills'

function open(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB, 1)
    req.onupgradeneeded = () => req.result.createObjectStore(STORE, { keyPath: 'key' })
    req.onsuccess = () => resolve(req.result)
    req.onerror = () => reject(req.error)
  })
}
async function tx<T>(mode: IDBTransactionMode, fn: (s: IDBObjectStore) => IDBRequest<T>): Promise<T> {
  const db = await open()
  return new Promise((resolve, reject) => {
    const r = fn(db.transaction(STORE, mode).objectStore(STORE))
    r.onsuccess = () => resolve(r.result)
    r.onerror = () => reject(r.error)
  })
}

export const pendingCount = ref(0)
export const syncing = ref(false)

export const refreshCount = async () => { try { pendingCount.value = await tx('readonly', (s) => s.count()) } catch { pendingCount.value = 0 } }
export async function enqueue(b: QueuedBill) { await tx('readwrite', (s) => s.put(b)); await refreshCount() }
export const listQueued = () => tx<QueuedBill[]>('readonly', (s) => s.getAll())

/** True when the failure is "no connection" rather than a server answer. */
export const isOffline = (e: unknown) => !(e instanceof ApiError) || e.status === 0

export async function flush(): Promise<number> {
  if (syncing.value || !navigator.onLine) return 0
  syncing.value = true
  let done = 0
  try {
    for (const b of await listQueued()) {
      try {
        await api('/invoices', { method: 'POST', body: JSON.stringify({ ...b.payload, idempotency_key: b.key }), headers: { 'X-Shop-Id': String(b.shopId) } })
        await tx('readwrite', (s) => s.delete(b.key))
        done++
      } catch (e) {
        if (isOffline(e)) break // still offline: keep everything, try later
        // The server rejected it (validation, permissions): keep it visible instead of silently dropping a sale.
        await tx('readwrite', (s) => s.put({ ...b, error: (e as ApiError).message }))
      }
    }
  } finally {
    syncing.value = false
    await refreshCount()
  }
  return done
}

export function startAutoSync() {
  refreshCount()
  window.addEventListener('online', () => void flush())
  setInterval(() => void flush(), 30000)
}
void invoicesApi
