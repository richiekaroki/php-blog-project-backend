<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Category } from '@/types'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { Loader2 } from 'lucide-vue-next'

const props = defineProps<{
  category?: Category | null
  loading: boolean
}>()

const emit = defineEmits<{
  submit: [data: { name: string; description: string }]
  cancel: []
}>()

const form = ref({
  name: '',
  description: '',
})

watch(
  () => props.category,
  (cat) => {
    if (cat) {
      form.value = {
        name: cat.name,
        description: cat.description || '',
      }
    }
  },
  { immediate: true }
)
</script>

<template>
  <form @submit.prevent="emit('submit', form)" class="space-y-4">
    <div class="space-y-2">
      <label class="text-sm font-medium leading-none">Name</label>
      <Input
        v-model="form.name"
        type="text"
        required
        placeholder="Category name"
      />
    </div>

    <div class="space-y-2">
      <label class="text-sm font-medium leading-none">Description</label>
      <Textarea
        v-model="form.description"
        rows="3"
        placeholder="Optional description"
      />
    </div>

    <div class="flex gap-3 pt-2">
      <Button type="submit" :disabled="loading">
        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
        {{ loading ? 'Saving...' : category ? 'Update Category' : 'Create Category' }}
      </Button>
      <Button type="button" variant="outline" @click="emit('cancel')">
        Cancel
      </Button>
    </div>
  </form>
</template>
