<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useRoute } from 'vue-router'
import {
  LayoutDashboard,
  FileText,
  Tag,
  LogOut,
  X,
  User,
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
  { name: 'Profile', route: '/admin/profile', icon: User },
]
</script>

<template>
  <aside class="w-64 bg-card border-r border-border flex flex-col h-full">
    <div class="p-6 border-b border-border flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 bg-primary rounded-full flex items-center justify-center">
          <span class="text-primary-foreground font-display font-bold text-lg">W</span>
        </div>
        <div>
          <h1 class="font-display font-semibold text-foreground">WAM Blog</h1>
          <p class="text-xs text-muted-foreground mt-0.5">Content Studio</p>
        </div>
      </div>
      <button
        @click="emit('close')"
        class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-lg lg:hidden transition-colors"
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
        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all"
        :class="[
          route.path === item.route
            ? 'bg-primary/10 text-primary'
            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
        ]"
      >
        <component :is="item.icon" class="w-5 h-5" />
        {{ item.name }}
      </RouterLink>
    </nav>

    <div class="p-4 border-t border-border">
      <div class="flex items-center gap-3 px-3 py-2">
        <div class="w-9 h-9 rounded-full bg-forest-green/10 flex items-center justify-center">
          <span class="text-sm font-medium text-forest-green">
            {{ authStore.user?.username?.charAt(0)?.toUpperCase() || 'A' }}
          </span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-foreground truncate">
            {{ authStore.user?.username || 'Admin' }}
          </p>
          <p class="text-xs text-muted-foreground capitalize">
            {{ authStore.user?.role || 'admin' }}
          </p>
        </div>
        <button
          @click="authStore.logout()"
          class="p-1.5 text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded-lg transition-colors"
          title="Logout"
        >
          <LogOut class="w-4 h-4" />
        </button>
      </div>
    </div>
  </aside>
</template>
