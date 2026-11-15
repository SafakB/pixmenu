import { defineAsyncComponent, type Component } from 'vue'

/**
 * Menü şablonları. Anahtar, admin panelindeki "Menü şablonları" kodudur (menu_templates.code →
 * API'de `menu.branch.template`). Her şablon ayrı bir chunk'tır: ziyaretçi yalnızca kendi
 * şubesinin şablonunu indirir.
 *
 * Yeni şablon: ./{kod}/ altında bileşeni yazın, buraya ekleyin, admin'de aynı kodla tanımlayın.
 */
const loaders = {
  standart: () => import('./standart/StandartTemplate.vue'),
  kategori: () => import('./kategori/KategoriTemplate.vue'),
} satisfies Record<string, () => Promise<{ default: Component }>>

export type TemplateCode = keyof typeof loaders

export const DEFAULT_TEMPLATE: TemplateCode = 'standart'

const warned = new Set<string>()

export function isTemplateCode(code: string): code is TemplateCode {
  return Object.prototype.hasOwnProperty.call(loaders, code)
}

/** Bilinmeyen kod (admin'de tanımlı ama bu sürümde tasarımı yok) varsayılan şablona düşer. */
export function resolveTemplateCode(code: string | null | undefined): TemplateCode {
  if (code && isTemplateCode(code)) {
    return code
  }

  if (code && !warned.has(code)) {
    warned.add(code)
    console.warn(`[menu-ui] "${code}" şablonu bu sürümde yok; "${DEFAULT_TEMPLATE}" kullanılıyor.`)
  }

  return DEFAULT_TEMPLATE
}

const components = new Map<TemplateCode, Component>()

export function templateComponent(code: string): Component {
  const resolved = resolveTemplateCode(code)
  let component = components.get(resolved)

  if (component === undefined) {
    component = defineAsyncComponent(loaders[resolved])
    components.set(resolved, component)
  }

  return component
}

/** Şablon chunk'ını API yanıtını beklemeden indirmeye başlar (önceki ziyaretten bilinen kod). */
export function prefetchTemplate(code: string): void {
  if (isTemplateCode(code)) {
    void loaders[code]().catch(() => undefined)
  }
}
