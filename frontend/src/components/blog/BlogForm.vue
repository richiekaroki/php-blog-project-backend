<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Blog, Category } from '@/types'
import { api } from '@/api/client'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Select from '@/components/ui/Select.vue'
import MarkdownEditor from '@/components/ui/MarkdownEditor.vue'
import { Loader2, Upload } from 'lucide-vue-next'

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
const imagePreview = ref<string | null>(null)
const uploading = ref(false)

watch(
  () => props.blog,
  (blog) => {
    if (blog) {
      form.value = {
        title: blog.title,
        content: blog.content,
        category_id: blog.category_id,
      }
      if (blog.image) {
        imagePreview.value = blog.image
      }
    }
  },
  { immediate: true }
)

function handleFileChange(e: Event) {
  const target = e.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) {
    imageFile.value = file
    const reader = new FileReader()
    reader.onload = (e) => {
      imagePreview.value = e.target?.result as string
    }
    reader.readAsDataURL(file)
  }
}

async function uploadImage(): Promise<string | null> {
  if (!imageFile.value) return null
  
  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('file', imageFile.value)
    
    const response = await api.post('/api/index.php?action=upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    
    return response.data.url
  } catch (error) {
    console.error('Upload failed:', error)
    return null
  } finally {
    uploading.value = false
  }
}

async function handleSubmit() {
  let imageUrl = imageFile.value ? await uploadImage() : null
  
  // If editing and no new image, keep the old one
  if (!imageUrl && props.blog?.image) {
    imageUrl = props.blog.image
  }
  
  const formData = new FormData()
  formData.append('title', form.value.title)
  formData.append('content', form.value.content)
  if (form.value.category_id) {
    formData.append('category_id', String(form.value.category_id))
  }
  if (imageUrl) {
    formData.append('image', imageUrl)
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
      <label class="text-sm font-medium leading-none">Content (supports Markdown)</label>
      <MarkdownEditor v-model="form.content" />
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
      <div class="flex items-center gap-4">
        <label class="flex-1">
          <Input
            type="file"
            accept="image/*"
            @change="handleFileChange"
            class="cursor-pointer"
          />
        </label>
      </div>
      <p class="text-xs text-muted-foreground">Max 5MB. JPEG, PNG, GIF, WebP.</p>
      
      <div v-if="imagePreview" class="mt-2">
        <img :src="imagePreview" alt="Preview" class="w-32 h-24 object-cover rounded-lg border" />
      </div>
    </div>

    <div class="flex gap-3 pt-2">
      <Button type="submit" :disabled="loading || uploading">
        <Loader2 v-if="loading || uploading" class="mr-2 h-4 w-4 animate-spin" />
        <Upload v-else class="mr-2 h-4 w-4" />
        {{ loading || uploading ? 'Saving...' : blog ? 'Update Blog' : 'Create Blog' }}
      </Button>
      <Button type="button" variant="outline" @click="emit('cancel')">
        Cancel
      </Button>
    </div>
  </form>
</template>
