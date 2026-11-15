<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { RouterLink } from 'vue-router'
import type { Menu, Product, Site } from '../../api/types'
import AppIcon from '../../components/AppIcon.vue'
import MenuFooter from '../../components/MenuFooter.vue'
import SearchBox from '../../components/SearchBox.vue'
import { useMenuHelpers } from '../../core/menuHelpers'
import { sectionId, useScrollSpy } from '../../core/scrollSpy'
import { filterCategories } from '../../core/search'
import { useMenuStore } from '../../core/store'
import LanguagePill from './LanguagePill.vue'
import ProductCard from './ProductCard.vue'
import ProductDialog from './ProductDialog.vue'

/**
 * Şablon "modern" — tek sayfa, fotoğraflı ürün kartları.
 * Yapışkan başlıkta kategori çipleri kaydırdıkça aktif kategoriyi izler; büyüteç ikonu çiplerin
 * yerine arama kutusunu açar. Ürüne dokununca ortada bir pencere açılır.
 */
const props = defineProps<{ site: Site; menu: Menu }>()

const { setLanguage } = useMenuStore()
const { t, lang } = useMenuHelpers(() => props.menu)

const query = ref('')
const selected = ref<Product | null>(null)
const searchOpen = ref(false)
const searchArea = ref<HTMLElement | null>(null)
const searchToggle = ref<HTMLButtonElement | null>(null)
const searching = computed(() => query.value.trim() !== '')
const visible = computed(() => filterCategories(props.menu.categories, query.value, lang.value))
const multiBranch = computed(() => props.site.branches.length > 1)

const root = ref<HTMLElement | null>(null)
const nav = ref<HTMLElement | null>(null)
const { activeId, setTab, goTo } = useScrollSpy({
  root,
  nav,
  categories: () => props.menu.categories,
  paused: () => searching.value,
  rootMargin: '-130px 0px -55% 0px',
})

function initial(name: string): string {
  return Array.from(name.trim())[0]?.toLocaleUpperCase(lang.value) ?? ''
}

async function toggleSearch(): Promise<void> {
  if (searchOpen.value) {
    closeSearch()

    return
  }

  searchOpen.value = true
  await nextTick()
  searchArea.value?.querySelector('input')?.focus()
}

function closeSearch(): void {
  searchOpen.value = false
  query.value = ''
  searchToggle.value?.focus()
}
</script>

<template>
  <div ref="root" class="min-h-dvh bg-linear-to-b from-slate-50 to-slate-200/70 text-slate-800 [--pm-accent:#e11d48]">
    <div class="sticky top-0 z-20">
      <header class="bg-white/92 shadow-[0_1px_16px_rgb(15_23_42/0.07)] backdrop-blur-md">
        <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3 sm:px-6">
          <!-- Logo kırpılmaz (object-contain): yazı logolar da dairenin içinde kendi oranında görünür. -->
          <img
            v-if="site.restaurant.logo_url"
            :src="site.restaurant.logo_url"
            alt=""
            width="44"
            height="44"
            class="size-11 shrink-0 rounded-full bg-white object-contain ring-2 ring-rose-200 ring-offset-2 ring-offset-white"
          />
          <span
            v-else
            aria-hidden="true"
            class="grid size-11 shrink-0 place-items-center rounded-full bg-linear-to-br from-rose-500 to-orange-400 text-lg font-bold text-white"
          >
            {{ initial(site.restaurant.name) }}
          </span>

          <div class="min-w-0 flex-1">
            <h1
              class="truncate bg-linear-to-r from-rose-600 to-orange-500 bg-clip-text pb-0.5 text-xl leading-tight font-extrabold tracking-tight text-transparent rtl:bg-linear-to-l"
            >
              {{ site.restaurant.name }}
            </h1>
            <p v-if="multiBranch" class="truncate text-sm text-slate-500">
              <span>{{ menu.branch.name }}</span>
              <RouterLink
                to="/"
                class="ms-2 underline decoration-slate-300 underline-offset-4 hover:text-(--pm-accent)"
              >
                {{ t('allBranches') }}
              </RouterLink>
            </p>
          </div>

          <button
            v-if="menu.categories.length > 0"
            ref="searchToggle"
            type="button"
            :aria-label="t('search')"
            :aria-expanded="searchOpen"
            aria-controls="modern-arama"
            class="grid size-10 shrink-0 place-items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none"
            :class="searchOpen ? 'bg-rose-100 text-(--pm-accent)' : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80'"
            @click="toggleSearch"
          >
            <AppIcon :name="searchOpen ? 'close' : 'search'" class="size-5" />
          </button>

          <LanguagePill
            v-if="site.languages.length > 1"
            :languages="site.languages"
            :value="site.language.code"
            :label="t('language')"
            @change="setLanguage"
          />
        </div>
      </header>

      <div
        v-if="searchOpen"
        id="modern-arama"
        ref="searchArea"
        class="border-b border-slate-200/70 bg-slate-50/92 backdrop-blur-md"
        @keydown.esc="closeSearch"
      >
        <div class="mx-auto max-w-5xl px-4 py-2.5 sm:px-6">
          <SearchBox v-model="query" :placeholder="t('search')" :clear-label="t('clearSearch')" />
        </div>
      </div>

      <div v-else-if="menu.categories.length > 1" class="border-b border-slate-200/70 bg-slate-50/92 backdrop-blur-md">
        <nav
          ref="nav"
          :aria-label="t('categories')"
          class="scrollbar-none mx-auto flex max-w-5xl gap-2.5 overflow-x-auto px-4 py-2.5 sm:px-6"
        >
          <button
            v-for="category in menu.categories"
            :key="category.id"
            :ref="(element) => setTab(category.id, element)"
            type="button"
            class="inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-medium whitespace-nowrap shadow-sm ring-1 transition-colors focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none"
            :class="
              category.id === activeId
                ? 'bg-white text-(--pm-accent) ring-(--pm-accent)/40'
                : 'bg-white/70 text-slate-600 ring-slate-900/5 hover:bg-white hover:text-slate-800'
            "
            :aria-current="category.id === activeId ? 'true' : undefined"
            @click="goTo(category.id)"
          >
            <span v-if="category.emoji" aria-hidden="true" class="text-base leading-none">{{ category.emoji }}</span>
            {{ category.name }}
          </button>
        </nav>
      </div>
    </div>

    <main class="mx-auto max-w-5xl px-4 sm:px-6">
      <p v-if="menu.categories.length === 0" class="py-16 text-center text-slate-500">{{ t('emptyMenu') }}</p>
      <p v-else-if="visible.length === 0" class="py-16 text-center text-slate-500">{{ t('noResults') }}</p>

      <section
        v-for="category in visible"
        :id="sectionId(category.id)"
        :key="category.id"
        :data-category-id="category.id"
        class="scroll-mt-28 pt-8"
      >
        <div class="flex items-center gap-3">
          <span
            aria-hidden="true"
            class="grid size-11 shrink-0 place-items-center rounded-xl bg-white text-2xl leading-none shadow-sm ring-1 ring-slate-900/5"
          >
            <template v-if="category.emoji">{{ category.emoji }}</template>
            <span v-else class="text-lg font-bold text-(--pm-accent)">{{ initial(category.name) }}</span>
          </span>
          <h2 class="min-w-0 text-2xl leading-tight font-bold tracking-tight text-balance text-slate-800">
            {{ category.name }}
          </h2>
          <span
            aria-hidden="true"
            class="ms-2 h-px min-w-8 flex-1 bg-linear-to-r from-rose-300/80 to-transparent rtl:bg-linear-to-l"
          />
        </div>
        <p v-if="category.description" class="mt-2 max-w-prose text-slate-500">{{ category.description }}</p>

        <ul class="mt-4 grid gap-4 md:grid-cols-2">
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

    <!-- Alt bilgi içerikle aynı genişlikte hizalanır. -->
    <MenuFooter :menu="menu" class="max-w-5xl! sm:px-6!" />
    <div v-if="!menu.branch.address && !menu.branch.phone" class="h-12" aria-hidden="true" />
    <ProductDialog :product="selected" :menu="menu" @close="selected = null" />
  </div>
</template>
