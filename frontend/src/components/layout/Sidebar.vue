<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useRoute } from 'vue-router'
import {
  LayoutDashboard,
  FileText,
  Tag,
  LogOut,
  X,
} from 'lucide-vue-next'

const route = useRoute()
const authStore = useAuthStore()

const emit = defineEmits<{
  close: []
}>()

const navItems = [
  { name: 'Dashboard', route: '/admin', icon: LayoutDashboard },
  { name: 'Blogs', route: '/admin/blogs', icon: FileText },
  { name: 'Categories', route: '/admin/categories', icon: Tag },
]
</script>

<template>
  <aside class="w-64 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex flex-col h-full">
    <div class="p-6 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Blog Admin</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Management Panel</p>
      </div>
      <button
        @click="emit('close')"
        class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 lg:hidden"
      >
        <X class="w-5 h-5" />
      </button>
    </div>

    <nav class="flex-1 p-4 space-y-1">
      <RouterLink
        v-for="item in navItems"
        :key="item.route"
        :to="item.route"
        @click="emit('close')"
        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
        :class="[
          route.path === item.route
            ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white'
            : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white',
        ]"
      >
        <component :is="item.icon" class="w-5 h-5" />
        {{ item.name }}
      </RouterLink>
    </nav>

    <div class="p-4 border-t border-gray-200 dark:border-gray-800">
      <div class="flex items-center gap-3 px-3 py-2">
        <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
          <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
            {{ authStore.user?.username?.charAt(0)?.toUpperCase() || 'A' }}
          </span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
            {{ authStore.user?.username || 'Admin' }}
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-400 capitalize">
            {{ authStore.user?.role || 'admin' }}
          </p>
        </div>
        <button
          @click="authStore.logout()"
          class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded"
          title="Logout"
        >
          <LogOut class="w-4 h-4" />
        </button>
      </div>
    </div>
  </aside>
</template>
