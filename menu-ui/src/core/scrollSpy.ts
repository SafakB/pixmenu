import { onBeforeUnmount, onMounted, ref, watch, type ComponentPublicInstance, type Ref } from 'vue'
import type { Category } from '../api/types'

/** Bölüm elemanının id'si; sekme tıklanınca bu id'ye kaydırılır. */
export function sectionId(categoryId: number): string {
  return `kategori-${categoryId}`
}

interface ScrollSpyOptions {
  /** `[data-category-id]` bölümlerini içeren kök. */
  root: Ref<HTMLElement | null>
  /** Yatay kayan sekme şeridi (aktif sekme ortalanır). */
  nav: Ref<HTMLElement | null>
  /** Kategoriler, sayfadaki sırayla. Değişince gözlem yeniden kurulur. */
  categories: () => Category[]
  /** true iken (ör. arama sürerken) aktif kategori izlenmez. */
  paused: () => boolean
  /** IntersectionObserver rootMargin: üstteki yapışkan alanın yüksekliği kadar içeriden. */
  rootMargin: string
}

/**
 * Tek sayfa şablonlarda kaydırdıkça aktif kategoriyi izler (scrollspy) ve sekmeye tıklanınca
 * o bölüme yumuşakça kaydırır.
 */
export function useScrollSpy(options: ScrollSpyOptions) {
  const tabs = new Map<number, HTMLElement>()
  const activeId = ref<number | null>(null)
  const inView = new Set<number>()
  const behavior: ScrollBehavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
  let observer: IntersectionObserver | null = null
  let lockUntil = 0

  function setTab(id: number, element: Element | ComponentPublicInstance | null): void {
    if (element instanceof HTMLElement) {
      tabs.set(id, element)
    } else {
      tabs.delete(id)
    }
  }

  function activate(id: number): void {
    if (activeId.value === id) {
      return
    }

    activeId.value = id
    const tab = tabs.get(id)
    const nav = options.nav.value

    if (tab && nav) {
      // Yalnızca sekme şeridi kaydırılır (scrollIntoView sayfa kaydırmasını kesebilir). LTR/RTL'de aynı çalışır.
      const tabBox = tab.getBoundingClientRect()
      const navBox = nav.getBoundingClientRect()
      nav.scrollBy({ left: tabBox.left + tabBox.width / 2 - (navBox.left + navBox.width / 2), behavior })
    }
  }

  function observe(): void {
    observer?.disconnect()
    inView.clear()

    const root = options.root.value

    if (options.paused() || root === null || typeof IntersectionObserver === 'undefined') {
      return
    }

    observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          const id = Number((entry.target as HTMLElement).dataset.categoryId)

          if (entry.isIntersecting) {
            inView.add(id)
          } else {
            inView.delete(id)
          }
        }

        if (Date.now() < lockUntil) {
          return // sekmeye tıklanınca başlayan yumuşak kaydırma sürüyor
        }

        const first = options.categories().find((category) => inView.has(category.id))

        if (first) {
          activate(first.id)
        }
      },
      { rootMargin: options.rootMargin },
    )

    root.querySelectorAll<HTMLElement>('[data-category-id]').forEach((section) => observer?.observe(section))
  }

  function goTo(id: number): void {
    lockUntil = Date.now() + 900
    activate(id)
    document.getElementById(sectionId(id))?.scrollIntoView({ behavior, block: 'start' })
  }

  onMounted(() => {
    activeId.value = options.categories()[0]?.id ?? null
    observe()
  })

  watch([options.categories, options.paused], observe, { flush: 'post' })

  onBeforeUnmount(() => observer?.disconnect())

  return { activeId, setTab, goTo }
}
