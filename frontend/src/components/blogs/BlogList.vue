<script setup lang="ts">
import type { Blog } from '@/types'
import { Pencil, Trash2 } from 'lucide-vue-next'

defineProps<{
  blogs: Blog[]
  loading: boolean
  pagination: {
    total: number
    page: number
    limit: number
    pages: number
  }
}>()

const emit = defineEmits<{
  edit: [blog: Blog]
  delete: [id: number]
  pageChange: [page: number]
}>()
</script>

<template>
  <div class="bg-white rounded-xl border border-gray-200">
    <div class="p-4 border-b border-gray-200">
      <h3 class="font-semibold text-gray-900">All Blogs ({{ pagination.total }})</h3>
    </div>

    <div v-if="loading" class="p-8 text-center text-gray-500">Loading...</div>

    <div v-else-if="blogs.length === 0" class="p-8 text-center text-gray-500">
      No blogs found. Create your first blog post!
    </div>

    <div v-else class="divide-y divide-gray-200">
      <div
        v-for="blog in blogs"
        :key="blog.id"
        class="p-4 flex items-center justify-between hover:bg-gray-50"
      >
        <div class="flex-1 min-w-0">
          <p class="font-medium text-gray-900 truncate">{{ blog.title }}</p>
          <p class="text-sm text-gray-500 truncate mt-1">
            {{ blog.category_name || 'Uncategorized' }}
          </p>
        </div>
        <div class="flex items-center gap-2 ml-4">
          <button
            @click="emit('edit', blog)"
            class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg"
          >
            <Pencil class="w-4 h-4" />
          </button>
          <button
            @click="emit('delete', blog.id)"
            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
          >
            <Trash2 class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <div v-if="pagination.pages > 1" class="p-4 border-t border-gray-200">
      <div class="flex items-center justify-center gap-2">
        <button
          v-for="page in pagination.pages"
          :key="page"
          @click="emit('pageChange', page)"
          class="px-3 py-1 text-sm rounded-lg"
          :class="[
            page === pagination.page
              ? 'bg-gray-900 text-white'
              : 'text-gray-600 hover:bg-gray-100',
          ]"
        >
          {{ page }}
        </button>
      </div>
    </div>
  </div>
</template>
