<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Blog, Category } from '@/types'
import { Loader2 } from 'lucide-vue-next'

const props = defineProps<{
  blog?: Blog | null
  categories: Category[]
  loading: boolean
}>()

const emit = defineEmits<{
  submit: [data: FormData]
  cancel: []
}>()

const form = ref({
  title: '',
  content: '',
  category_id: null as number | null,
})

const imageFile = ref<File | null>(null)

watch(
  () => props.blog,
  (blog) => {
    if (blog) {
      form.value = {
        title: blog.title,
        content: blog.content,
        category_id: blog.category_id,
      }
    }
  },
  { immediate: true }
)

function handleSubmit() {
  const formData = new FormData()
  formData.append('title', form.value.title)
  formData.append('content', form.value.content)
  if (form.value.category_id) {
    formData.append('category_id', String(form.value.category_id))
  }
  if (imageFile.value) {
    formData.append('image', imageFile.value)
  }
  emit('submit', formData)
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
      <input
        v-model="form.title"
        type="text"
        required
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
        placeholder="Blog title"
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Content</label>
      <textarea
        v-model="form.content"
        required
        rows="6"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
        placeholder="Blog content"
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
      <select
        v-model="form.category_id"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
      >
        <option :value="null">Select category</option>
        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
          {{ cat.name }}
        </option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Featured Image</label>
      <input
        type="file"
        accept="image/*"
        @change="(e) => (imageFile = (e.target as HTMLInputElement).files?.[0] || null)"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
      />
      <p class="text-xs text-gray-500 mt-1">Max 5MB. JPEG, PNG, GIF, WebP.</p>
    </div>

    <div class="flex gap-3 pt-2">
      <button
        type="submit"
        :disabled="loading"
        class="px-4 py-2 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800 disabled:opacity-50 flex items-center gap-2"
      >
        <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
        {{ loading ? 'Saving...' : blog ? 'Update Blog' : 'Create Blog' }}
      </button>
      <button
        type="button"
        @click="emit('cancel')"
        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50"
      >
        Cancel
      </button>
    </div>
  </form>
</template>
