<script setup lang="ts">
import type { Menu, Product } from '../../api/types'
import AllergenList from '../../components/AllergenList.vue'
import { useMenuHelpers } from '../../core/menuHelpers'

const props = defineProps<{ product: Product; menu: Menu }>()
defineEmits<{ open: [product: Product] }>()

const { price } = useMenuHelpers(() => props.menu)
</script>

<template>
  <li>
    <button
      type="button"
      class="group flex w-full gap-4 py-4 text-start focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:ring-offset-4 focus-visible:outline-none"
      @click="$emit('open', product)"
    >
      <span class="min-w-0 flex-1">
        <!-- Basılı menü düzeni: ad ……… fiyat -->
        <span class="flex items-baseline gap-2">
          <span class="min-w-0 text-[1.0625rem] leading-snug font-semibold group-hover:text-(--pm-accent)">
            {{ product.name }}
          </span>
          <span aria-hidden="true" class="min-w-4 flex-1 -translate-y-1 border-b-2 border-dotted border-stone-300" />
          <span class="shrink-0 font-semibold text-(--pm-accent) tabular-nums">{{ price(product) }}</span>
        </span>
        <span v-if="product.description" class="mt-1 line-clamp-2 block leading-relaxed text-stone-600">
          {{ product.description }}
        </span>
        <AllergenList v-if="product.allergens.length" :list="product.allergens" :menu="menu" class="mt-2" />
      </span>
      <img
        v-if="product.thumb_url"
        :src="product.thumb_url"
        alt=""
        loading="lazy"
        decoding="async"
        width="80"
        height="80"
        class="size-20 shrink-0 rounded-md bg-stone-100 object-cover"
      />
    </button>
  </li>
</template>
