<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { Site } from '../api/types'
import { translate } from '../core/i18n'
import { useMenuStore } from '../core/store'
import LanguageSwitcher from './LanguageSwitcher.vue'

/**
 * masthead: basılı menü kapağı gibi ortalanmış (standart şablon, şube seçimi)
 * bar:      sola (başa) yaslı kompakt satır (kategori şablonu)
 */
const props = withDefaults(defineProps<{ site: Site; subtitle?: string | null; variant?: 'masthead' | 'bar' }>(), {
  subtitle: null,
  variant: 'masthead',
})

const { setLanguage } = useMenuStore()
const lang = computed(() => props.site.language.code)
const showBranchesLink = computed(() => props.site.menu !== null && props.site.branches.length > 1)
</script>

<template>
  <header v-if="variant === 'masthead'" class="relative px-4 pt-10 pb-6 text-center">
    <div v-if="site.languages.length > 1" class="absolute end-3 top-3">
      <LanguageSwitcher
        :languages="site.languages"
        :value="lang"
        :label="translate(lang, 'language')"
        @change="setLanguage"
      />
    </div>

    <!-- Logo kırpılmaz: yazı (wordmark) logolar da kendi oranında görünür. -->
    <img
      v-if="site.restaurant.logo_url"
      :src="site.restaurant.logo_url"
      alt=""
      height="72"
      class="mx-auto mb-4 h-18 w-auto max-w-48 object-contain"
    />
    <h1 class="mx-auto max-w-[20ch] font-serif text-[2rem] leading-tight font-semibold text-balance">
      {{ site.restaurant.name }}
    </h1>
    <p v-if="subtitle" class="mt-1.5 text-stone-500">{{ subtitle }}</p>
    <RouterLink
      v-if="showBranchesLink"
      to="/"
      class="mt-2 inline-block text-sm text-stone-500 underline decoration-stone-300 underline-offset-4 hover:text-(--pm-accent)"
    >
      {{ translate(lang, 'allBranches') }}
    </RouterLink>
  </header>

  <header v-else class="mx-auto flex max-w-2xl items-center gap-3 px-4 pt-5 pb-3">
    <img
      v-if="site.restaurant.logo_url"
      :src="site.restaurant.logo_url"
      alt=""
      height="48"
      class="h-12 w-auto max-w-28 shrink-0 object-contain"
    />
    <div class="min-w-0 flex-1">
      <h1 class="truncate text-xl leading-tight font-bold tracking-tight">{{ site.restaurant.name }}</h1>
      <p v-if="subtitle || showBranchesLink" class="truncate text-sm text-stone-500">
        <span v-if="subtitle">{{ subtitle }}</span>
        <RouterLink
          v-if="showBranchesLink"
          to="/"
          class="ms-2 underline decoration-stone-300 underline-offset-4 hover:text-(--pm-accent)"
        >
          {{ translate(lang, 'allBranches') }}
        </RouterLink>
      </p>
    </div>
    <LanguageSwitcher
      v-if="site.languages.length > 1"
      :languages="site.languages"
      :value="lang"
      :label="translate(lang, 'language')"
      @change="setLanguage"
    />
  </header>
</template>
