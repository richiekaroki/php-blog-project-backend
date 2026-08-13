<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useBlogStore } from '@/stores/blog'
import { useToast } from '@/composables/useToast'
import type { Category } from '@/types'
import CategoryList from '@/components/categories/CategoryList.vue'
import CategoryForm from '@/components/categories/CategoryForm.vue'
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
    if (result) toast.success('Category updated successfully')
  } else {
    const result = await blogStore.createCategory(data)
    if (result) toast.success('Category created successfully')
  }
  showForm.value = false
  editingCategory.value = null
}

async function handleDelete(id: number) {
  if (confirm('Are you sure you want to delete this category?')) {
    const success = await blogStore.deleteCategory(id)
    if (success) toast.success('Category deleted successfully')
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">Categories</h2>
      <button
        @click="handleCreate"
        class="flex items-center gap-2 px-4 py-2 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800"
      >
        <Plus class="w-4 h-4" />
        New Category
      </button>
    </div>

    <div v-if="showForm" class="bg-white rounded-xl border border-gray-200 p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">
          {{ editingCategory ? 'Edit Category' : 'Create Category' }}
        </h3>
        <button @click="handleCancel" class="p-1 text-gray-400 hover:text-gray-600">
          <X class="w-5 h-5" />
        </button>
      </div>
      <CategoryForm
        :category="editingCategory"
        :loading="blogStore.loading"
        @submit="handleSubmit"
        @cancel="handleCancel"
      />
    </div>

    <CategoryList
      :categories="blogStore.categories"
      :loading="blogStore.loading"
      @edit="handleEdit"
      @delete="handleDelete"
    />
  </div>
</template>
