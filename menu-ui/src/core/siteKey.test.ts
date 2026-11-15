import { describe, expect, it } from 'vitest'
import { canonicalRedirect, resolveSiteKey, siteKeyId } from './siteKey'

describe('resolveSiteKey', () => {
  it('.env slug doluysa sabit restoran kullanır', () => {
    expect(resolveSiteKey(' SirketAdi ', 'menu.baska.com')).toEqual({ restaurant: 'sirketadi' })
  })

  it('.env boşsa host kullanır', () => {
    expect(resolveSiteKey('', 'Sirketadi.PixMenu.com')).toEqual({ host: 'sirketadi.pixmenu.com' })
    expect(resolveSiteKey(undefined, 'menu.sirketadi.com')).toEqual({ host: 'menu.sirketadi.com' })
  })

  it('kimlik slug veya host olur', () => {
    expect(siteKeyId({ restaurant: 'a' })).toBe('a')
    expect(siteKeyId({ host: 'a.b.com' })).toBe('a.b.com')
  })
})

describe('canonicalRedirect', () => {
  const host = { host: 'sirketadi.pixmenu.com' }

  it('host modunda farklı kanonik host’a yönlendirir', () => {
    expect(canonicalRedirect(host, 'https://menu.sirketadi.com', 'sirketadi.pixmenu.com')).toBe('https://menu.sirketadi.com')
  })

  it('aynı host’ta (port farkı dahil) yönlendirmez', () => {
    expect(canonicalRedirect(host, 'https://sirketadi.pixmenu.com', 'sirketadi.pixmenu.com')).toBeNull()
    expect(canonicalRedirect({ host: 'a.localhost' }, 'http://a.localhost:5173', 'a.localhost')).toBeNull()
  })

  it('sabit restoran modunda, kanonik yoksa veya geçersizse yönlendirmez', () => {
    expect(canonicalRedirect({ restaurant: 'sirketadi' }, 'https://menu.sirketadi.com', 'localhost')).toBeNull()
    expect(canonicalRedirect(host, null, 'sirketadi.pixmenu.com')).toBeNull()
    expect(canonicalRedirect(host, 'bozuk adres', 'sirketadi.pixmenu.com')).toBeNull()
  })
})
