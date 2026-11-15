<script setup lang="ts">
import { watchEffect } from 'vue'
import { RouterView } from 'vue-router'
import { applyDocumentMeta } from './core/meta'
import { useMenuStore } from './core/store'

const { state } = useMenuStore()

watchEffect(() => {
  if (state.site) {
    applyDocumentMeta(state.site)
  }
})
</script>

<template>
  <RouterView />
</template>
