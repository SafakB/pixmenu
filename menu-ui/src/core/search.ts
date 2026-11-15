import type { Category } from '../api/types'

/**
 * Arama için metni sadeleştirir: yerel küçük harf (Türkçe İ/ı doğru), aksanlar atılır,
 * ı → i. Böylece "corba", "ÇORBA" ve "Çorba" aynı sonucu verir.
 */
export function normalizeSearch(text: string, locale: string): string {
  let lowered: string

  try {
    lowered = text.toLocaleLowerCase(locale)
  } catch {
    lowered = text.toLowerCase()
  }

  return lowered
    .normalize('NFD')
    .replace(/\p{M}+/gu, '')
    .replace(/ı/g, 'i')
    .replace(/\s+/g, ' ')
    .trim()
}

/** Ürün adı veya açıklaması aramayı içeren ürünler; ürünü kalmayan kategoriler atılır. */
export function filterCategories(categories: Category[], query: string, locale: string): Category[] {
  const needle = normalizeSearch(query, locale)

  if (needle === '') {
    return categories
  }

  const result: Category[] = []

  for (const category of categories) {
    const categoryMatches = normalizeSearch(category.name, locale).includes(needle)
    const products = categoryMatches
      ? category.products
      : category.products.filter((product) =>
          normalizeSearch(`${product.name} ${product.description ?? ''}`, locale).includes(needle),
        )

    if (products.length > 0) {
      result.push({ ...category, products })
    }
  }

  return result
}
