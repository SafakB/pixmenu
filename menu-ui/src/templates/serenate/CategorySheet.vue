<script setup lang="ts">
import { nextTick, onBeforeUnmount, useTemplateRef, watch } from 'vue'
import type { Category, Menu } from '../../api/types'
import AppIcon from '../../components/AppIcon.vue'
import { useMenuHelpers } from '../../core/menuHelpers'

/**
 * Menünün "içindekiler" sayfası: dock'taki kategori düğmesiyle alttan açılır.
 * Kategori seçilince kapanır ve üst bileşen o bölüme kaydırır.
 */
const props = defineProps<{ open: boolean; categories: Category[]; activeId: number | null; menu: Menu }>()
const emit = defineEmits<{ close: []; select: [id: number] }>()

const { t } = useMenuHelpers(() => props.menu)
const dialog = useTemplateRef<HTMLDialogElement>('dialog')

function lockScroll(locked: boolean): void {
  document.documentElement.classList.toggle('overflow-hidden', locked)
}

watch(
  () => props.open,
  async (open) => {
    const element = dialog.value

    if (element === null) {
      return
    }

    if (open && !element.open) {
      element.showModal()
      lockScroll(true)
      await nextTick()
      element.querySelector('[aria-current="true"]')?.scrollIntoView({ block: 'center' })
    } else if (!open && element.open) {
      element.close()
    }
  },
)

function close(): void {
  dialog.value?.close()
}

function onClose(): void {
  lockScroll(false)
  emit('close')
}

function onBackdropClick(event: MouseEvent): void {
  if (event.target === dialog.value) {
    close()
  }
}

function choose(id: number): void {
  // Kilit `close` olayını beklemeden kalkar: üst bileşen hemen sayfayı kaydıracak.
  lockScroll(false)
  close()
  emit('select', id)
}

function number(index: number): string {
  return String(index + 1).padStart(2, '0')
}

function count(category: Category): string {
  const n = category.products.length

  return t(n === 1 ? 'productCountOne' : 'productCount', { n })
}

onBeforeUnmount(() => lockScroll(false))
</script>

<template>
  <dialog
    ref="dialog"
    aria-labelledby="serenate-kategoriler"
    class="mx-auto mt-auto mb-0 max-h-[80dvh] w-full max-w-none overflow-y-auto overscroll-contain rounded-t-[2rem] bg-[#1c1626] p-0 text-(--sr-ink) shadow-[0_-20px_60px_rgb(0_0_0/0.6)] ring-1 ring-white/10 transition-[translate,opacity] duration-300 ease-out backdrop:bg-black/60 backdrop:backdrop-blur-sm sm:m-auto sm:max-w-md sm:rounded-[2rem] starting:open:translate-y-full starting:open:opacity-0 sm:starting:open:translate-y-6 motion-reduce:transition-none"
    @close="onClose"
    @click="onBackdropClick"
  >
    <div class="sticky top-0 z-10 bg-[#1c1626]/95 px-6 pt-3 pb-3 backdrop-blur-md">
      <span aria-hidden="true" class="mx-auto block h-1 w-10 rounded-full bg-white/25 sm:hidden" />
      <div class="mt-3 flex items-center gap-3">
        <h2 id="serenate-kategoriler" class="min-w-0 flex-1 font-serif text-2xl italic">{{ t('categories') }}</h2>
        <button
          type="button"
          :aria-label="t('close')"
          class="grid size-10 shrink-0 place-items-center rounded-full bg-white/8 text-(--sr-ink) transition-colors hover:bg-white/14 focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none"
          @click="close"
        >
          <AppIcon name="close" class="size-5" />
        </button>
      </div>
    </div>

    <ul class="px-3 pb-[calc(env(safe-area-inset-bottom)+1rem)]">
      <li v-for="(category, index) in categories" :key="category.id">
        <button
          type="button"
          class="flex w-full items-center gap-4 rounded-2xl px-3 py-3 text-start transition-colors focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none"
          :class="category.id === activeId ? 'bg-(--pm-accent)/12' : 'hover:bg-white/5'"
          :aria-current="category.id === activeId ? 'true' : undefined"
          @click="choose(category.id)"
        >
          <span
            aria-hidden="true"
            class="w-6 shrink-0 text-xs font-semibold tabular-nums"
            :class="category.id === activeId ? 'text-(--pm-accent)' : 'text-(--sr-mute)/70'"
          >
            {{ number(index) }}
          </span>
          <span class="min-w-0 flex-1">
            <span
              class="block truncate font-serif text-xl leading-snug"
              :class="category.id === activeId ? 'text-(--pm-accent)' : ''"
            >
              {{ category.name }}
            </span>
            <span class="block text-xs text-(--sr-mute)">{{ count(category) }}</span>
          </span>
          <span v-if="category.emoji" aria-hidden="true" class="shrink-0 text-2xl leading-none">
            {{ category.emoji }}
          </span>
        </button>
      </li>
    </ul>
  </dialog>
</template>
