<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useBlogStore } from '@/stores/blog'
import { useToast } from '@/composables/useToast'
import type { Blog } from '@/types'
import BlogList from '@/components/blogs/BlogList.vue'
import BlogForm from '@/components/blogs/BlogForm.vue'
import { Plus, X } from 'lucide-vue-next'

const blogStore = useBlogStore()
const toast = useToast()
const showForm = ref(false)
const editingBlog = ref<Blog | null>(null)

onMounted(() => {
  blogStore.fetchBlogs()
  blogStore.fetchCategories()
})

function handleEdit(blog: Blog) {
  editingBlog.value = blog
  showForm.value = true
}

function handleCreate() {
  editingBlog.value = null
  showForm.value = true
}

function handleCancel() {
  showForm.value = false
  editingBlog.value = null
}

async function handleSubmit(data: FormData) {
  if (editingBlog.value) {
    const result = await blogStore.updateBlog(editingBlog.value.id, data)
    if (result) toast.success('Blog updated successfully')
  } else {
    const result = await blogStore.createBlog(data)
    if (result) toast.success('Blog created successfully')
  }
  showForm.value = false
  editingBlog.value = null
}

async function handleDelete(id: number) {
  if (confirm('Are you sure you want to delete this blog?')) {
    const success = await blogStore.deleteBlog(id)
    if (success) toast.success('Blog deleted successfully')
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">Blogs</h2>
      <button
        @click="handleCreate"
        class="flex items-center gap-2 px-4 py-2 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800"
      >
        <Plus class="w-4 h-4" />
        New Blog
      </button>
    </div>

    <div v-if="showForm" class="bg-white rounded-xl border border-gray-200 p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">
          {{ editingBlog ? 'Edit Blog' : 'Create Blog' }}
        </h3>
        <button @click="handleCancel" class="p-1 text-gray-400 hover:text-gray-600">
          <X class="w-5 h-5" />
        </button>
      </div>
      <BlogForm
        :blog="editingBlog"
        :categories="blogStore.categories"
        :loading="blogStore.loading"
        @submit="handleSubmit"
        @cancel="handleCancel"
      />
    </div>

    <BlogList
      :blogs="blogStore.blogs"
      :loading="blogStore.loading"
      :pagination="blogStore.pagination"
      @edit="handleEdit"
      @delete="handleDelete"
      @page-change="(page) => blogStore.fetchBlogs(page)"
    />
  </div>
</template>
