import type { TextDirection } from '../api/types'

const LANG_PATTERN = /^[A-Za-z0-9-]{1,10}$/

/**
 * İlk istekte gönderilecek dil: geçerli ?lang → kayıtlı tercih → yok (sunucu şubenin varsayılanını seçer).
 * Tarayıcı dili (Accept-Language / navigator.language) bilinçli olarak kullanılmaz (spec §8.4).
 */
export function pickInitialLang(urlLang: unknown, stored: string | null): string | undefined {
  if (typeof urlLang === 'string' && LANG_PATTERN.test(urlLang)) {
    return urlLang
  }

  return stored !== null && LANG_PATTERN.test(stored) ? stored : undefined
}

export function applyDocumentLanguage(code: string, direction: TextDirection): void {
  document.documentElement.lang = code
  document.documentElement.dir = direction
}
