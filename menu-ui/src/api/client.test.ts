import { describe, expect, it } from 'vitest'
import { apiBase, siteUrl } from './client'

describe('apiBase', () => {
  it('sondaki eğik çizgiyi atar, boşsa aynı origin', () => {
    expect(apiBase('https://pixmenu.com/api/v1/')).toBe('https://pixmenu.com/api/v1')
    expect(apiBase('  ')).toBe('/api/v1')
    expect(apiBase(undefined)).toBe('/api/v1')
  })
})

describe('siteUrl', () => {
  it('yalnızca dolu parametreleri ekler ve kodlar', () => {
    expect(siteUrl('/api/v1', { restaurant: 'sirketadi', branch: 'merkez', lang: 'en' })).toBe(
      '/api/v1/site?restaurant=sirketadi&branch=merkez&lang=en',
    )
    expect(siteUrl('https://x.com/api/v1', { host: 'menu.sirketadi.com', branch: undefined })).toBe(
      'https://x.com/api/v1/site?host=menu.sirketadi.com',
    )
    expect(siteUrl('/api/v1', { restaurant: 'a&b' })).toBe('/api/v1/site?restaurant=a%26b')
  })
})
