import { defineStore } from 'pinia'

const KEY = 'salon.shopId'

function read(): number | null {
  try {
    const v = localStorage.getItem(KEY)
    return v ? Number(v) : null
  } catch {
    return null
  }
}

/** Current shop context. `null` means the owner-only "All shops" view. */
export const useShopStore = defineStore('shop', {
  state: () => ({ shopId: read() }),
  actions: {
    setShop(id: number | null) {
      this.shopId = id
      try {
        id === null ? localStorage.removeItem(KEY) : localStorage.setItem(KEY, String(id))
      } catch {
        /* storage unavailable: keep in memory only */
      }
    },
  },
})
