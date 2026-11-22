/** Amounts are integer paise everywhere; format only at the UI edge. */
export function formatPaise(paise: number): string {
  const sign = paise < 0 ? '-' : ''
  const abs = Math.abs(paise)
  const rupees = String(Math.floor(abs / 100))
  const last3 = rupees.slice(-3)
  const rest = rupees.slice(0, -3)
  const grouped = rest ? `${rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',')},${last3}` : last3
  return `${sign}₹ ${grouped}.${String(abs % 100).padStart(2, '0')}`
}
