<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import type { Menu, Product } from '../api/types'
import { useMenuHelpers } from '../core/menuHelpers'
import AllergenList from './AllergenList.vue'
import AppIcon from './AppIcon.vue'

/**
 * Ürün detayı: mobilde alttan açılan sayfa, geniş ekranda ortada pencere.
 * Yerel <dialog>: odak hapsi, Esc ile kapanma ve erişilebilirlik tarayıcıdan gelir.
 */
const props = defineProps<{ product: Product | null; menu: Menu }>()
const emit = defineEmits<{ close: [] }>()

const { t, price } = useMenuHelpers(() => props.menu)
const dialog = ref<HTMLDialogElement | null>(null)
const shown = ref<Product | null>(null) // kapanırken içerik boşalmasın

function lockScroll(locked: boolean): void {
  document.documentElement.classList.toggle('overflow-hidden', locked)
}

watch(
  () => props.product,
  async (product) => {
    const element = dialog.value

    if (element === null) {
      return
    }

    if (product !== null) {
      shown.value = product
      await nextTick()

      if (!element.open) {
        element.showModal()
        lockScroll(true)
      }
    } else if (element.open) {
      element.close()
    }
  },
)

function onClose(): void {
  lockScroll(false)
  emit('close')
}

function onBackdropClick(event: MouseEvent): void {
  if (event.target === dialog.value) {
    dialog.value?.close()
  }
}

onBeforeUnmount(() => lockScroll(false))
</script>

<template>
  <dialog
    ref="dialog"
    :aria-labelledby="shown ? `urun-${shown.id}` : undefined"
    class="m-0 mt-auto max-h-[92dvh] w-full max-w-none overflow-y-auto overscroll-contain rounded-t-2xl bg-white p-0 text-stone-900 shadow-2xl transition-[translate,opacity] duration-200 ease-out backdrop:bg-stone-950/55 starting:open:translate-y-10 starting:open:opacity-0 motion-reduce:transition-none sm:m-auto sm:max-w-lg sm:rounded-2xl"
    @close="onClose"
    @click="onBackdropClick"
  >
    <article v-if="shown" class="relative">
      <img
        v-if="shown.image_url"
        :src="shown.image_url"
        alt=""
        width="1600"
        height="1200"
        class="aspect-4/3 w-full bg-stone-100 object-cover"
      />

      <button
        type="button"
        :aria-label="t('close')"
        class="absolute end-3 top-3 rounded-full bg-white/90 p-2 text-stone-700 shadow-sm backdrop-blur hover:bg-white focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none"
        @click="dialog?.close()"
      >
        <AppIcon name="close" class="size-5" />
      </button>

      <div class="px-5 pt-5 pb-8" :class="shown.image_url ? '' : 'pt-14'">
        <div class="flex items-baseline gap-4">
          <h2 :id="`urun-${shown.id}`" class="min-w-0 flex-1 text-2xl leading-snug font-semibold text-balance">
            {{ shown.name }}
          </h2>
          <span class="shrink-0 text-xl font-semibold text-(--pm-accent) tabular-nums">{{ price(shown) }}</span>
        </div>

        <p v-if="shown.description" class="mt-3 leading-relaxed whitespace-pre-line text-stone-700">
          {{ shown.description }}
        </p>

        <section v-if="shown.allergens.length" class="mt-6 border-t border-stone-200 pt-4">
          <h3 class="mb-3 font-semibold">{{ t('allergens') }}</h3>
          <AllergenList :list="shown.allergens" :menu="menu" detailed />
        </section>
      </div>
    </article>
  </dialog>
</template>
