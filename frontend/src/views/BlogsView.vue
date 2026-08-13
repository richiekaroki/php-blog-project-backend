<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useBlogStore } from '@/stores/blog'
import { useToast } from '@/composables/useToast'
import type { Blog } from '@/types'
import BlogList from '@/components/blogs/BlogList.vue'
import BlogForm from '@/components/blogs/BlogForm.vue'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import CardContent from '@/components/ui/CardContent.vue'
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
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold">Blogs</h2>
      <Button @click="handleCreate">
        <Plus class="mr-2 h-4 w-4" />
        New Blog
      </Button>
    </div>

    <Card v-if="showForm">
      <CardHeader class="flex flex-row items-center justify-between">
        <CardTitle>
          {{ editingBlog ? 'Edit Blog' : 'Create Blog' }}
        </CardTitle>
        <Button variant="ghost" size="icon" @click="handleCancel">
          <X class="h-4 w-4" />
        </Button>
      </CardHeader>
      <CardContent>
        <BlogForm
          :blog="editingBlog"
          :categories="blogStore.categories"
          :loading="blogStore.loading"
          @submit="handleSubmit"
          @cancel="handleCancel"
        />
      </CardContent>
    </Card>

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
