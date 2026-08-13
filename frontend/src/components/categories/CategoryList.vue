<script setup lang="ts">
import type { Category } from '@/types'
import { Pencil, Trash2 } from 'lucide-vue-next'

defineProps<{
  categories: Category[]
  loading: boolean
}>()

const emit = defineEmits<{
  edit: [category: Category]
  delete: [id: number]
}>()
</script>

<template>
  <div class="bg-white rounded-xl border border-gray-200">
    <div v-if="loading" class="p-8 text-center text-gray-500">Loading...</div>

    <div v-else-if="categories.length === 0" class="p-8 text-center text-gray-500">
      No categories yet. Create your first category!
    </div>

    <div v-else class="divide-y divide-gray-200">
      <div
        v-for="category in categories"
        :key="category.id"
        class="p-4 flex items-center justify-between hover:bg-gray-50"
      >
        <div class="flex-1 min-w-0">
          <p class="font-medium text-gray-900">{{ category.name }}</p>
          <p v-if="category.description" class="text-sm text-gray-500 truncate mt-1">
            {{ category.description }}
          </p>
        </div>
        <div class="flex items-center gap-2 ml-4">
          <button
            @click="emit('edit', category)"
            class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg"
          >
            <Pencil class="w-4 h-4" />
          </button>
          <button
            @click="emit('delete', category.id)"
            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
          >
            <Trash2 class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
