<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import BranchPicker from '../components/BranchPicker.vue'
import StatusView from '../components/StatusView.vue'
import { translate } from '../core/i18n'
import { useMenuStore } from '../core/store'
import { templateComponent } from '../templates/registry'

const props = defineProps<{ branch?: string }>()

const route = useRoute()
const { state, load, initLanguage } = useMenuStore()

initLanguage(route.query.lang)

watch(
  () => props.branch,
  (branch) => void load(branch),
  { immediate: true },
)

// Yalnızca geliştirmede: ?sablon=kategori ile şubenin şablonu değiştirilmeden önizlenir (build'de silinir).
const previewTemplate = computed(() =>
  import.meta.env.DEV && typeof route.query.sablon === 'string' ? route.query.sablon : null,
)

const template = computed(() =>
  state.site?.menu ? templateComponent(previewTemplate.value ?? state.site.menu.branch.template) : null,
)
const lang = computed(() => state.site?.language.code ?? state.lang ?? 'tr')
</script>

<template>
  <!-- Şube/dil değişirken mevcut içerik kalır; üstte ince bir ilerleme çizgisi görünür. -->
  <div
    v-if="state.loading && state.site"
    class="fixed inset-x-0 top-0 z-50 h-0.5 bg-(--pm-accent) motion-safe:animate-pulse"
    role="progressbar"
    :aria-label="translate(lang, 'loading')"
  />

  <StatusView v-if="state.error === 'not_found'" kind="not_found" :lang="lang" :home-link="Boolean(props.branch)" />
  <StatusView v-else-if="!state.site && state.error === 'network'" kind="error" :lang="lang" @retry="load(props.branch)" />
  <StatusView v-else-if="!state.site" kind="loading" :lang="lang" />
  <BranchPicker v-else-if="!state.site.menu" :site="state.site" />
  <component :is="template" v-else-if="template" :site="state.site" :menu="state.site.menu" />

  <div
    v-if="state.site && state.error === 'network'"
    role="alert"
    class="fixed inset-x-4 bottom-4 z-50 mx-auto flex max-w-md items-center gap-3 rounded-xl bg-stone-900 px-4 py-3 text-sm text-white shadow-lg"
  >
    <span class="flex-1">{{ translate(lang, 'loadError') }}</span>
    <button type="button" class="font-semibold underline underline-offset-4" @click="load(state.branch)">
      {{ translate(lang, 'retry') }}
    </button>
  </div>
</template>
