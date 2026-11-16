<script setup lang="ts">
import { computed } from 'vue'
import type { AllergenRef, Menu } from '../../api/types'
import { useMenuHelpers } from '../../core/menuHelpers'

/**
 * Ürün kartındaki küçük alerjen rozetleri (koyu zemin için). İkon yüklenmişse açık bir karoda ikon,
 * yoksa adı yazar. "İçerir" amber vurgulu, "iz miktarda" soluk. `max` aşılırsa "+N" rozeti.
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
  <ul v-if="items.length" class="flex gap-1.5" :aria-label="t('allergens')">
    <li
      v-for="item in visible"
      :key="item.id"
      :title="`${item.name}: ${item.level === 'contains' ? t('contains') : t('mayContain')}`"
      class="max-w-full"
    >
      <span
        v-if="item.icon_url"
        class="grid size-6 place-items-center rounded-full bg-[#f4ece1]"
        :class="item.level === 'contains' ? 'ring-2 ring-orange-400' : 'opacity-60'"
      >
        <img :src="item.icon_url" :alt="item.name" width="18" height="18" class="size-4" />
      </span>
      <span
        v-else
        class="block truncate rounded-full px-2 text-[0.6875rem] leading-5 font-semibold ring-1"
        :class="
          item.level === 'contains'
            ? 'bg-orange-400/15 text-orange-200 ring-orange-300/40'
            : 'bg-white/5 text-(--sr-mute) ring-white/10'
        "
      >
        {{ item.name }}
      </span>
    </li>
    <li
      v-if="hidden.length"
      :title="hidden.map((item) => item.name).join(', ')"
      class="rounded-full bg-white/10 px-2 text-[0.6875rem] leading-5 font-semibold text-(--sr-ink)"
    >
      <span aria-hidden="true">+{{ hidden.length }}</span>
      <span class="sr-only">{{ hidden.map((item) => item.name).join(', ') }}</span>
    </li>
  </ul>
</template>
