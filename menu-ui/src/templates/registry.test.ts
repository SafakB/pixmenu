import { describe, expect, it, vi } from 'vitest'
import { DEFAULT_TEMPLATE, isTemplateCode, resolveTemplateCode } from './registry'

describe('resolveTemplateCode', () => {
  it('bilinen kodları olduğu gibi döndürür', () => {
    expect(resolveTemplateCode('standart')).toBe('standart')
    expect(resolveTemplateCode('kategori')).toBe('kategori')
  })

  it('bilinmeyen veya boş kodu varsayılana düşürür ve bir kez uyarır', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined)

    expect(resolveTemplateCode('modern')).toBe(DEFAULT_TEMPLATE)
    expect(resolveTemplateCode('modern')).toBe(DEFAULT_TEMPLATE)
    expect(resolveTemplateCode(null)).toBe(DEFAULT_TEMPLATE)
    expect(warn).toHaveBeenCalledTimes(1)

    warn.mockRestore()
  })

  it('nesne prototipindeki adları şablon saymaz', () => {
    expect(isTemplateCode('toString')).toBe(false)
    expect(isTemplateCode('__proto__')).toBe(false)
  })
})
