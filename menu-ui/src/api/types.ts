/**
 * PixMenu API sözleşmesi — docs/VIEW_CONTRACT.md "Herkese açık menü" ve "Menü sitesi API".
 * Backend (app/Services/MenuService.php, SiteService.php) ile birebir aynı tutulmalıdır.
 */

export type TextDirection = 'ltr' | 'rtl'

export interface CurrentLanguage {
  code: string
  direction: TextDirection
}

export interface LanguageOption {
  code: string
  native_name: string
}

export interface Allergen {
  id: number
  code: string
  name: string
  icon_url: string | null
}

export interface AllergenRef {
  id: number
  level: 'contains' | 'may_contain'
}

export interface Product {
  id: number
  name: string
  description: string | null
  /** Ondalık string ("129.90"); yalnızca gösterim için sayıya çevrilir. */
  price: string
  image_url: string | null
  thumb_url: string | null
  allergens: AllergenRef[]
}

export interface Category {
  id: number
  name: string
  description: string | null
  image_url: string | null
  products: Product[]
}

/** GET /api/v1/menu/{code} `data` yapısı. */
export interface Menu {
  restaurant: {
    name: string
    description: string | null
    logo_url: string | null
    /** ISO 4217 (TRY, EUR…) */
    currency: string
  }
  branch: {
    code: string
    name: string
    address: string | null
    phone: string | null
    /** Şubeye atanmış şablon kodu (menu_templates.code). */
    template: string
  }
  language: CurrentLanguage
  languages: LanguageOption[]
  allergens: Allergen[]
  categories: Category[]
}

export interface BranchSummary {
  slug: string
  code: string
  name: string
  address: string | null
}

/** GET /api/v1/site `data` yapısı. */
export interface Site {
  restaurant: {
    slug: string
    name: string
    logo_url: string | null
  }
  language: CurrentLanguage
  languages: LanguageOption[]
  branches: BranchSummary[]
  /** Kanonik kök adres (sonunda / yok) veya null. */
  canonical_url: string | null
  /** Seçili (veya tek) şubenin menüsü; birden fazla şube varken şube seçilmediyse null. */
  menu: Menu | null
}
