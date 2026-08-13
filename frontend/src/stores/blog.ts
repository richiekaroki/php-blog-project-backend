import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Blog, Category, PaginatedResponse, ApiResponse } from '@/types'
import { api } from '@/api/client'

export const useBlogStore = defineStore('blog', () => {
  const blogs = ref<Blog[]>([])
  const categories = ref<Category[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pagination = ref({
    total: 0,
    page: 1,
    limit: 10,
    pages: 0,
  })

  async function fetchBlogs(page = 1, limit = 10) {
    loading.value = true
    error.value = null

    try {
      const response = await api.get<PaginatedResponse<Blog>>('/api/index.php', {
        params: { action: 'blogs', page, limit },
      })
      blogs.value = response.data.data
      pagination.value = response.data.pagination
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to fetch blogs'
    } finally {
      loading.value = false
    }
  }

  async function fetchBlog(id: number) {
    loading.value = true
    error.value = null

    try {
      const response = await api.get<{ success: boolean; data: Blog }>('/api/index.php', {
        params: { action: 'blogs', id },
      })
      return response.data.data
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to fetch blog'
      return null
    } finally {
      loading.value = false
    }
  }

  async function createBlog(data: FormData) {
    loading.value = true
    error.value = null

    try {
      const response = await api.post<ApiResponse<Blog>>('/api/index.php?action=blogs', data)
      await fetchBlogs()
      return response.data
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to create blog'
      return null
    } finally {
      loading.value = false
    }
  }

  async function updateBlog(id: number, data: FormData) {
    loading.value = true
    error.value = null

    try {
      const response = await api.put<ApiResponse<Blog>>(`/api/index.php?action=blogs&id=${id}`, data)
      await fetchBlogs()
      return response.data
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to update blog'
      return null
    } finally {
      loading.value = false
    }
  }

  async function deleteBlog(id: number) {
    loading.value = true
    error.value = null

    try {
      await api.delete(`/api/index.php?action=blogs&id=${id}`)
      await fetchBlogs()
      return true
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to delete blog'
      return false
    } finally {
      loading.value = false
    }
  }

  async function fetchCategories() {
    try {
      const response = await api.get<{ success: boolean; data: Category[] }>('/api/index.php', {
        params: { action: 'categories' },
      })
      categories.value = response.data.data
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to fetch categories'
    }
  }

  async function createCategory(data: { name: string; description: string }) {
    loading.value = true
    error.value = null

    try {
      const response = await api.post<ApiResponse<Category>>('/api/index.php?action=categories', data)
      await fetchCategories()
      return response.data
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to create category'
      return null
    } finally {
      loading.value = false
    }
  }

  async function updateCategory(id: number, data: { name: string; description: string }) {
    loading.value = true
    error.value = null

    try {
      const response = await api.put<ApiResponse<Category>>(`/api/index.php?action=categories&id=${id}`, data)
      await fetchCategories()
      return response.data
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to update category'
      return false
    } finally {
      loading.value = false
    }
  }

  async function deleteCategory(id: number) {
    loading.value = true
    error.value = null

    try {
      await api.delete(`/api/index.php?action=categories&id=${id}`)
      await fetchCategories()
      return true
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to delete category'
      return false
    } finally {
      loading.value = false
    }
  }

  return {
    blogs,
    categories,
    loading,
    error,
    pagination,
    fetchBlogs,
    fetchBlog,
    createBlog,
    updateBlog,
    deleteBlog,
    fetchCategories,
    createCategory,
    updateCategory,
    deleteCategory,
  }
})
