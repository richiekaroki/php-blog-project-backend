import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User } from '@/types'
import { api } from '@/api/client'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed(() => !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')

  async function fetchUser() {
    try {
      const response = await api.get('/admin/login.php', {
        params: { action: 'status' },
      })
      if (response.data?.user) {
        user.value = response.data.user
      }
    } catch {
      user.value = null
    }
  }

  async function logout() {
    try {
      await api.get('/admin/login.php?action=logout')
    } finally {
      user.value = null
    }
  }

  async function setUser(userData: User) {
    user.value = userData
  }

  return {
    user,
    loading,
    error,
    isAuthenticated,
    isAdmin,
    logout,
    fetchUser,
    setUser,
  }
})
