<script setup lang="ts">
import { computed } from 'vue'
import type { Allergen, AllergenRef, Menu, Product } from '../../api/types'
import AppIcon from '../../components/AppIcon.vue'
import { useMenuHelpers } from '../../core/menuHelpers'
import { useProductDialog } from '../../core/productDialog'

/**
 * Ürün sayfası: telefonda alttan yükselir (başparmakla kapatılır), geniş ekranda ortada kart olur.
 * Alerjenler seviyesine göre iki grupta ("İçerir" / "Eser miktarda içerebilir") haplar halinde.
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
    class="mx-auto mt-auto mb-0 max-h-[92dvh] w-full max-w-none overflow-y-auto overscroll-contain rounded-t-[2rem] bg-[#1c1626] p-0 text-(--sr-ink) shadow-[0_-20px_60px_rgb(0_0_0/0.6)] ring-1 ring-white/10 transition-[translate,opacity] duration-300 ease-out backdrop:bg-black/60 backdrop:backdrop-blur-sm sm:m-auto sm:max-w-md sm:rounded-[2rem] starting:open:translate-y-full starting:open:opacity-0 sm:starting:open:translate-y-6 motion-reduce:transition-none"
    @close="onClose"
    @click="onBackdropClick"
  >
    <article v-if="shown" class="relative">
      <div v-if="shown.image_url" class="relative">
        <img
          :src="shown.image_url"
          alt=""
          width="1600"
          height="1200"
          class="aspect-4/3 w-full bg-white/5 object-cover"
        />
        <span aria-hidden="true" class="absolute inset-x-0 bottom-0 h-1/2 bg-linear-to-t from-[#1c1626] to-transparent" />
      </div>

      <!-- Tutamaç: alttan açılan sayfa olduğunu sezdirir (yalnızca telefonda). -->
      <span
        aria-hidden="true"
        class="absolute top-2.5 left-1/2 h-1 w-10 -translate-x-1/2 rounded-full sm:hidden"
        :class="shown.image_url ? 'bg-white/60' : 'bg-white/25'"
      />

      <button
        type="button"
        :aria-label="t('close')"
        class="absolute end-3 top-3 grid size-10 place-items-center rounded-full bg-[#14101c]/60 text-(--sr-ink) ring-1 ring-white/15 backdrop-blur-md transition-colors hover:bg-[#14101c]/85 focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none"
        @click="close"
      >
        <AppIcon name="close" class="size-5" />
      </button>

      <div
        class="relative px-6 pb-[calc(env(safe-area-inset-bottom)+1.5rem)]"
        :class="shown.image_url ? '-mt-8' : 'pt-16'"
      >
        <h2 :id="`urun-${shown.id}`" class="font-serif text-3xl leading-tight text-balance">
          {{ shown.name }}
        </h2>

        <p class="mt-3 flex items-center gap-4">
          <span class="font-serif text-2xl text-(--pm-accent) tabular-nums">{{ price(shown) }}</span>
          <span aria-hidden="true" class="h-px flex-1 bg-linear-to-r from-(--pm-accent)/50 to-transparent rtl:bg-linear-to-l" />
        </p>

        <p v-if="shown.description" class="mt-4 leading-relaxed whitespace-pre-line text-(--sr-mute)">
          {{ shown.description }}
        </p>

        <section v-if="allergenGroups.length" class="mt-6 space-y-4" :aria-label="t('allergens')">
          <div v-for="group in allergenGroups" :key="group.level">
            <h3 class="mb-2 text-[0.6875rem] font-semibold tracking-[0.2em] text-(--sr-mute) uppercase">
              {{ group.label }}
            </h3>
            <ul class="flex flex-wrap gap-2">
              <li
                v-for="item in group.items"
                :key="item.id"
                class="inline-flex items-center gap-1.5 rounded-full py-1 pe-3 text-xs font-semibold ring-1"
                :class="[
                  item.icon_url ? 'ps-1' : 'ps-3',
                  group.level === 'contains'
                    ? 'bg-orange-400/15 text-orange-200 ring-orange-300/40'
                    : 'bg-white/5 text-(--sr-mute) ring-white/12',
                ]"
              >
                <span v-if="item.icon_url" class="grid size-5 place-items-center rounded-full bg-[#f4ece1]">
                  <img :src="item.icon_url" alt="" width="16" height="16" class="size-3.5" />
                </span>
                {{ item.name }}
              </li>
            </ul>
          </div>
        </section>

        <button
          type="button"
          class="mt-7 w-full rounded-full bg-(--pm-accent) py-3.5 text-base font-semibold text-[#14101c] transition hover:brightness-110 focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:ring-offset-2 focus-visible:ring-offset-[#1c1626] focus-visible:outline-none active:scale-[0.98] motion-reduce:transition-none motion-reduce:active:scale-100"
          @click="close"
        >
          {{ t('close') }}
        </button>
      </div>
    </article>
  </dialog>
</template>
