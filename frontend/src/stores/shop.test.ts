import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useShopStore } from './shop'

describe('shop store', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('switches shop and supports All shops (null)', () => {
    const s = useShopStore()
    s.setShop(3)
    expect(s.shopId).toBe(3)
    s.setShop(null)
    expect(s.shopId).toBeNull()
  })
})
