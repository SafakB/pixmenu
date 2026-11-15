import { describe, expect, it } from 'vitest'
import { formatPrice } from './format'

describe('formatPrice', () => {
  it('dile göre para birimi biçimler', () => {
    expect(formatPrice('129.90', 'TRY', 'tr')).toBe('₺129,90')
    expect(formatPrice('1499.90', 'TRY', 'tr')).toBe('₺1.499,90')
    expect(formatPrice('12.50', 'EUR', 'en')).toBe('€12.50')
    expect(formatPrice('310.90', 'TRY', 'en')).toBe('₺310.90')
  })

  it('sayı olmayan fiyatı olduğu gibi döndürür', () => {
    expect(formatPrice('', 'TRY', 'tr')).toBe('')
    expect(formatPrice('abc', 'TRY', 'tr')).toBe('abc')
  })

  it('geçersiz dil etiketinde yine biçimler, geçersiz para biriminde düz metne düşer', () => {
    expect(formatPrice('10.00', 'USD', 'x_invalid_!!')).toContain('10')
    expect(formatPrice('10.00', 'XXXX', 'tr')).toBe('10.00 XXXX')
  })
})
