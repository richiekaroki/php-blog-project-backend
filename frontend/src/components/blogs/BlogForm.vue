<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Blog, Category } from '@/types'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Select from '@/components/ui/Select.vue'
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
    <div class="space-y-2">
      <label class="text-sm font-medium leading-none">Title</label>
      <Input
        v-model="form.title"
        type="text"
        required
        placeholder="Blog title"
      />
    </div>

    <div class="space-y-2">
      <label class="text-sm font-medium leading-none">Content</label>
      <Textarea
        v-model="form.content"
        required
        rows="6"
        placeholder="Blog content"
      />
    </div>

    <div class="space-y-2">
      <label class="text-sm font-medium leading-none">Category</label>
      <Select v-model="form.category_id">
        <option :value="null">Select category</option>
        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
          {{ cat.name }}
        </option>
      </Select>
    </div>

    <div class="space-y-2">
      <label class="text-sm font-medium leading-none">Featured Image</label>
      <Input
        type="file"
        accept="image/*"
        @change="(e) => (imageFile = (e.target as HTMLInputElement).files?.[0] || null)"
      />
      <p class="text-xs text-muted-foreground">Max 5MB. JPEG, PNG, GIF, WebP.</p>
    </div>

    <div class="flex gap-3 pt-2">
      <Button type="submit" :disabled="loading">
        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
        {{ loading ? 'Saving...' : blog ? 'Update Blog' : 'Create Blog' }}
      </Button>
      <Button type="button" variant="outline" @click="emit('cancel')">
        Cancel
      </Button>
    </div>
  </form>
</template>
