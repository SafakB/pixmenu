<script setup lang="ts">
import StatusView from '../components/StatusView.vue'
import { useMenuStore } from '../core/store'

const { state } = useMenuStore()
</script>

<template>
  <StatusView kind="not_found" :lang="state.site?.language.code ?? state.lang ?? 'tr'" home-link />
</template>
