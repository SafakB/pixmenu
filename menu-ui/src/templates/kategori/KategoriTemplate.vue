<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { Menu, Product, Site } from '../../api/types'
import AppHeader from '../../components/AppHeader.vue'
import AppIcon from '../../components/AppIcon.vue'
import MenuFooter from '../../components/MenuFooter.vue'
import ProductSheet from '../../components/ProductSheet.vue'
import SearchBox from '../../components/SearchBox.vue'
import { useMenuHelpers } from '../../core/menuHelpers'
import { filterCategories } from '../../core/search'
import ProductItem from './ProductItem.vue'

/**
 * Şablon "kategori" — önce fotoğraflı kategori kartları, dokununca o kategorinin ürünleri.
 * Kartta görsel yoksa kategorinin emojisi, o da yoksa adının baş harfi gösterilir.
 * Açık kategori adres çubuğunda ?c={id} olarak durur: geri tuşu kartlara döner, bağlantı paylaşılabilir.
 */
const props = defineProps<{ site: Site; menu: Menu }>()

const route = useRoute()
const router = useRouter()
const { t, lang } = useMenuHelpers(() => props.menu)

const query = ref('')
const selected = ref<Product | null>(null)
const searching = computed(() => query.value.trim() !== '')
const results = computed(() => filterCategories(props.menu.categories, query.value, lang.value))

const categoryId = computed(() => {
  const raw = route.query.c
  const value = Number(Array.isArray(raw) ? raw[0] : raw)

  return Number.isInteger(value) && value > 0 ? value : null
})

const category = computed(() =>
  categoryId.value === null ? null : (props.menu.categories.find((item) => item.id === categoryId.value) ?? null),
)

// Tek sayıda kategori varsa ilk kart iki sütunu kaplar; ızgarada boşluk kalmaz.
const featureFirst = computed(() => props.menu.categories.length % 2 === 1)

function initial(name: string): string {
  return Array.from(name.trim())[0]?.toLocaleUpperCase(lang.value) ?? ''
}

function open(id: number): void {
  query.value = ''
  void router.push({ query: { ...route.query, c: String(id) } })
}

function back(): void {
  const historyState = window.history.state as { back?: unknown } | null

  if (historyState && typeof historyState.back === 'string') {
    router.back()

    return
  }

  // Kategoriye doğrudan bağlantıyla gelindi: kartlara geç.
  const rest = { ...route.query }
  delete rest.c
  void router.replace({ query: rest })
}
</script>

<template>
  <div class="min-h-dvh bg-[#faf9f7] text-stone-900 [--pm-accent:#9f1d35]">
    <!-- Kategori içi -->
    <template v-if="category">
      <div class="sticky top-0 z-20 border-b border-stone-200 bg-[#faf9f7]/95 backdrop-blur-sm">
        <div class="mx-auto flex max-w-2xl items-center gap-1 px-2 py-2">
          <button
            type="button"
            :aria-label="t('back')"
            class="rounded-full p-2.5 text-stone-700 hover:bg-stone-200/70 focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none"
            @click="back"
          >
            <AppIcon name="chevronStart" class="size-6 rtl:rotate-180" />
          </button>
          <h1 class="min-w-0 flex-1 truncate text-lg font-bold">
            <span v-if="category.emoji" aria-hidden="true" class="me-1.5">{{ category.emoji }}</span>{{ category.name }}
          </h1>
        </div>
      </div>

      <main class="mx-auto max-w-2xl px-4">
        <img
          v-if="category.image_url"
          :src="category.image_url"
          alt=""
          width="1200"
          height="675"
          class="mt-4 aspect-video w-full rounded-xl bg-stone-200 object-cover"
        />
        <p v-if="category.description" class="mt-4 max-w-prose text-stone-600">{{ category.description }}</p>

        <ul class="mt-2 divide-y divide-stone-200">
          <ProductItem
            v-for="product in category.products"
            :key="product.id"
            :product="product"
            :menu="menu"
            @open="selected = $event"
          />
        </ul>
      </main>
    </template>

    <!-- Kategori kartları / arama sonuçları -->
    <template v-else>
      <AppHeader :site="site" :subtitle="site.branches.length > 1 ? menu.branch.name : null" variant="bar" />

      <main class="mx-auto max-w-2xl px-4">
        <SearchBox v-model="query" :placeholder="t('search')" :clear-label="t('clearSearch')" class="mt-1" />

        <p v-if="menu.categories.length === 0" class="py-16 text-center text-stone-500">{{ t('emptyMenu') }}</p>

        <template v-else-if="searching">
          <p v-if="results.length === 0" class="py-16 text-center text-stone-500">{{ t('noResults') }}</p>
          <section v-for="result in results" :key="result.id" class="pt-6">
            <h2 class="text-sm font-semibold text-stone-500">
              <span v-if="result.emoji" aria-hidden="true" class="me-1">{{ result.emoji }}</span>{{ result.name }}
            </h2>
            <ul class="divide-y divide-stone-200">
              <ProductItem
                v-for="product in result.products"
                :key="product.id"
                :product="product"
                :menu="menu"
                @open="selected = $event"
              />
            </ul>
          </section>
        </template>

        <ul v-else class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3" :aria-label="t('categories')">
          <li
            v-for="(item, index) in menu.categories"
            :key="item.id"
            :class="index === 0 && featureFirst ? 'col-span-2 sm:col-span-1' : ''"
          >
            <button
              type="button"
              class="group relative block w-full overflow-hidden rounded-xl bg-(--pm-accent) text-start focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:ring-offset-2 focus-visible:outline-none"
              :class="index === 0 && featureFirst ? 'aspect-2/1 sm:aspect-square' : 'aspect-square'"
              @click="open(item.id)"
            >
              <img
                v-if="item.image_url"
                :src="item.image_url"
                alt=""
                :loading="index < 4 ? 'eager' : 'lazy'"
                decoding="async"
                class="absolute inset-0 size-full object-cover transition-transform duration-300 group-hover:scale-[1.03] motion-reduce:transition-none"
              />
              <span
                v-else-if="item.emoji"
                aria-hidden="true"
                class="absolute inset-x-0 top-[38%] -translate-y-1/2 text-center text-6xl leading-none drop-shadow-sm transition-transform duration-300 select-none group-hover:scale-110 motion-reduce:transition-none sm:text-7xl"
              >
                {{ item.emoji }}
              </span>
              <span
                v-else
                aria-hidden="true"
                class="absolute -end-3 -bottom-10 font-serif text-[10rem] leading-none font-semibold text-white/15 select-none"
              >
                {{ initial(item.name) }}
              </span>
              <span class="absolute inset-0 bg-linear-to-t from-black/75 via-black/15 to-transparent" aria-hidden="true" />
              <span class="absolute inset-x-0 bottom-0 p-3 text-white">
                <span class="block text-lg leading-tight font-semibold text-balance">{{ item.name }}</span>
                <span class="mt-0.5 block text-sm text-white/80">
                  {{ t(item.products.length === 1 ? 'productCountOne' : 'productCount', { n: item.products.length }) }}
                </span>
              </span>
            </button>
          </li>
        </ul>
      </main>
    </template>

    <MenuFooter :menu="menu" />
    <ProductSheet :product="selected" :menu="menu" @close="selected = null" />
  </div>
</template>
