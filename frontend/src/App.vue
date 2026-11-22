<template>
  <v-app>
    <component :is="layout">
      <router-view />
    </component>
  </v-app>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { startAutoSync } from './mobile/offlineQueue'
import { useRoute } from 'vue-router'
import DeskLayout from './shared/layouts/DeskLayout.vue'
import CounterLayout from './shared/layouts/CounterLayout.vue'
import BlankLayout from './shared/layouts/BlankLayout.vue'

onMounted(startAutoSync)
const route = useRoute()
const layouts = { counter: CounterLayout, blank: BlankLayout }
const layout = computed(() => (route.meta.layout ? layouts[route.meta.layout] : DeskLayout))
</script>
