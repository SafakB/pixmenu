<script setup lang="ts">
import { computed } from 'vue'
import type { Allergen, AllergenRef, Menu, Product } from '../../api/types'
import AppIcon from '../../components/AppIcon.vue'
import { useMenuHelpers } from '../../core/menuHelpers'
import { useProductDialog } from '../../core/productDialog'

/**
 * Ürün penceresi: her ekran boyutunda ortada, bulanık arka plan üstünde yuvarlak kart.
 * Alerjenler seviyesine göre iki grupta ("İçerir" / "İz miktarda içerebilir") haplar halinde.
 */
const props = defineProps<{ product: Product | null; menu: Menu }>()
const emit = defineEmits<{ close: [] }>()

const { t, price, allergensById } = useMenuHelpers(() => props.menu)
const { shown, close, onClose, onBackdropClick } = useProductDialog(
  () => props.product,
  () => emit('close'),
)

const allergenGroups = computed(() => {
  const groups: Record<AllergenRef['level'], Allergen[]> = { contains: [], may_contain: [] }

  for (const entry of shown.value?.allergens ?? []) {
    const allergen = allergensById.value.get(entry.id)

    if (allergen) {
      groups[entry.level].push(allergen)
    }
  }

  return [
    { level: 'contains' as const, label: t('contains'), items: groups.contains },
    { level: 'may_contain' as const, label: t('mayContain'), items: groups.may_contain },
  ].filter((group) => group.items.length > 0)
})
</script>

<template>
  <dialog
    ref="dialog"
    :aria-labelledby="shown ? `urun-${shown.id}` : undefined"
    class="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-md overflow-y-auto overscroll-contain rounded-3xl bg-white p-0 text-slate-800 shadow-2xl transition-[scale,opacity] duration-200 ease-out backdrop:bg-slate-900/40 backdrop:backdrop-blur-sm starting:open:scale-95 starting:open:opacity-0 motion-reduce:transition-none"
    @close="onClose"
    @click="onBackdropClick"
  >
    <article v-if="shown" class="relative">
      <img
        v-if="shown.image_url"
        :src="shown.image_url"
        alt=""
        width="1600"
        height="900"
        class="aspect-video w-full bg-slate-100 object-cover"
      />

      <button
        type="button"
        :aria-label="t('close')"
        class="absolute end-3 top-3 grid size-9 place-items-center rounded-full backdrop-blur-sm transition-colors focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
        :class="
          shown.image_url
            ? 'bg-slate-900/35 text-white hover:bg-slate-900/55'
            : 'bg-slate-100 text-slate-600 hover:bg-slate-200 focus-visible:ring-(--pm-accent)'
        "
        @click="close"
      >
        <AppIcon name="close" class="size-5" />
      </button>

      <div class="px-6 pt-5 pb-6" :class="shown.image_url ? '' : 'pt-14'">
        <div class="flex items-baseline gap-4">
          <h2 :id="`urun-${shown.id}`" class="min-w-0 flex-1 text-2xl leading-snug font-bold text-balance">
            {{ shown.name }}
          </h2>
          <span class="shrink-0 text-xl font-bold text-(--pm-accent) tabular-nums">{{ price(shown) }}</span>
        </div>

        <p v-if="shown.description" class="mt-3 leading-relaxed whitespace-pre-line text-slate-500">
          {{ shown.description }}
        </p>

        <section v-if="allergenGroups.length" class="mt-5 space-y-3" :aria-label="t('allergens')">
          <div v-for="group in allergenGroups" :key="group.level">
            <h3 class="mb-1.5 text-xs font-semibold tracking-wide text-slate-400 uppercase">{{ group.label }}</h3>
            <ul class="flex flex-wrap gap-2">
              <li
                v-for="item in group.items"
                :key="item.id"
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1"
                :class="
                  group.level === 'contains'
                    ? 'bg-orange-50 text-orange-700 ring-orange-200'
                    : 'bg-slate-50 text-slate-600 ring-slate-200'
                "
              >
                <img v-if="item.icon_url" :src="item.icon_url" alt="" width="16" height="16" class="size-4" />
                {{ item.name }}
              </li>
            </ul>
          </div>
        </section>

        <button
          type="button"
          class="mt-6 w-full rounded-2xl bg-(--pm-accent) py-3.5 text-base font-bold text-white shadow-lg shadow-rose-500/30 transition hover:brightness-110 focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:ring-offset-2 focus-visible:outline-none"
          @click="close"
        >
          {{ t('close') }}
        </button>
      </div>
    </article>
  </dialog>
</template>
