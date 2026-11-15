import { describe, expect, it } from 'vitest'
import type { Category, Product } from '../api/types'
import { filterCategories, normalizeSearch } from './search'

const product = (id: number, name: string, description: string | null = null): Product => ({
  id,
  name,
  description,
  price: '10.00',
  image_url: null,
  thumb_url: null,
  allergens: [],
})

const categories: Category[] = [
  { id: 1, name: 'Çorbalar', description: null, image_url: null, products: [product(1, 'Mercimek Çorbası'), product(2, 'Ezogelin')] },
  { id: 2, name: 'Tatlılar', description: null, image_url: null, products: [product(3, 'Sütlaç', 'Fırında, tarçınlı'), product(4, 'Künefe')] },
]

describe('normalizeSearch', () => {
  it('Türkçe büyük/küçük harf ve aksanları sadeleştirir', () => {
    expect(normalizeSearch('İSTANBUL', 'tr')).toBe('istanbul')
    expect(normalizeSearch('Çorba', 'tr')).toBe('corba')
    expect(normalizeSearch('ışık', 'tr')).toBe('isik')
    expect(normalizeSearch('  Crème   brûlée ', 'en')).toBe('creme brulee')
  })
})

describe('filterCategories', () => {
  it('boş aramada listeyi aynen döndürür', () => {
    expect(filterCategories(categories, '  ', 'tr')).toBe(categories)
  })

  it('ürün adında aksansız arar ve boş kalan kategoriyi atar', () => {
    const result = filterCategories(categories, 'CORBASI', 'tr')

    expect(result.map((c) => c.id)).toEqual([1])
    expect(result[0]?.products.map((p) => p.id)).toEqual([1])
  })

  it('açıklamada da arar', () => {
    expect(filterCategories(categories, 'tarcin', 'tr')[0]?.products.map((p) => p.id)).toEqual([3])
  })

  it('kategori adı eşleşirse tüm ürünleri gösterir', () => {
    expect(filterCategories(categories, 'tatli', 'tr')[0]?.products).toHaveLength(2)
  })

  it('eşleşme yoksa boş liste', () => {
    expect(filterCategories(categories, 'pizza', 'tr')).toEqual([])
  })
})
