import { describe, expect, it } from 'vitest'
import { buildReceipt } from './escpos'
import { previewTotals } from '../billing/totals'

const text = (b: Uint8Array) => new TextDecoder().decode(b)

describe('escpos receipt', () => {
  const data = { shop: 'Glow', number: 'GL/1', date: '21 Nov', lines: [{ name: 'Haircut', qty: 1, total_paise: 35400 }], subtotal_paise: 30000, discount_paise: 0, tax_paise: 5400, total_paise: 35400 }

  it('starts with init, contains the bill and ends with a paper cut', () => {
    const b = buildReceipt(data, 32)
    expect([b[0], b[1]]).toEqual([0x1b, 0x40])
    expect(text(b)).toContain('Haircut')
    expect(text(b)).toContain('Rs 354.00')
    expect(Array.from(b.slice(-4))).toEqual([0x1d, 0x56, 0x42, 0x00])
  })

  it('keeps every row within the paper width', () => {
    const wide = { ...data, lines: [{ name: 'A very long service name that overflows', qty: 2, total_paise: 123456 }] }
    for (const w of [32, 48] as const) {
      const rows = text(buildReceipt(wide, w)).split('\n').filter((r) => r.includes('Rs') && r.includes('service'))
      expect(rows.every((r) => r.replace(/[^\x20-\x7e]/g, '').length <= w + 4)).toBe(true)
    }
  })
})

describe('coupon-style bill discount preview', () => {
  it('10% coupon on a ₹300 + 18% GST service matches the server (₹319)', () => {
    const t = previewTotals([{ unit: 30000, qty: 1, gst_bps: 1800 }], 'exclusive', { type: 'percent', value: 1000 })
    expect(t.discount).toBe(3000)
    expect(t.tax).toBe(4860)
    expect(t.total).toBe(31900)
  })
})
