<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Category } from '@/types'
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
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
      <input
        v-model="form.name"
        type="text"
        required
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
        placeholder="Category name"
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
      <textarea
        v-model="form.description"
        rows="3"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
        placeholder="Optional description"
      />
    </div>

    <div class="flex gap-3 pt-2">
      <button
        type="submit"
        :disabled="loading"
        class="px-4 py-2 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800 disabled:opacity-50 flex items-center gap-2"
      >
        <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
        {{ loading ? 'Saving...' : category ? 'Update Category' : 'Create Category' }}
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
