import { describe, expect, it } from 'vitest'
import type { Menu, Site } from '../api/types'
import { documentTitle } from './meta'

const site = (branches: number, menuBranchName: string | null): Site => ({
  restaurant: { slug: 'r', name: 'Lezzet Durağı', logo_url: null },
  language: { code: 'tr', direction: 'ltr' },
  languages: [],
  branches: Array.from({ length: branches }, (_, i) => ({ slug: `s${i}`, code: `c${i}`, name: `Şube ${i}`, address: null })),
  canonical_url: null,
  menu: menuBranchName === null ? null : ({ branch: { name: menuBranchName } } as Menu),
})

describe('documentTitle', () => {
  it('çok şubede seçili şubeyi başa yazar', () => {
    expect(documentTitle(site(2, 'Kadıköy'))).toBe('Kadıköy · Lezzet Durağı')
  })

  it('tek şubede, şube seçiminde veya adsız şubede yalnızca restoran', () => {
    expect(documentTitle(site(1, 'Merkez'))).toBe('Lezzet Durağı')
    expect(documentTitle(site(3, null))).toBe('Lezzet Durağı')
    expect(documentTitle(site(2, ''))).toBe('Lezzet Durağı')
  })
})
