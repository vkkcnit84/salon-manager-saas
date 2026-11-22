/**
 * ESC/POS receipt builder for 58 mm (32 chars) and 80 mm (48 chars) thermal printers.
 * `printReceipt` hands the bytes to a native Bluetooth printer bridge when one is registered
 * (window.SalonPrinter, added with the Android plugin) and otherwise falls back to the browser print dialog.
 */
import { formatPaise } from '../shared/money'

export interface ReceiptData {
  shop: string; number: string; date: string; customer?: string
  lines: { name: string; qty: number; total_paise: number }[]
  subtotal_paise: number; discount_paise: number; tax_paise: number; total_paise: number; footer?: string
}

const enc = new TextEncoder()
const ESC = 0x1b, GS = 0x1d
const cmd = (...b: number[]) => Uint8Array.from(b)

export function buildReceipt(r: ReceiptData, width: 32 | 48 = 32): Uint8Array {
  const out: number[] = []
  const push = (...parts: Uint8Array[]) => parts.forEach((p) => out.push(...p))
  const money = (p: number) => formatPaise(p).replace('₹ ', 'Rs ') // most thermal code pages lack the rupee glyph
  const row = (l: string, rt: string) => l.slice(0, width - rt.length - 1).padEnd(width - rt.length) + rt + '\n'
  const line = '-'.repeat(width) + '\n'

  push(cmd(ESC, 0x40), cmd(ESC, 0x61, 1), cmd(ESC, 0x45, 1), enc.encode(r.shop + '\n'), cmd(ESC, 0x45, 0), enc.encode(`Bill ${r.number}\n${r.date}\n`))
  if (r.customer) push(enc.encode(r.customer + '\n'))
  push(cmd(ESC, 0x61, 0), enc.encode(line))
  r.lines.forEach((l) => push(enc.encode(row(`${l.qty} x ${l.name}`, money(l.total_paise)))))
  push(enc.encode(line), enc.encode(row('Subtotal', money(r.subtotal_paise))))
  if (r.discount_paise) push(enc.encode(row('Discount', '-' + money(r.discount_paise))))
  push(enc.encode(row('GST', money(r.tax_paise))), cmd(ESC, 0x45, 1), enc.encode(row('TOTAL', money(r.total_paise))), cmd(ESC, 0x45, 0))
  push(cmd(ESC, 0x61, 1), enc.encode(`\n${r.footer ?? 'Thank you, visit again!'}\n\n\n`), cmd(GS, 0x56, 0x42, 0x00))
  return Uint8Array.from(out)
}

declare global { interface Window { SalonPrinter?: { write(bytes: Uint8Array): Promise<void> } } }
export async function printReceipt(r: ReceiptData, width: 32 | 48 = 32): Promise<void> {
  if (window.SalonPrinter) return window.SalonPrinter.write(buildReceipt(r, width))
  window.print()
}
