<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { translate } from '../core/i18n'

/** homeLink: bilinmeyen şube adresinde restoranın şube listesine dönüş bağlantısı. */
defineProps<{ kind: 'loading' | 'not_found' | 'error'; lang: string; homeLink?: boolean }>()
defineEmits<{ retry: [] }>()
</script>

<template>
  <main class="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-6 py-16" :aria-busy="kind === 'loading'">
    <template v-if="kind === 'loading'">
      <p class="sr-only">{{ translate(lang, 'loading') }}</p>
      <div class="space-y-4 motion-safe:animate-pulse" aria-hidden="true">
        <div class="mx-auto size-16 rounded-full bg-stone-200" />
        <div class="mx-auto h-7 w-1/2 rounded bg-stone-200" />
        <div class="h-10 rounded-lg bg-stone-100" />
        <div v-for="row in 4" :key="row" class="space-y-2 py-2">
          <div class="h-4 w-3/4 rounded bg-stone-200" />
          <div class="h-3 w-full rounded bg-stone-100" />
        </div>
      </div>
    </template>

    <template v-else-if="kind === 'not_found'">
      <h1 class="font-serif text-3xl font-semibold">{{ translate(lang, 'notFoundTitle') }}</h1>
      <p class="mt-3 text-stone-600">{{ translate(lang, 'notFoundText') }}</p>
      <RouterLink
        v-if="homeLink"
        to="/"
        class="mt-6 self-start rounded-lg bg-stone-900 px-5 py-2.5 font-medium text-white hover:bg-stone-700 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none"
      >
        {{ translate(lang, 'allBranches') }}
      </RouterLink>
    </template>

    <template v-else>
      <p class="text-lg text-stone-700">{{ translate(lang, 'loadError') }}</p>
      <button
        type="button"
        class="mt-6 self-start rounded-lg bg-stone-900 px-5 py-2.5 font-medium text-white hover:bg-stone-700 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none"
        @click="$emit('retry')"
      >
        {{ translate(lang, 'retry') }}
      </button>
    </template>
  </main>
</template>
