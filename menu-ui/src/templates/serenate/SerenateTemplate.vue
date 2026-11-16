<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import type { Menu, Product, Site } from '../../api/types'
import AppIcon from '../../components/AppIcon.vue'
import { useMenuHelpers } from '../../core/menuHelpers'
import { sectionId, useScrollSpy } from '../../core/scrollSpy'
import { filterCategories } from '../../core/search'
import { useMenuStore } from '../../core/store'
import CategorySheet from './CategorySheet.vue'
import LanguagePill from './LanguagePill.vue'
import ProductCard from './ProductCard.vue'
import ProductSheet from './ProductSheet.vue'

/**
 * Şablon "serenate" — akşam renklerinde, telefon öncelikli tek sayfa.
 * Üstte yapışkan çubuk yoktur: gezinme başparmağın ulaştığı yerde, altta yüzen dock'tadır.
 * Dock aktif kategoriyi gösterir; dokununca kategoriler alttan açılır. Arama, klavye açıkken de
 * görünsün diye ekranın üstünde açılır. Ürüne dokununca alttan ürün sayfası yükselir.
 */
const props = defineProps<{ site: Site; menu: Menu }>()

const BACKGROUND = '#14101c'

const { setLanguage } = useMenuStore()
const { t, lang } = useMenuHelpers(() => props.menu)

const query = ref('')
const selected = ref<Product | null>(null)
const searchOpen = ref(false)
const categoriesOpen = ref(false)
const searchInput = ref<HTMLInputElement | null>(null)
const searchToggle = ref<HTMLButtonElement | null>(null)
const visible = computed(() => filterCategories(props.menu.categories, query.value, lang.value))
const multiBranch = computed(() => props.site.branches.length > 1)
const hasFooter = computed(() => Boolean(props.menu.branch.address || props.menu.branch.phone))

const root = ref<HTMLElement | null>(null)
const nav = ref<HTMLElement | null>(null) // yatay sekme şeridi yok; scrollspy yalnızca aktif kategoriyi izler
const { activeId, goTo } = useScrollSpy({
  root,
  nav,
  categories: () => props.menu.categories,
  paused: () => searchOpen.value,
  rootMargin: '-40px 0px -60% 0px',
})

const activeCategory = computed(
  () => props.menu.categories.find((category) => category.id === activeId.value) ?? props.menu.categories[0] ?? null,
)
const order = computed(() => new Map(props.menu.categories.map((category, index) => [category.id, index + 1])))

function pad(value: number): string {
  return String(value).padStart(2, '0')
}

function initial(name: string): string {
  return Array.from(name.trim())[0]?.toLocaleUpperCase(lang.value) ?? ''
}

async function openSearch(): Promise<void> {
  searchOpen.value = true
  window.scrollTo({ top: 0 })
  await nextTick()
  searchInput.value?.focus()
}

async function closeSearch(): Promise<void> {
  searchOpen.value = false
  query.value = ''
  await nextTick()
  searchToggle.value?.focus()
}

// Sayfanın geri kalanı açık temadır; esneme (overscroll) bölgesi ve tarayıcı çubuğu da koyu olsun.
let restoreTheme: (() => void) | null = null

onMounted(() => {
  const html = document.documentElement
  const meta = document.querySelector('meta[name="theme-color"]')
  const previous = {
    background: html.style.backgroundColor,
    scheme: html.style.colorScheme,
    theme: meta?.getAttribute('content') ?? null,
  }

  html.style.backgroundColor = BACKGROUND
  html.style.colorScheme = 'dark'
  meta?.setAttribute('content', BACKGROUND)

  restoreTheme = () => {
    html.style.backgroundColor = previous.background
    html.style.colorScheme = previous.scheme

    if (previous.theme !== null) {
      meta?.setAttribute('content', previous.theme)
    }
  }
})

onBeforeUnmount(() => restoreTheme?.())
</script>

<template>
  <div
    ref="root"
    class="relative min-h-dvh overflow-x-clip bg-[#14101c] text-(--sr-ink) [--pm-accent:#f2b279] [--sr-ink:#f4ece1] [--sr-mute:#b3a8ba]"
  >
    <!-- Mum ışığı: üstten sızan sıcak ışıma. -->
    <div
      aria-hidden="true"
      class="pointer-events-none absolute inset-x-0 top-0 h-[34rem] bg-[radial-gradient(90%_60%_at_50%_-10%,rgb(242_178_121/0.22),transparent_70%)]"
    />

    <!-- Arama: klavye açılınca da görünür kalsın diye üstte sabit. -->
    <div
      v-if="searchOpen"
      id="serenate-arama"
      class="fixed inset-x-0 top-0 z-30 px-4 pt-[calc(env(safe-area-inset-top)+0.75rem)] transition-[translate,opacity] duration-300 ease-out starting:-translate-y-3 starting:opacity-0 motion-reduce:transition-none"
      @keydown.esc="closeSearch"
    >
      <div
        class="mx-auto flex max-w-xl items-center gap-1 rounded-full bg-[#221b2d]/90 p-1.5 shadow-[0_12px_40px_-8px_rgb(0_0_0/0.8)] ring-1 ring-white/12 backdrop-blur-xl focus-within:ring-(--pm-accent)/60"
      >
        <AppIcon name="search" class="ms-3 size-5 shrink-0 text-(--pm-accent)" />
        <!-- text-base: iOS 16px altındaki alanlarda odaklanınca sayfayı yakınlaştırır -->
        <input
          ref="searchInput"
          v-model="query"
          type="search"
          :placeholder="t('search')"
          :aria-label="t('search')"
          enterkeyhint="search"
          autocomplete="off"
          class="h-11 min-w-0 flex-1 bg-transparent px-2 text-base text-(--sr-ink) outline-none placeholder:text-(--sr-mute)/70 [&::-webkit-search-cancel-button]:appearance-none"
        />
        <button
          type="button"
          :aria-label="t('close')"
          class="grid size-11 shrink-0 place-items-center rounded-full bg-white/8 text-(--sr-ink) transition-colors hover:bg-white/14 focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none"
          @click="closeSearch"
        >
          <AppIcon name="close" class="size-5" />
        </button>
      </div>
    </div>

    <header
      v-if="!searchOpen"
      class="relative mx-auto max-w-5xl px-5 pt-[calc(env(safe-area-inset-top)+3rem)] sm:px-8 sm:pt-20"
    >
      <!-- Logo kırpılmaz (object-contain): yazı logolar da dairenin içinde kendi oranında görünür. -->
      <img
        v-if="site.restaurant.logo_url"
        :src="site.restaurant.logo_url"
        alt=""
        width="64"
        height="64"
        class="size-16 rounded-full bg-[#f4ece1] object-contain ring-1 ring-(--pm-accent)/60 ring-offset-4 ring-offset-[#14101c]"
      />
      <span
        v-else
        aria-hidden="true"
        class="grid size-16 place-items-center rounded-full font-serif text-3xl text-(--pm-accent) italic ring-1 ring-(--pm-accent)/60"
      >
        {{ initial(site.restaurant.name) }}
      </span>

      <h1
        class="mt-7 font-serif text-[clamp(2.75rem,13vw,5rem)] leading-[0.95] tracking-tight text-balance wrap-anywhere"
      >
        {{ site.restaurant.name }}
      </h1>

      <p v-if="menu.restaurant.description" class="mt-5 max-w-prose text-lg leading-relaxed text-(--sr-mute)">
        {{ menu.restaurant.description }}
      </p>

      <p v-if="multiBranch" class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-(--sr-mute)">
        <span class="inline-flex items-center gap-1.5 text-(--sr-ink)">
          <AppIcon name="pin" class="size-4 text-(--pm-accent)" />
          {{ menu.branch.name }}
        </span>
        <RouterLink
          to="/"
          class="rounded-sm underline decoration-white/25 underline-offset-4 hover:text-(--pm-accent) focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none"
        >
          {{ t('allBranches') }}
        </RouterLink>
      </p>

      <div aria-hidden="true" class="mt-9 flex items-center gap-3 text-(--pm-accent)">
        <span class="h-px flex-1 bg-linear-to-r from-transparent to-(--pm-accent)/60" />
        <span class="text-xs">✦</span>
        <span class="h-px flex-1 bg-linear-to-l from-transparent to-(--pm-accent)/60" />
      </div>
    </header>

    <main
      class="relative mx-auto max-w-5xl px-5 sm:px-8"
      :class="searchOpen ? 'pt-[calc(env(safe-area-inset-top)+4.5rem)]' : ''"
    >
      <p v-if="menu.categories.length === 0" class="py-20 text-center font-serif text-xl text-(--sr-mute) italic">
        {{ t('emptyMenu') }}
      </p>
      <p v-else-if="visible.length === 0" class="py-20 text-center font-serif text-xl text-(--sr-mute) italic">
        {{ t('noResults') }}
      </p>

      <section
        v-for="category in visible"
        :id="sectionId(category.id)"
        :key="category.id"
        :data-category-id="category.id"
        class="scroll-mt-4 pt-12"
      >
        <p aria-hidden="true" class="flex items-center gap-3 text-xs font-semibold tracking-[0.25em] tabular-nums">
          <span class="text-(--pm-accent)">{{ pad(order.get(category.id) ?? 0) }}</span>
          <span class="h-px w-8 bg-(--pm-accent)/50" />
          <span class="text-(--sr-mute)/60">{{ pad(menu.categories.length) }}</span>
        </p>
        <h2 class="mt-3 font-serif text-[2.5rem] leading-none tracking-tight text-balance italic">
          {{ category.name }}
          <span v-if="category.emoji" aria-hidden="true" class="ms-1 align-middle text-3xl not-italic">
            {{ category.emoji }}
          </span>
        </h2>
        <p v-if="category.description" class="mt-3 max-w-prose leading-relaxed text-(--sr-mute)">
          {{ category.description }}
        </p>

        <ul class="mt-6 grid gap-4 md:grid-cols-2 md:items-start">
          <ProductCard
            v-for="product in category.products"
            :key="product.id"
            :product="product"
            :menu="menu"
            @open="selected = $event"
          />
        </ul>
      </section>
    </main>

    <footer v-if="hasFooter" class="relative mx-auto max-w-5xl px-5 pt-16 sm:px-8">
      <div aria-hidden="true" class="h-px bg-linear-to-r from-transparent via-white/20 to-transparent" />
      <div class="space-y-3 pt-8 text-sm text-(--sr-mute)">
        <p v-if="menu.branch.address" class="flex gap-2.5">
          <AppIcon name="pin" class="mt-0.5 size-4 shrink-0 text-(--pm-accent)" />
          <span class="whitespace-pre-line">{{ menu.branch.address }}</span>
        </p>
        <p v-if="menu.branch.phone" class="flex gap-2.5">
          <AppIcon name="phone" class="mt-0.5 size-4 shrink-0 text-(--pm-accent)" />
          <a
            :href="`tel:${menu.branch.phone.replace(/[^\d+]/g, '')}`"
            :aria-label="`${t('call')}: ${menu.branch.phone}`"
            class="rounded-sm underline decoration-white/25 underline-offset-4 hover:text-(--pm-accent) focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none"
            dir="ltr"
          >
            {{ menu.branch.phone }}
          </a>
        </p>
      </div>
    </footer>

    <!-- Dock içeriğin üstünü kapatmasın diye alt boşluk. -->
    <div class="h-[calc(env(safe-area-inset-bottom)+7.5rem)]" aria-hidden="true" />

    <!-- Yüzen dock: başparmak bölgesinde kategori, arama ve dil. -->
    <div
      v-if="!searchOpen && (menu.categories.length > 0 || site.languages.length > 1)"
      class="pointer-events-none fixed inset-x-0 bottom-0 z-20 px-4 pb-[calc(env(safe-area-inset-bottom)+0.875rem)]"
    >
      <div
        class="pointer-events-auto mx-auto flex max-w-md items-center gap-1.5 rounded-full bg-[#221b2d]/85 p-1.5 shadow-[0_12px_40px_-8px_rgb(0_0_0/0.8)] ring-1 ring-white/12 backdrop-blur-xl"
      >
        <button
          v-if="menu.categories.length > 1 && activeCategory"
          type="button"
          aria-haspopup="dialog"
          class="group flex min-w-0 flex-1 items-center gap-3 rounded-full pe-3 text-start focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none"
          @click="categoriesOpen = true"
        >
          <span
            class="grid size-11 shrink-0 place-items-center rounded-full bg-(--pm-accent) text-[#14101c] transition group-hover:brightness-110 group-active:scale-95 motion-reduce:transition-none"
          >
            <AppIcon name="menu" class="size-5" />
          </span>
          <span class="min-w-0 flex-1">
            <span class="block text-[0.625rem] leading-tight font-semibold tracking-[0.2em] text-(--sr-mute) uppercase">
              {{ t('categories') }}
            </span>
            <span class="block truncate leading-snug font-medium">
              <span v-if="activeCategory.emoji" aria-hidden="true">{{ activeCategory.emoji }}&nbsp;</span>
              {{ activeCategory.name }}
            </span>
          </span>
        </button>
        <span v-else class="min-w-0 flex-1 truncate ps-4 font-serif text-lg italic">{{ site.restaurant.name }}</span>

        <button
          v-if="menu.categories.length > 0"
          ref="searchToggle"
          type="button"
          :aria-label="t('search')"
          aria-controls="serenate-arama"
          aria-expanded="false"
          class="grid size-11 shrink-0 place-items-center rounded-full bg-white/8 text-(--sr-ink) transition-colors hover:bg-white/14 focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none"
          @click="openSearch"
        >
          <AppIcon name="search" class="size-5" />
        </button>

        <LanguagePill
          v-if="site.languages.length > 1"
          :languages="site.languages"
          :value="site.language.code"
          :label="t('language')"
          @change="setLanguage"
        />
      </div>
    </div>

    <CategorySheet
      :open="categoriesOpen"
      :categories="menu.categories"
      :active-id="activeId"
      :menu="menu"
      @close="categoriesOpen = false"
      @select="goTo"
    />
    <ProductSheet :product="selected" :menu="menu" @close="selected = null" />
  </div>
</template>
