<script setup lang="ts">
import type { LanguageOption } from '../../api/types'
import AppIcon from '../../components/AppIcon.vue'

/**
 * Hap biçiminde dil seçici: kapalıyken dil kodu ("TR") görünür, açılan yerel listede
 * dillerin kendi adları. Görünmez <select> hapın üstüne gerilir; klavye ve ekran okuyucu onu kullanır.
 */
defineProps<{ languages: LanguageOption[]; value: string; label: string }>()
const emit = defineEmits<{ change: [code: string] }>()

function onChange(event: Event): void {
  emit('change', (event.target as HTMLSelectElement).value)
}
</script>

<template>
  <label
    class="relative inline-flex h-10 shrink-0 items-center gap-1 rounded-full bg-slate-100 ps-3.5 pe-2.5 text-sm font-bold text-slate-700 transition-colors focus-within:ring-2 focus-within:ring-(--pm-accent) hover:bg-slate-200/80"
  >
    <span class="sr-only">{{ label }}</span>
    <span aria-hidden="true">{{ value.toUpperCase() }}</span>
    <AppIcon name="chevronDown" class="size-4 text-slate-500" />
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
