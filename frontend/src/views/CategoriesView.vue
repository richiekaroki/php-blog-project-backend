<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useBlogStore } from '@/stores/blog'
import { useToast } from '@/composables/useToast'
import type { Category } from '@/types'
import CategoryList from '@/components/categories/CategoryList.vue'
import CategoryForm from '@/components/categories/CategoryForm.vue'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import CardContent from '@/components/ui/CardContent.vue'
import { Plus, X } from 'lucide-vue-next'

const blogStore = useBlogStore()
const toast = useToast()
const showForm = ref(false)
const editingCategory = ref<Category | null>(null)

onMounted(() => {
  blogStore.fetchCategories()
})

function handleEdit(category: Category) {
  editingCategory.value = category
  showForm.value = true
}

function handleCreate() {
  editingCategory.value = null
  showForm.value = true
}

function handleCancel() {
  showForm.value = false
  editingCategory.value = null
}

async function handleSubmit(data: { name: string; description: string }) {
  if (editingCategory.value) {
    const result = await blogStore.updateCategory(editingCategory.value.id, data)
    if (result) {
      toast.success('Category updated successfully')
    } else {
      toast.error(blogStore.error || 'Failed to update category')
    }
  } else {
    const result = await blogStore.createCategory(data)
    if (result) {
      toast.success('Category created successfully')
    } else {
      toast.error(blogStore.error || 'Failed to create category')
    }
  }
  showForm.value = false
  editingCategory.value = null
}

async function handleDelete(id: number) {
  if (confirm('Are you sure you want to delete this category?')) {
    const success = await blogStore.deleteCategory(id)
    if (success) {
      toast.success('Category deleted successfully')
    } else {
      toast.error(blogStore.error || 'Failed to delete category')
    }
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="font-display text-2xl font-bold text-dark-olive">Categories</h2>
        <p class="text-sm text-muted-foreground mt-1">Organize your stories by topic</p>
      </div>
      <Button @click="handleCreate" class="bg-forest-green hover:bg-forest-green/90 text-white">
        <Plus class="mr-2 h-4 w-4" />
        New Category
      </Button>
    </div>

    <Card v-if="showForm" class="border-0 shadow-lg">
      <CardHeader class="flex flex-row items-center justify-between border-b border-border">
        <CardTitle class="font-display text-lg">
          {{ editingCategory ? 'Edit Category' : 'Create Category' }}
        </CardTitle>
        <Button variant="ghost" size="icon" @click="handleCancel" class="text-muted-foreground hover:text-foreground">
          <X class="h-4 w-4" />
        </Button>
      </CardHeader>
      <CardContent class="pt-6">
        <CategoryForm
          :category="editingCategory"
          :loading="blogStore.loading"
          @submit="handleSubmit"
          @cancel="handleCancel"
        />
      </CardContent>
    </Card>

    <CategoryList
      :categories="blogStore.categories"
      :loading="blogStore.loading"
      @edit="handleEdit"
      @delete="handleDelete"
    />
  </div>
</template>
