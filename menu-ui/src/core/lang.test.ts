import { describe, expect, it } from 'vitest'
import { pickInitialLang } from './lang'

describe('pickInitialLang', () => {
  it('geçerli ?lang önceliklidir', () => {
    expect(pickInitialLang('en', 'tr')).toBe('en')
  })

  it('?lang yoksa veya geçersizse kayıtlı tercihe düşer', () => {
    expect(pickInitialLang(undefined, 'de')).toBe('de')
    expect(pickInitialLang('<script>', 'de')).toBe('de')
    expect(pickInitialLang(['en'], 'de')).toBe('de')
  })

  it('hiçbiri yoksa sunucunun seçmesi için undefined', () => {
    expect(pickInitialLang(null, null)).toBeUndefined()
    expect(pickInitialLang(undefined, 'bozuk değer')).toBeUndefined()
  })
})
