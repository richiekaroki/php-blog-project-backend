import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User, LoginCredentials } from '@/types'
import { api } from '@/api/client'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const csrfToken = ref<string | null>(null)

  const isAuthenticated = computed(() => !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')

  async function fetchCsrfToken() {
    try {
      const response = await api.get('/admin/login.php')
      const match = response.data.match(/name="csrf_token"\s+value="([^"]+)"/)
      if (match) {
        csrfToken.value = match[1]
      }
    } catch {
      // CSRF token will be handled by session
    }
  }

  async function login(credentials: LoginCredentials) {
    loading.value = true
    error.value = null

    try {
      // First get CSRF token
      await fetchCsrfToken()

      const formData = new URLSearchParams()
      formData.append('username', credentials.username)
      formData.append('password', credentials.password)
      if (csrfToken.value) {
        formData.append('csrf_token', csrfToken.value)
      }

      const response = await api.post('/admin/login.php', formData, {
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      })

      if (response.data?.user) {
        user.value = response.data.user
      } else {
        await fetchUser()
      }
      return true
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Login failed'
      return false
    } finally {
      loading.value = false
    }
  }

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

  async function checkAuth() {
    await fetchUser()
  }

  return {
    user,
    loading,
    error,
    isAuthenticated,
    isAdmin,
    login,
    logout,
    checkAuth,
    fetchUser,
  }
})
