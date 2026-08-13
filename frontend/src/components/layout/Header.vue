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
  <header class="h-16 bg-card border-b border-border flex items-center justify-between px-4 sm:px-6">
    <div class="flex items-center gap-3">
      <button
        @click="emit('toggle-sidebar')"
        class="p-2 text-muted-foreground hover:text-foreground hover:bg-muted rounded-lg lg:hidden transition-colors"
      >
        <Menu class="w-5 h-5" />
      </button>
      <h2 class="font-display text-lg font-semibold text-foreground">
        {{ pageTitle[route.path] || 'Dashboard' }}
      </h2>
    </div>

    <div class="flex items-center gap-2">
      <button
        @click="toggle"
        class="p-2 text-muted-foreground hover:text-foreground hover:bg-muted rounded-lg transition-colors"
        :title="isDark ? 'Light mode' : 'Dark mode'"
      >
        <Sun v-if="isDark" class="w-5 h-5" />
        <Moon v-else class="w-5 h-5" />
      </button>
      <button class="p-2 text-muted-foreground hover:text-foreground hover:bg-muted rounded-lg transition-colors relative">
        <Bell class="w-5 h-5" />
        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-warm-orange rounded-full"></span>
      </button>
    </div>
  </header>
</template>
