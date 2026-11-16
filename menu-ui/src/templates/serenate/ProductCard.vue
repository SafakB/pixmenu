<script setup lang="ts">
import { computed } from 'vue'
import type { Menu, Product } from '../../api/types'
import { useMenuHelpers } from '../../core/menuHelpers'
import AllergenBadges from './AllergenBadges.vue'

/**
 * İki görünümlü ürün kartı:
 * - Fotoğraflı ürün: geniş görsel, ad görselin üstünde, fiyat cam bir hapta.
 * - Fotoğrafsız ürün: basılı menü satırı gibi; ad, noktalı çizgi, fiyat.
 * Kartın tamamı tek düğmedir; dokununca ürün sayfası açılır.
 */
const props = defineProps<{ product: Product; menu: Menu }>()
defineEmits<{ open: [product: Product] }>()

const { price } = useMenuHelpers(() => props.menu)
const image = computed(() => props.product.thumb_url ?? props.product.image_url)
</script>

<template>
  <li>
    <button
      v-if="image"
      type="button"
      class="group block w-full overflow-hidden rounded-[1.75rem] bg-white/5 text-start ring-1 ring-white/10 transition duration-200 hover:ring-(--pm-accent)/50 focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none active:scale-[0.985] motion-reduce:transition-none motion-reduce:active:scale-100"
      @click="$emit('open', product)"
    >
      <span class="relative block aspect-4/3 bg-white/5">
        <img
          :src="image"
          alt=""
          loading="lazy"
          decoding="async"
          width="480"
          height="360"
          class="absolute inset-0 size-full object-cover transition-transform duration-500 group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100"
        />
        <span aria-hidden="true" class="absolute inset-0 bg-linear-to-t from-[#14101c] via-[#14101c]/35 to-transparent" />
        <span
          class="absolute end-3 top-3 rounded-full bg-[#14101c]/75 px-3 py-1 text-sm font-semibold text-(--pm-accent) tabular-nums ring-1 ring-white/15 backdrop-blur-md"
        >
          {{ price(product) }}
        </span>
        <span
          class="absolute inset-x-0 bottom-0 line-clamp-2 px-4 pb-3 font-serif text-2xl leading-tight text-balance text-(--sr-ink)"
        >
          {{ product.name }}
        </span>
      </span>

      <span v-if="product.description || product.allergens.length" class="block bg-[#14101c]/60 px-4 pt-1 pb-4">
        <span v-if="product.description" class="line-clamp-2 text-sm leading-relaxed text-(--sr-mute)">
          {{ product.description }}
        </span>
        <AllergenBadges
          v-if="product.allergens.length"
          :list="product.allergens"
          :menu="menu"
          class="mt-2.5 flex-wrap"
        />
      </span>
    </button>

    <button
      v-else
      type="button"
      class="block w-full rounded-3xl bg-white/[0.035] px-4 py-4 text-start ring-1 ring-white/8 transition duration-200 hover:ring-(--pm-accent)/50 focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none active:scale-[0.985] motion-reduce:transition-none motion-reduce:active:scale-100"
      @click="$emit('open', product)"
    >
      <span class="flex items-baseline gap-3">
        <span class="min-w-0 font-serif text-xl leading-snug text-(--sr-ink)">{{ product.name }}</span>
        <span aria-hidden="true" class="min-w-4 flex-1 -translate-y-1 border-b border-dotted border-white/25" />
        <span class="shrink-0 font-semibold text-(--pm-accent) tabular-nums">{{ price(product) }}</span>
      </span>
      <span v-if="product.description" class="mt-1.5 line-clamp-2 text-sm leading-relaxed text-(--sr-mute)">
        {{ product.description }}
      </span>
      <AllergenBadges v-if="product.allergens.length" :list="product.allergens" :menu="menu" class="mt-2.5 flex-wrap" />
    </button>
  </li>
</template>
