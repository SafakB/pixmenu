import { describe, expect, it } from 'vitest'
import { translate } from './i18n'

describe('translate', () => {
  it('dil koduna göre metin döndürür', () => {
    expect(translate('tr', 'back')).toBe('Geri')
    expect(translate('en', 'back')).toBe('Back')
  })

  it('bölge etiketini yok sayar, sözlüğü olmayan dili İngilizceye düşürür', () => {
    expect(translate('tr-TR', 'close')).toBe('Kapat')
    expect(translate('ar', 'close')).toBe('Close')
  })

  it('parametreleri yerleştirir', () => {
    expect(translate('tr', 'productCount', { n: 7 })).toBe('7 ürün')
    expect(translate('en', 'productCountOne', { n: 1 })).toBe('1 item')
    expect(translate('en', 'productCount', { n: 3 })).toBe('3 items')
  })
})
