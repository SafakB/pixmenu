<script setup lang="ts">
import { computed } from 'vue'
import type { AllergenRef, Menu } from '../api/types'
import { useMenuHelpers } from '../core/menuHelpers'

/**
 * compact:  ürün satırında küçük ikon/ad dizisi ("iz miktarda" olanlar soluk)
 * detailed: ürün detayında ikon + ad + seviye listesi
 */
const props = defineProps<{ list: AllergenRef[]; menu: Menu; detailed?: boolean }>()
const { t, allergensById } = useMenuHelpers(() => props.menu)

const items = computed(() =>
  props.list.flatMap((entry) => {
    const allergen = allergensById.value.get(entry.id)

    return allergen ? [{ ...allergen, level: entry.level }] : []
  }),
)
</script>

<template>
  <ul v-if="detailed && items.length" class="space-y-2.5">
    <li v-for="item in items" :key="item.id" class="flex items-center gap-3">
      <img v-if="item.icon_url" :src="item.icon_url" alt="" width="24" height="24" class="size-6 shrink-0" />
      <span v-else class="size-6 shrink-0 rounded-full border-2 border-stone-200" aria-hidden="true" />
      <span class="flex-1">{{ item.name }}</span>
      <span class="text-sm" :class="item.level === 'contains' ? 'font-medium text-stone-900' : 'text-stone-500'">
        {{ item.level === 'contains' ? t('contains') : t('mayContain') }}
      </span>
    </li>
  </ul>

  <ul v-else-if="items.length" class="flex flex-wrap items-center gap-1.5" :aria-label="t('allergens')">
    <li
      v-for="item in items"
      :key="item.id"
      :title="`${item.name}: ${item.level === 'contains' ? t('contains') : t('mayContain')}`"
      :class="item.level === 'may_contain' ? 'opacity-55' : ''"
    >
      <img v-if="item.icon_url" :src="item.icon_url" :alt="item.name" width="18" height="18" class="size-4.5" />
      <span v-else class="block rounded border border-stone-300 px-1.5 text-xs leading-5 text-stone-600">{{ item.name }}</span>
    </li>
  </ul>
</template>
