<script setup lang="ts">
import { RouterLink } from 'vue-router'
import type { Site } from '../api/types'
import { translate } from '../core/i18n'
import AppHeader from './AppHeader.vue'
import AppIcon from './AppIcon.vue'

defineProps<{ site: Site }>()
</script>

<template>
  <div class="min-h-dvh bg-white">
    <AppHeader :site="site" :subtitle="translate(site.language.code, 'chooseBranch')" />

    <main class="mx-auto max-w-xl px-4 pb-16">
      <ul class="divide-y divide-stone-200 border-y border-stone-200">
        <li v-for="branch in site.branches" :key="branch.slug">
          <RouterLink
            :to="`/${encodeURIComponent(branch.slug)}`"
            class="group flex items-center gap-4 px-1 py-5 focus-visible:ring-2 focus-visible:ring-(--pm-accent) focus-visible:outline-none"
          >
            <span class="min-w-0 flex-1">
              <span class="block text-lg font-semibold group-hover:text-(--pm-accent)">{{ branch.name }}</span>
              <span v-if="branch.address" class="mt-0.5 block text-sm whitespace-pre-line text-stone-500">{{ branch.address }}</span>
            </span>
            <AppIcon name="chevronEnd" class="size-5 shrink-0 text-stone-400 rtl:rotate-180" />
          </RouterLink>
        </li>
      </ul>
    </main>
  </div>
</template>
