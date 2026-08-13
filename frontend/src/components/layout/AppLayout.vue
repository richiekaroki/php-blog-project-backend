<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useRoute } from 'vue-router'
import Sidebar from './Sidebar.vue'
import Header from './Header.vue'

const authStore = useAuthStore()
const route = useRoute()
</script>

<template>
  <div v-if="route.name === 'login'" class="min-h-screen">
    <slot />
  </div>
  <div v-else class="flex h-screen bg-gray-100">
    <Sidebar v-if="authStore.isAuthenticated" />
    <div class="flex-1 flex flex-col overflow-hidden">
      <Header v-if="authStore.isAuthenticated" />
      <main class="flex-1 overflow-y-auto p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
