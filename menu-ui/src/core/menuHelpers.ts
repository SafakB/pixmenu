import { computed } from 'vue'
import type { Allergen, Menu, Product } from '../api/types'
import { formatPrice } from './format'
import { translate, type MessageKey } from './i18n'

/** Şablon ve bileşenlerin ortak yardımcıları (dil, çeviri, fiyat, alerjen sözlüğü). */
export function useMenuHelpers(menu: () => Menu) {
  const lang = computed(() => menu().language.code)
  const allergensById = computed(() => new Map<number, Allergen>(menu().allergens.map((a) => [a.id, a])))

  const t = (key: MessageKey, params?: Record<string, string | number>): string => translate(lang.value, key, params)
  const price = (product: Product): string => formatPrice(product.price, menu().restaurant.currency, lang.value)

  return { lang, t, price, allergensById }
}
