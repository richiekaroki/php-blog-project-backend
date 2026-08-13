<script setup lang="ts">
import type { Blog } from '@/types'
import Card from '@/components/ui/Card.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import CardContent from '@/components/ui/CardContent.vue'
import Button from '@/components/ui/Button.vue'
import { Pencil, Trash2 } from 'lucide-vue-next'

defineProps<{
  blogs: Blog[]
  loading: boolean
  pagination: {
    total: number
    page: number
    limit: number
    pages: number
  }
}>()

const emit = defineEmits<{
  edit: [blog: Blog]
  delete: [id: number]
  pageChange: [page: number]
}>()
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>All Blogs ({{ pagination.total }})</CardTitle>
    </CardHeader>
    <CardContent>
      <div v-if="loading" class="text-center text-muted-foreground py-8">Loading...</div>

      <div v-else-if="blogs.length === 0" class="text-center text-muted-foreground py-8">
        No blogs found. Create your first blog post!
      </div>

      <div v-else class="divide-y">
        <div
          v-for="blog in blogs"
          :key="blog.id"
          class="py-4 flex items-center justify-between first:pt-0 last:pb-0"
        >
          <div class="flex-1 min-w-0">
            <p class="font-medium truncate">{{ blog.title }}</p>
            <p class="text-sm text-muted-foreground truncate mt-1">
              {{ blog.category_name || 'Uncategorized' }}
            </p>
          </div>
          <div class="flex items-center gap-2 ml-4">
            <Button variant="ghost" size="icon" @click="emit('edit', blog)">
              <Pencil class="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="icon" @click="emit('delete', blog.id)">
              <Trash2 class="h-4 w-4 text-destructive" />
            </Button>
          </div>
        </div>
      </div>

      <div v-if="pagination.pages > 1" class="mt-4 pt-4 border-t">
        <div class="flex items-center justify-center gap-2">
          <Button
            v-for="page in pagination.pages"
            :key="page"
            :variant="page === pagination.page ? 'default' : 'outline'"
            size="sm"
            @click="emit('pageChange', page)"
          >
            {{ page }}
          </Button>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
