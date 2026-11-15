<script setup lang="ts">
import { computed } from 'vue'
import type { Menu, Product } from '../../api/types'
import AppIcon from '../../components/AppIcon.vue'
import { useMenuHelpers } from '../../core/menuHelpers'
import AllergenBadges from './AllergenBadges.vue'

/**
 * Yatay ürün kartı: solda fotoğraf (alerjen rozetleri üstünde), sağda ad, açıklama, fiyat.
 * Kartın tamamı tek düğmedir; "+" yalnızca görsel ipucu, dokununca ürün penceresi açılır.
 */
const props = defineProps<{ product: Product; menu: Menu }>()
defineEmits<{ open: [product: Product] }>()

const { price } = useMenuHelpers(() => props.menu)
const image = computed(() => props.product.thumb_url ?? props.product.image_url)
</script>

<template>
  <li>
    <button
      type="button"
      class="group flex size-full min-h-32 overflow-hidden rounded-2xl bg-white/95 text-start shadow-[0_8px_24px_-12px_rgb(15_23_42/0.25)] ring-1 ring-slate-900/5 transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_14px_32px_-14px_rgb(15_23_42/0.35)] focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none motion-reduce:transition-none motion-reduce:hover:translate-y-0"
      @click="$emit('open', product)"
    >
      <span v-if="image" class="relative w-[34%] max-w-48 shrink-0 bg-slate-200">
        <img
          :src="image"
          alt=""
          loading="lazy"
          decoding="async"
          width="480"
          height="360"
          class="absolute inset-0 size-full object-cover"
        />
        <AllergenBadges
          v-if="product.allergens.length"
          :list="product.allergens"
          :menu="menu"
          :max="3"
          class="absolute start-2 top-2 end-2 flex-col items-start"
        />
      </span>

      <span class="flex min-w-0 flex-1 flex-col p-4">
        <span class="line-clamp-2 text-[1.0625rem] leading-snug font-bold text-slate-800">{{ product.name }}</span>
        <span v-if="product.description" class="mt-1 line-clamp-2 text-sm leading-relaxed text-slate-500">
          {{ product.description }}
        </span>
        <AllergenBadges
          v-if="!image && product.allergens.length"
          :list="product.allergens"
          :menu="menu"
          class="mt-2 flex-wrap"
        />
        <span class="mt-auto flex items-center justify-between gap-3 pt-3">
          <span class="text-lg font-bold text-(--pm-accent) tabular-nums">{{ price(product) }}</span>
          <span
            aria-hidden="true"
            class="grid size-8 shrink-0 place-items-center rounded-full bg-rose-100 text-(--pm-accent) transition-colors group-hover:bg-(--pm-accent) group-hover:text-white"
          >
            <AppIcon name="plus" class="size-4.5" />
          </span>
        </span>
      </span>
    </button>
  </li>
</template>
