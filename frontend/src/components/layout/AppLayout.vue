<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRoute } from 'vue-router'
import Sidebar from './Sidebar.vue'
import Header from './Header.vue'

const authStore = useAuthStore()
const route = useRoute()
const sidebarOpen = ref(false)

const isLanding = route.name === 'landing'
const isLogin = route.name === 'login'
const isPublic = isLanding || isLogin

function closeSidebar() {
  sidebarOpen.value = false
}
</script>

<template>
  <div v-if="isPublic">
    <slot />
  </div>
  <div v-else class="flex h-screen bg-muted/30 overflow-hidden">
    <!-- Mobile sidebar overlay -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-40 bg-black/50 lg:hidden"
      @click="closeSidebar"
    />

    <!-- Sidebar -->
    <div
      :class="[
        'fixed inset-y-0 left-0 z-50 w-64 transform transition-transform duration-200 ease-in-out lg:relative lg:translate-x-0',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
      ]"
    >
      <Sidebar v-if="authStore.isAuthenticated" @close="closeSidebar" />
    </div>

    <!-- Main content -->
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
      <Header v-if="authStore.isAuthenticated" @toggle-sidebar="sidebarOpen = !sidebarOpen" />
      <main class="flex-1 overflow-y-auto p-4 sm:p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
