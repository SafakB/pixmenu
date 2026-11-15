<script setup lang="ts">
import type { Menu } from '../api/types'
import { translate } from '../core/i18n'
import AppIcon from './AppIcon.vue'

defineProps<{ menu: Menu }>()
</script>

<template>
  <footer
    v-if="menu.branch.address || menu.branch.phone"
    class="mx-auto max-w-2xl space-y-2 px-4 pt-10 pb-12 text-sm text-stone-500"
  >
    <p v-if="menu.branch.address" class="flex gap-2">
      <AppIcon name="pin" class="mt-0.5 size-4 shrink-0" />
      <span class="whitespace-pre-line">{{ menu.branch.address }}</span>
    </p>
    <p v-if="menu.branch.phone" class="flex gap-2">
      <AppIcon name="phone" class="mt-0.5 size-4 shrink-0" />
      <a
        :href="`tel:${menu.branch.phone.replace(/[^\d+]/g, '')}`"
        :aria-label="`${translate(menu.language.code, 'call')}: ${menu.branch.phone}`"
        class="underline decoration-stone-300 underline-offset-4 hover:text-(--pm-accent)"
        dir="ltr"
      >
        {{ menu.branch.phone }}
      </a>
    </p>
  </footer>
</template>
