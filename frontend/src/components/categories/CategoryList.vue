<script setup lang="ts">
import type { Category } from '@/types'
import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import Button from '@/components/ui/Button.vue'
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
  <Card>
    <CardContent>
      <div v-if="loading" class="text-center text-muted-foreground py-8">Loading...</div>

      <div v-else-if="categories.length === 0" class="text-center text-muted-foreground py-8">
        No categories yet. Create your first category!
      </div>

      <div v-else class="divide-y">
        <div
          v-for="category in categories"
          :key="category.id"
          class="py-4 flex items-center justify-between first:pt-0 last:pb-0"
        >
          <div class="flex-1 min-w-0">
            <p class="font-medium">{{ category.name }}</p>
            <p v-if="category.description" class="text-sm text-muted-foreground truncate mt-1">
              {{ category.description }}
            </p>
          </div>
          <div class="flex items-center gap-2 ml-4">
            <Button variant="ghost" size="icon" @click="emit('edit', category)">
              <Pencil class="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="icon" @click="emit('delete', category.id)">
              <Trash2 class="h-4 w-4 text-destructive" />
            </Button>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
