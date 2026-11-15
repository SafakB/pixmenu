import type { Site } from '../api/types'

/** Sekme başlığı: "Şube · Restoran" (tek şubede veya şube seçiminde yalnızca restoran). */
export function documentTitle(site: Site): string {
  const restaurant = site.restaurant.name

  if (site.menu === null || site.branches.length <= 1 || site.menu.branch.name === '') {
    return restaurant
  }

  return `${site.menu.branch.name} · ${restaurant}`
}

export function applyDocumentMeta(site: Site): void {
  document.title = documentTitle(site)

  const description = site.menu?.restaurant.description ?? ''
  document.querySelector('meta[name="description"]')?.setAttribute('content', description)
}
