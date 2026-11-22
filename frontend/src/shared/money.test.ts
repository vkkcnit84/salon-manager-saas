import { describe, expect, it } from 'vitest'
import { formatPaise } from './money'

describe('formatPaise', () => {
  it('uses Indian grouping', () => {
    expect(formatPaise(12345600)).toBe('₹ 1,23,456.00')
    expect(formatPaise(99950)).toBe('₹ 999.50')
    expect(formatPaise(123456789)).toBe('₹ 12,34,567.89')
  })
})
