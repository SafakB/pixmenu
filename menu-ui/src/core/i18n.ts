/**
 * Arayüz metinleri. Menü içeriği (ürün/kategori adları) API'den çevrili gelir; burada yalnızca
 * düğme/etiket metinleri vardır. Sözlüğü olmayan dil İngilizceye düşer.
 */
const tr = {
  search: 'Menüde ara',
  clearSearch: 'Aramayı temizle',
  noResults: 'Aramanızla eşleşen ürün yok.',
  categories: 'Kategoriler',
  productCount: '{n} ürün',
  productCountOne: '{n} ürün',
  allergens: 'Alerjenler',
  contains: 'İçerir',
  mayContain: 'Eser miktarda içerebilir',
  chooseBranch: 'Şube seçin',
  allBranches: 'Tüm şubeler',
  back: 'Geri',
  close: 'Kapat',
  language: 'Dil',
  call: 'Ara',
  notFoundTitle: 'Menü bulunamadı',
  notFoundText: 'Aradığınız menü yayında değil ya da adres hatalı.',
  loadError: 'Menü yüklenemedi. İnternet bağlantınızı kontrol edin.',
  retry: 'Tekrar dene',
  emptyMenu: 'Menü henüz hazırlanıyor.',
  loading: 'Menü yükleniyor…',
}

export type MessageKey = keyof typeof tr

const en: Record<MessageKey, string> = {
  search: 'Search the menu',
  clearSearch: 'Clear search',
  noResults: 'No items match your search.',
  categories: 'Categories',
  productCount: '{n} items',
  productCountOne: '{n} item',
  allergens: 'Allergens',
  contains: 'Contains',
  mayContain: 'May contain traces',
  chooseBranch: 'Choose a location',
  allBranches: 'All locations',
  back: 'Back',
  close: 'Close',
  language: 'Language',
  call: 'Call',
  notFoundTitle: 'Menu not found',
  notFoundText: 'This menu is not available or the address is wrong.',
  loadError: 'The menu could not be loaded. Please check your connection.',
  retry: 'Try again',
  emptyMenu: 'The menu is being prepared.',
  loading: 'Loading menu…',
}

const messages: Record<string, Record<MessageKey, string>> = { tr, en }

export function translate(lang: string, key: MessageKey, params?: Record<string, string | number>): string {
  const dictionary = messages[lang.toLowerCase().split('-')[0] ?? ''] ?? en
  let text = dictionary[key]

  if (params) {
    for (const [name, value] of Object.entries(params)) {
      text = text.split(`{${name}}`).join(String(value))
    }
  }

  return text
}
