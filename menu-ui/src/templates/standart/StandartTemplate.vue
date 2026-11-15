<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch, type ComponentPublicInstance } from 'vue'
import type { Menu, Product, Site } from '../../api/types'
import AppHeader from '../../components/AppHeader.vue'
import MenuFooter from '../../components/MenuFooter.vue'
import ProductSheet from '../../components/ProductSheet.vue'
import SearchBox from '../../components/SearchBox.vue'
import { useMenuHelpers } from '../../core/menuHelpers'
import { filterCategories } from '../../core/search'
import MenuRow from './MenuRow.vue'

/**
 * Şablon "standart" — tek sayfa, basılı menü kartı.
 * Tüm kategoriler alt alta; yapışkan sekmeler kaydırdıkça aktif kategoriyi izler.
 */
const props = defineProps<{ site: Site; menu: Menu }>()

const { t, lang } = useMenuHelpers(() => props.menu)
const query = ref('')
const selected = ref<Product | null>(null)
const searching = computed(() => query.value.trim() !== '')
const visible = computed(() => filterCategories(props.menu.categories, query.value, lang.value))

// --- Aktif kategori (scrollspy) ---------------------------------------------
const root = ref<HTMLElement | null>(null)
const nav = ref<HTMLElement | null>(null)
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

  if (tab && nav.value) {
    // Yalnızca sekme şeridi kaydırılır (scrollIntoView sayfa kaydırmasını kesebilir). LTR/RTL'de aynı çalışır.
    const tabBox = tab.getBoundingClientRect()
    const navBox = nav.value.getBoundingClientRect()
    nav.value.scrollBy({ left: tabBox.left + tabBox.width / 2 - (navBox.left + navBox.width / 2), behavior })
  }
}

function observe(): void {
  observer?.disconnect()
  inView.clear()

  if (searching.value || root.value === null || typeof IntersectionObserver === 'undefined') {
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

      const first = props.menu.categories.find((category) => inView.has(category.id))

      if (first) {
        activate(first.id)
      }
    },
    { rootMargin: '-130px 0px -55% 0px' },
  )

  root.value.querySelectorAll<HTMLElement>('[data-category-id]').forEach((section) => observer?.observe(section))
}

function goTo(id: number): void {
  lockUntil = Date.now() + 900
  activate(id)
  document.getElementById(`kategori-${id}`)?.scrollIntoView({ behavior, block: 'start' })
}

onMounted(() => {
  activeId.value = props.menu.categories[0]?.id ?? null
  observe()
})

watch([() => props.menu, searching], observe, { flush: 'post' })

onBeforeUnmount(() => observer?.disconnect())
</script>

<template>
  <div ref="root" class="min-h-dvh bg-white text-stone-900 [--pm-accent:#1f5a3d]">
    <AppHeader :site="site" :subtitle="site.branches.length > 1 ? menu.branch.name : null" />

    <div class="sticky top-0 z-20 border-b border-stone-200 bg-white/95 backdrop-blur-sm">
      <div class="mx-auto max-w-2xl px-4 pt-3" :class="searching || menu.categories.length < 2 ? 'pb-3' : ''">
        <SearchBox v-model="query" :placeholder="t('search')" :clear-label="t('clearSearch')" />
      </div>

      <nav
        v-if="!searching && menu.categories.length > 1"
        ref="nav"
        :aria-label="t('categories')"
        class="scrollbar-none mx-auto flex max-w-2xl gap-6 overflow-x-auto px-4"
      >
        <button
          v-for="category in menu.categories"
          :key="category.id"
          :ref="(element) => setTab(category.id, element)"
          type="button"
          class="relative shrink-0 py-3 text-[0.9375rem] font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none"
          :class="category.id === activeId ? 'text-(--pm-accent)' : 'text-stone-500 hover:text-stone-800'"
          :aria-current="category.id === activeId ? 'true' : undefined"
          @click="goTo(category.id)"
        >
          <span v-if="category.emoji" aria-hidden="true" class="me-1">{{ category.emoji }}</span>{{ category.name }}
          <span
            v-if="category.id === activeId"
            class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-(--pm-accent)"
            aria-hidden="true"
          />
        </button>
      </nav>
    </div>

    <main class="mx-auto max-w-2xl px-4">
      <p v-if="menu.categories.length === 0" class="py-16 text-center text-stone-500">{{ t('emptyMenu') }}</p>
      <p v-else-if="visible.length === 0" class="py-16 text-center text-stone-500">{{ t('noResults') }}</p>

      <section
        v-for="category in visible"
        :id="`kategori-${category.id}`"
        :key="category.id"
        :data-category-id="category.id"
        class="scroll-mt-32 pt-10"
      >
        <h2 class="font-serif text-[1.75rem] leading-tight font-semibold">
          <span v-if="category.emoji" aria-hidden="true" class="me-2">{{ category.emoji }}</span>{{ category.name }}
        </h2>
        <p v-if="category.description" class="mt-1.5 max-w-prose text-stone-500">{{ category.description }}</p>

        <ul class="mt-2 divide-y divide-stone-100">
          <MenuRow
            v-for="product in category.products"
            :key="product.id"
            :product="product"
            :menu="menu"
            @open="selected = $event"
          />
        </ul>
      </section>
    </main>

    <MenuFooter :menu="menu" />
    <ProductSheet :product="selected" :menu="menu" @close="selected = null" />
  </div>
</template>
