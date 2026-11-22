import { defineStore } from 'pinia'
import { shopsApi, type Shop } from '../shops/api'
import { useShopStore } from './shop'
import { useAuthStore } from './auth'

export const useShopsStore = defineStore('shops', {
  state: () => ({ shops: [] as Shop[], loaded: false }),
  actions: {
    /** Forget everything tied to the previous user (called on sign-out / session end). */
    reset() {
      this.shops = []
      this.loaded = false
      useShopStore().setShop(null)
    },
    async load() {
      this.shops = await shopsApi.list()
      this.loaded = true
      const ctx = useShopStore()
      // Keep selection valid: drop a stale id; default to the first shop when none is chosen.
      if (ctx.shopId !== null && !this.shops.some((s) => s.id === ctx.shopId)) ctx.setShop(null)
      // Owners may stay on "All shops"; everyone else always needs a concrete shop.
      const owner = useAuthStore().me?.is_owner
      if (ctx.shopId === null && this.shops.length && (this.shops.length === 1 || !owner)) ctx.setShop(this.shops[0]!.id)
    },
  },
})
