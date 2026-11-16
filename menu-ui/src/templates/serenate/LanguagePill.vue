<script setup lang="ts">
import type { LanguageOption } from '../../api/types'
import AppIcon from '../../components/AppIcon.vue'

/**
 * Dock içindeki dil seçici: kapalıyken dil kodu ("TR") görünür, açılan yerel listede dillerin
 * kendi adları. Görünmez <select> hapın üstüne gerilir; klavye ve ekran okuyucu onu kullanır.
 */
defineProps<{ languages: LanguageOption[]; value: string; label: string }>()
const emit = defineEmits<{ change: [code: string] }>()

function onChange(event: Event): void {
  emit('change', (event.target as HTMLSelectElement).value)
}
</script>

<template>
  <label
    class="relative inline-flex h-11 shrink-0 items-center gap-0.5 rounded-full bg-white/8 ps-3.5 pe-2.5 text-sm font-semibold tracking-wide text-(--sr-ink) transition-colors focus-within:ring-2 focus-within:ring-(--pm-accent) hover:bg-white/14"
  >
    <span class="sr-only">{{ label }}</span>
    <span aria-hidden="true">{{ value.toUpperCase() }}</span>
    <AppIcon name="chevronDown" class="size-4 text-(--sr-mute)" />
    <select
      :value="value"
      class="absolute inset-0 size-full cursor-pointer appearance-none rounded-full opacity-0"
      @change="onChange"
    >
      <option v-for="language in languages" :key="language.code" :value="language.code">
        {{ language.native_name }}
      </option>
    </select>
  </label>
</template>
