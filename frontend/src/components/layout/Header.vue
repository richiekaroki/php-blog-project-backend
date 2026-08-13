<script setup lang="ts">
import { useRoute } from 'vue-router'
import { Bell } from 'lucide-vue-next'

const route = useRoute()

const pageTitle: Record<string, string> = {
  '/': 'Dashboard',
  '/blogs': 'Blog Management',
  '/categories': 'Category Management',
}
</script>

<template>
  <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6">
    <h2 class="text-lg font-semibold text-gray-900">
      {{ pageTitle[route.path] || 'Dashboard' }}
    </h2>

    <div class="flex items-center gap-4">
      <button class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
        <Bell class="w-5 h-5" />
      </button>
    </div>
  </header>
</template>
