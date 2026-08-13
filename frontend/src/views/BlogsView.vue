<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useBlogStore } from '@/stores/blog'
import { useToast } from '@/composables/useToast'
import type { Blog } from '@/types'
import BlogList from '@/components/blogs/BlogList.vue'
import BlogForm from '@/components/blogs/BlogForm.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Card from '@/components/ui/Card.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import CardContent from '@/components/ui/CardContent.vue'
import { Plus, X, Search, Download, FileJson, FileSpreadsheet } from 'lucide-vue-next'

const blogStore = useBlogStore()
const toast = useToast()
const showForm = ref(false)
const editingBlog = ref<Blog | null>(null)
const searchQuery = ref('')
const showExportMenu = ref(false)

const filteredBlogs = computed(() => {
  if (!searchQuery.value) return blogStore.blogs
  const query = searchQuery.value.toLowerCase()
  return blogStore.blogs.filter(
    (blog) =>
      blog.title.toLowerCase().includes(query) ||
      blog.category_name?.toLowerCase().includes(query)
  )
})

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
    if (result) {
      toast.success('Blog updated successfully')
    } else {
      toast.error(blogStore.error || 'Failed to update blog')
    }
  } else {
    const result = await blogStore.createBlog(data)
    if (result) {
      toast.success('Blog created successfully')
    } else {
      toast.error(blogStore.error || 'Failed to create blog')
    }
  }
  showForm.value = false
  editingBlog.value = null
}

async function handleDelete(id: number) {
  if (confirm('Are you sure you want to delete this blog?')) {
    const success = await blogStore.deleteBlog(id)
    if (success) {
      toast.success('Blog deleted successfully')
    } else {
      toast.error(blogStore.error || 'Failed to delete blog')
    }
  }
}

function exportJSON() {
  const data = filteredBlogs.value.map((blog) => ({
    id: blog.id,
    title: blog.title,
    content: blog.content,
    category: blog.category_name,
    image: blog.image,
    created_at: blog.created_at,
  }))
  
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
  downloadBlob(blob, 'blogs-export.json')
  toast.success('Exported as JSON')
  showExportMenu.value = false
}

function exportCSV() {
  const headers = ['ID', 'Title', 'Content', 'Category', 'Image', 'Created At']
  const rows = filteredBlogs.value.map((blog) => [
    blog.id,
    `"${(blog.title || '').replace(/"/g, '""')}"`,
    `"${(blog.content || '').replace(/"/g, '""')}"`,
    `"${(blog.category_name || '').replace(/"/g, '""')}"`,
    blog.image || '',
    blog.created_at || '',
  ])
  
  const csv = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  downloadBlob(blob, 'blogs-export.csv')
  toast.success('Exported as CSV')
  showExportMenu.value = false
}

function downloadBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="font-display text-2xl font-bold text-dark-olive">Blog Posts</h2>
        <p class="text-sm text-muted-foreground mt-1">Create and manage your stories</p>
      </div>
      <div class="flex items-center gap-3">
        <div class="relative">
          <Button 
            variant="outline" 
            @click="showExportMenu = !showExportMenu"
            class="border-border text-muted-foreground hover:text-foreground"
          >
            <Download class="mr-2 h-4 w-4" />
            Export
          </Button>
          <div
            v-if="showExportMenu"
            class="absolute right-0 mt-2 w-48 bg-card border border-border rounded-xl shadow-lg z-10 overflow-hidden"
          >
            <button
              @click="exportJSON"
              class="flex items-center gap-3 w-full px-4 py-3 text-sm text-left hover:bg-muted transition-colors"
            >
              <FileJson class="h-4 w-4 text-forest-green" />
              <span class="text-foreground">Export as JSON</span>
            </button>
            <button
              @click="exportCSV"
              class="flex items-center gap-3 w-full px-4 py-3 text-sm text-left hover:bg-muted transition-colors border-t border-border"
            >
              <FileSpreadsheet class="h-4 w-4 text-warm-orange" />
              <span class="text-foreground">Export as CSV</span>
            </button>
          </div>
        </div>
        <Button @click="handleCreate" class="bg-forest-green hover:bg-forest-green/90 text-white">
          <Plus class="mr-2 h-4 w-4" />
          New Post
        </Button>
      </div>
    </div>

    <Card v-if="showForm" class="border-0 shadow-lg">
      <CardHeader class="flex flex-row items-center justify-between border-b border-border">
        <CardTitle class="font-display text-lg">
          {{ editingBlog ? 'Edit Post' : 'Create New Post' }}
        </CardTitle>
        <Button variant="ghost" size="icon" @click="handleCancel" class="text-muted-foreground hover:text-foreground">
          <X class="h-4 w-4" />
        </Button>
      </CardHeader>
      <CardContent class="pt-6">
        <BlogForm
          :blog="editingBlog"
          :categories="blogStore.categories"
          :loading="blogStore.loading"
          @submit="handleSubmit"
          @cancel="handleCancel"
        />
      </CardContent>
    </Card>

    <!-- Search bar -->
    <div class="relative">
      <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
      <Input
        v-model="searchQuery"
        placeholder="Search by title or category..."
        class="pl-11 bg-card border-border focus:border-forest-green focus:ring-forest-green h-11"
      />
    </div>

    <BlogList
      :blogs="filteredBlogs"
      :loading="blogStore.loading"
      :pagination="blogStore.pagination"
      @edit="handleEdit"
      @delete="handleDelete"
      @page-change="(page) => blogStore.fetchBlogs(page)"
    />
  </div>
</template>
