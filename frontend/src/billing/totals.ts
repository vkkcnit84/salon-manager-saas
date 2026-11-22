/** Client-side preview that mirrors the server InvoiceCalculator (paise, GST exclusive/inclusive). Server is authoritative. */
export interface Line { unit: number; qty: number; gst_bps: number }

export function previewTotals(lines: Line[], taxMode: 'inclusive' | 'exclusive', discount: { type: 'flat' | 'percent'; value: number } | null, tip = 0) {
  const gross = lines.map((l) => l.unit * l.qty)
  const sum = gross.reduce((a, b) => a + b, 0)
  let off = 0
  if (discount && sum > 0) off = Math.min(sum, discount.type === 'percent' ? Math.floor((sum * discount.value * 2 + 10000) / 20000) : discount.value)
  let tax = 0
  let payable = 0
  lines.forEach((l, i) => {
    const net = gross[i] - (sum ? Math.floor((off * gross[i]) / sum) : 0)
    if (taxMode === 'inclusive') {
      payable += net
      tax += net - Math.floor((net * 10000) / (10000 + l.gst_bps))
    } else {
      const t = Math.floor((net * l.gst_bps * 2 + 10000) / 20000)
      tax += t
      payable += net + t
    }
  })
  const exact = payable + tip
  const total = Math.floor((exact + 50) / 100) * 100
  return { subtotal: sum, discount: off, tax, tip, rounding: total - exact, total }
}
