import { nextTick, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue'
import type { Product } from '../api/types'

function lockScroll(locked: boolean): void {
  document.documentElement.classList.toggle('overflow-hidden', locked)
}

/**
 * Ürün detayı için yerel <dialog> davranışı: ürün seçilince showModal + sayfa kaydırma kilidi,
 * null olunca kapanır. Odak hapsi ve Esc tarayıcıdan gelir.
 *
 * Kullanım: <dialog ref="dialog" @close="onClose" @click="onBackdropClick">, içerik `shown`'dan.
 * Eleman `ref="dialog"` adıyla bulunur (useTemplateRef).
 */
export function useProductDialog(product: () => Product | null, onClosed: () => void) {
  const dialog = useTemplateRef<HTMLDialogElement>('dialog')
  const shown = ref<Product | null>(null) // kapanırken içerik boşalmasın

  watch(product, async (next) => {
    const element = dialog.value

    if (element === null) {
      return
    }

    if (next !== null) {
      shown.value = next
      await nextTick()

      if (!element.open) {
        element.showModal()
        lockScroll(true)
      }
    } else if (element.open) {
      element.close()
    }
  })

  function close(): void {
    dialog.value?.close()
  }

  function onClose(): void {
    lockScroll(false)
    onClosed()
  }

  function onBackdropClick(event: MouseEvent): void {
    if (event.target === dialog.value) {
      close()
    }
  }

  onBeforeUnmount(() => lockScroll(false))

  return { shown, close, onClose, onBackdropClick }
}
