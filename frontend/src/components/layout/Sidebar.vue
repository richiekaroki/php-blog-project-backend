<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  LayoutDashboard,
  FileText,
  Tag,
  LogOut,
} from 'lucide-vue-next'

const route = useRoute()
const authStore = useAuthStore()

const navItems = [
  { name: 'Dashboard', route: '/', icon: LayoutDashboard },
  { name: 'Blogs', route: '/blogs', icon: FileText },
  { name: 'Categories', route: '/categories', icon: Tag },
]
</script>

<template>
  <aside class="w-64 bg-white border-r border-gray-200 flex flex-col">
    <div class="p-6 border-b border-gray-200">
      <h1 class="text-xl font-bold text-gray-900">Blog Admin</h1>
      <p class="text-sm text-gray-500 mt-1">Management Panel</p>
    </div>

    <nav class="flex-1 p-4 space-y-1">
      <RouterLink
        v-for="item in navItems"
        :key="item.route"
        :to="item.route"
        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
        :class="[
          route.path === item.route
            ? 'bg-gray-100 text-gray-900'
            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
        ]"
      >
        <component :is="item.icon" class="w-5 h-5" />
        {{ item.name }}
      </RouterLink>
    </nav>

    <div class="p-4 border-t border-gray-200">
      <div class="flex items-center gap-3 px-3 py-2">
        <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
          <span class="text-sm font-medium text-gray-600">
            {{ authStore.user?.username?.charAt(0)?.toUpperCase() || 'A' }}
          </span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-gray-900 truncate">
            {{ authStore.user?.username || 'Admin' }}
          </p>
          <p class="text-xs text-gray-500 capitalize">
            {{ authStore.user?.role || 'admin' }}
          </p>
        </div>
        <button
          @click="authStore.logout()"
          class="p-1 text-gray-400 hover:text-gray-600 rounded"
          title="Logout"
        >
          <LogOut class="w-4 h-4" />
        </button>
      </div>
    </div>
  </aside>
</template>
