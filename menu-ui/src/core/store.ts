import { reactive } from 'vue'
import { fetchSite, NotFoundError } from '../api/client'
import type { Site } from '../api/types'
import { prefetchTemplate } from '../templates/registry'
import { applyDocumentLanguage, pickInitialLang } from './lang'
import { canonicalRedirect, resolveSiteKey, siteKeyId } from './siteKey'
import { storageGet, storageSet } from './storage'

export const siteKey = resolveSiteKey(import.meta.env.VITE_RESTAURANT, window.location.hostname)

const keyId = siteKeyId(siteKey)
const LANG_KEY = `pixmenu_lang_${keyId}`
const TEMPLATE_KEY = `pixmenu_tpl_${keyId}`

export interface MenuState {
  site: Site | null
  loading: boolean
  error: 'not_found' | 'network' | null
  branch: string | undefined
  lang: string | undefined
}

/** Uygulama durumu. Bileşenler yalnızca okur; değişiklik aşağıdaki fonksiyonlarla yapılır. */
const state = reactive<MenuState>({
  site: null,
  loading: false,
  error: null,
  branch: undefined,
  lang: undefined,
})

// Tekrar gelen ziyaretçide şablon chunk'ı API isteğiyle paralel insin.
const lastTemplate = storageGet(TEMPLATE_KEY)

if (lastTemplate) {
  prefetchTemplate(lastTemplate)
}

let controller: AbortController | null = null

/** İlk açılışta: ?lang (geçerliyse kaydedilir) → kayıtlı tercih. */
function initLanguage(urlLang: unknown): void {
  state.lang = pickInitialLang(urlLang, storageGet(LANG_KEY))

  if (typeof urlLang === 'string' && state.lang === urlLang) {
    storageSet(LANG_KEY, urlLang)
  }
}

async function load(branch: string | undefined, lang: string | undefined = state.lang): Promise<void> {
  controller?.abort()
  const current = new AbortController()
  controller = current

  state.loading = true
  state.branch = branch

  try {
    const site = await fetchSite({ ...siteKey, branch, lang }, current.signal)
    const redirect = canonicalRedirect(siteKey, site.canonical_url, window.location.hostname)

    if (redirect !== null) {
      const { pathname, search, hash } = window.location
      window.location.replace(redirect + pathname + search + hash)

      return
    }

    state.site = site
    state.error = null
    state.lang = site.language.code
    applyDocumentLanguage(site.language.code, site.language.direction)

    if (site.menu) {
      storageSet(TEMPLATE_KEY, site.menu.branch.template)
    }
  } catch (error) {
    if (current.signal.aborted) {
      return
    }

    state.error = error instanceof NotFoundError ? 'not_found' : 'network'

    if (state.error === 'not_found') {
      state.site = null
    }
  } finally {
    if (controller === current) {
      state.loading = false
    }
  }
}

function setLanguage(code: string): Promise<void> {
  storageSet(LANG_KEY, code)
  state.lang = code

  return load(state.branch, code)
}

export function useMenuStore() {
  return { state, load, setLanguage, initLanguage }
}
