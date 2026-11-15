<script setup lang="ts">
import type { LanguageOption } from '../api/types'
import AppIcon from './AppIcon.vue'

defineProps<{ languages: LanguageOption[]; value: string; label: string }>()
const emit = defineEmits<{ change: [code: string] }>()

function onChange(event: Event): void {
  emit('change', (event.target as HTMLSelectElement).value)
}
</script>

<template>
  <label class="relative inline-flex items-center">
    <span class="sr-only">{{ label }}</span>
    <select
      :value="value"
      class="cursor-pointer appearance-none rounded-full border border-stone-300 bg-white/90 py-1.5 ps-3 pe-8 text-sm font-medium text-stone-800 outline-none focus-visible:ring-2 focus-visible:ring-(--pm-accent)"
      @change="onChange"
    >
      <option v-for="language in languages" :key="language.code" :value="language.code">
        {{ language.native_name }}
      </option>
    </select>
    <AppIcon name="chevronDown" class="pointer-events-none absolute end-2.5 size-4 text-stone-500" />
  </label>
</template>
