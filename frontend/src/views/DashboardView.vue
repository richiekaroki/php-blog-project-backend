<script setup lang="ts">
import { onMounted } from 'vue'
import { useBlogStore } from '@/stores/blog'
import { FileText, Tag, TrendingUp } from 'lucide-vue-next'

const blogStore = useBlogStore()

onMounted(async () => {
  await Promise.all([blogStore.fetchBlogs(1, 5), blogStore.fetchCategories()])
})
</script>

<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center gap-4">
          <div class="p-3 bg-blue-50 rounded-lg">
            <FileText class="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <p class="text-sm text-gray-500">Total Blogs</p>
            <p class="text-2xl font-bold text-gray-900">{{ blogStore.pagination.total }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center gap-4">
          <div class="p-3 bg-green-50 rounded-lg">
            <Tag class="w-6 h-6 text-green-600" />
          </div>
          <div>
            <p class="text-sm text-gray-500">Categories</p>
            <p class="text-2xl font-bold text-gray-900">{{ blogStore.categories.length }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center gap-4">
          <div class="p-3 bg-purple-50 rounded-lg">
            <TrendingUp class="w-6 h-6 text-purple-600" />
          </div>
          <div>
            <p class="text-sm text-gray-500">Status</p>
            <p class="text-2xl font-bold text-green-600">Active</p>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Blogs</h3>
      <div v-if="blogStore.blogs.length" class="space-y-3">
        <div
          v-for="blog in blogStore.blogs"
          :key="blog.id"
          class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
        >
          <div>
            <p class="font-medium text-gray-900">{{ blog.title }}</p>
            <p class="text-sm text-gray-500">{{ blog.category_name || 'Uncategorized' }}</p>
          </div>
          <span class="text-xs text-gray-400">#{{ blog.id }}</span>
        </div>
      </div>
      <p v-else class="text-gray-500 text-center py-4">No blogs yet.</p>
    </div>
  </div>
</template>
