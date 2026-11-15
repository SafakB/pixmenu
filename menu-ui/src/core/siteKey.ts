/**
 * Uygulama hangi restoranı gösteriyor?
 *  - VITE_RESTAURANT doluysa o slug (restorana özel kurulum / geliştirme),
 *  - boşsa tarayıcının host'u (sirketadi.pixmenu.com, menu.sirketadi.com) — API çözer.
 */
export type SiteKey = { restaurant: string } | { host: string }

export function resolveSiteKey(envRestaurant: string | undefined, hostname: string): SiteKey {
  const slug = (envRestaurant ?? '').trim().toLowerCase()

  return slug !== '' ? { restaurant: slug } : { host: hostname.toLowerCase() }
}

/** Yerel depolama anahtarları için kısa kimlik. */
export function siteKeyId(key: SiteKey): string {
  return 'restaurant' in key ? key.restaurant : key.host
}

/**
 * Host modunda adres çubuğundaki host kanonik adresten farklıysa (ör. alt alan adından
 * girildi ama restoranın özel alan adı var) yönlenilecek kök adres; değilse null.
 * Sabit restoran (.env) modunda yönlendirme yapılmaz.
 */
export function canonicalRedirect(key: SiteKey, canonicalUrl: string | null, hostname: string): string | null {
  if (!('host' in key) || !canonicalUrl) {
    return null
  }

  try {
    const canonical = new URL(canonicalUrl)

    return canonical.hostname !== hostname.toLowerCase() ? canonical.origin : null
  } catch {
    return null
  }
}
