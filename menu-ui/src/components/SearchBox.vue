<script setup lang="ts">
import AppIcon from './AppIcon.vue'

const model = defineModel<string>({ required: true })

defineProps<{ placeholder: string; clearLabel: string }>()
</script>

<template>
  <div class="relative">
    <AppIcon name="search" class="pointer-events-none absolute start-3 top-1/2 size-5 -translate-y-1/2 text-stone-400" />
    <!-- text-base: iOS 16px altındaki alanlarda odaklanınca sayfayı yakınlaştırır -->
    <input
      v-model="model"
      type="search"
      :placeholder="placeholder"
      :aria-label="placeholder"
      enterkeyhint="search"
      autocomplete="off"
      class="w-full rounded-lg border border-stone-200 bg-stone-50 py-2.5 ps-10 pe-10 text-base text-stone-900 outline-none placeholder:text-stone-400 focus:border-transparent focus:bg-white focus:ring-2 focus:ring-(--pm-accent) [&::-webkit-search-cancel-button]:appearance-none"
    />
    <button
      v-if="model"
      type="button"
      :aria-label="clearLabel"
      class="absolute end-1.5 top-1/2 -translate-y-1/2 rounded-full p-1.5 text-stone-500 hover:bg-stone-200 focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none"
      @click="model = ''"
    >
      <AppIcon name="close" class="size-4" />
    </button>
  </div>
</template>
