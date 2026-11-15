<script setup lang="ts">
import { computed } from 'vue'
import type { AllergenRef, Menu } from '../../api/types'
import { useMenuHelpers } from '../../core/menuHelpers'

/**
 * Ürün kartındaki küçük alerjen rozetleri. İkon yüklenmişse ikon, yoksa adı yazar.
 * "İçerir" turuncu ve dolu, "iz miktarda" beyaz ve soluk. `max` aşılırsa "+N" rozeti.
 * Yerleşim (dikey/yatay) üst bileşenin verdiği sınıfla belirlenir.
 */
const props = withDefaults(defineProps<{ list: AllergenRef[]; menu: Menu; max?: number }>(), { max: Infinity })
const { t, allergensById } = useMenuHelpers(() => props.menu)

const items = computed(() =>
  props.list.flatMap((entry) => {
    const allergen = allergensById.value.get(entry.id)

    return allergen ? [{ ...allergen, level: entry.level }] : []
  }),
)

const visible = computed(() => items.value.slice(0, props.max))
const hidden = computed(() => items.value.slice(props.max))
</script>

<template>
  <ul v-if="items.length" class="flex gap-1" :aria-label="t('allergens')">
    <li
      v-for="item in visible"
      :key="item.id"
      :title="`${item.name}: ${item.level === 'contains' ? t('contains') : t('mayContain')}`"
      class="max-w-full"
    >
      <span
        v-if="item.icon_url"
        class="grid size-6 place-items-center rounded-md bg-white/95 shadow-sm"
        :class="item.level === 'contains' ? 'ring-2 ring-orange-500' : 'opacity-75'"
      >
        <img :src="item.icon_url" :alt="item.name" width="18" height="18" class="size-4.5" />
      </span>
      <span
        v-else
        class="block truncate rounded-md px-1.5 text-[0.6875rem] leading-5 font-semibold shadow-sm"
        :class="item.level === 'contains' ? 'bg-orange-500 text-white' : 'bg-white/90 text-slate-600'"
      >
        {{ item.name }}
      </span>
    </li>
    <li
      v-if="hidden.length"
      :title="hidden.map((item) => item.name).join(', ')"
      class="rounded-md bg-slate-900/60 px-1.5 text-[0.6875rem] leading-5 font-semibold text-white shadow-sm"
    >
      <span aria-hidden="true">+{{ hidden.length }}</span>
      <span class="sr-only">{{ hidden.map((item) => item.name).join(', ') }}</span>
    </li>
  </ul>
</template>
