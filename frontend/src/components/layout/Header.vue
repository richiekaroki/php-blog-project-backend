<script setup lang="ts">
import { useRoute } from 'vue-router'
import { Bell, Menu, Sun, Moon } from 'lucide-vue-next'
import { useDarkMode } from '@/composables/useDarkMode'

const route = useRoute()
const { isDark, toggle } = useDarkMode()

const emit = defineEmits<{
  'toggle-sidebar': []
}>()

const pageTitle: Record<string, string> = {
  '/admin': 'Dashboard',
  '/admin/blogs': 'Blog Management',
  '/admin/categories': 'Category Management',
}
</script>

<template>
  <header class="h-16 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between px-4 sm:px-6">
    <div class="flex items-center gap-3">
      <button
        @click="emit('toggle-sidebar')"
        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 lg:hidden"
      >
        <Menu class="w-5 h-5" />
      </button>
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
        {{ pageTitle[route.path] || 'Dashboard' }}
      </h2>
    </div>

    <div class="flex items-center gap-2">
      <button
        @click="toggle"
        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800"
        :title="isDark ? 'Light mode' : 'Dark mode'"
      >
        <Sun v-if="isDark" class="w-5 h-5" />
        <Moon v-else class="w-5 h-5" />
      </button>
      <button class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
        <Bell class="w-5 h-5" />
      </button>
    </div>
  </header>
</template>
