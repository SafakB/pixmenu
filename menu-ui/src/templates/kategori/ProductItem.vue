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
      class="flex w-full gap-4 py-4 text-start focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:ring-offset-4 focus-visible:outline-none"
      @click="$emit('open', product)"
    >
      <img
        v-if="product.thumb_url"
        :src="product.thumb_url"
        alt=""
        loading="lazy"
        decoding="async"
        width="96"
        height="96"
        class="size-24 shrink-0 rounded-lg bg-stone-200 object-cover"
      />
      <span class="flex min-w-0 flex-1 flex-col">
        <span class="leading-snug font-semibold">{{ product.name }}</span>
        <span v-if="product.description" class="mt-1 line-clamp-2 text-sm leading-relaxed text-stone-600">
          {{ product.description }}
        </span>
        <span class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-2">
          <span class="font-semibold text-(--pm-accent) tabular-nums">{{ price(product) }}</span>
          <AllergenList v-if="product.allergens.length" :list="product.allergens" :menu="menu" />
        </span>
      </span>
    </button>
  </li>
</template>
