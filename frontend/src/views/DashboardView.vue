<script setup lang="ts">
import { onMounted } from 'vue'
import { useBlogStore } from '@/stores/blog'
import Card from '@/components/ui/Card.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import CardContent from '@/components/ui/CardContent.vue'
import { FileText, Tag, TrendingUp } from 'lucide-vue-next'

const blogStore = useBlogStore()

onMounted(async () => {
  await Promise.all([blogStore.fetchBlogs(1, 5), blogStore.fetchCategories()])
})
</script>

<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium">Total Blogs</CardTitle>
          <FileText class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ blogStore.pagination.total }}</div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium">Categories</CardTitle>
          <Tag class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ blogStore.categories.length }}</div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium">Status</CardTitle>
          <TrendingUp class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold text-green-600">Active</div>
        </CardContent>
      </Card>
    </div>

    <Card>
      <CardHeader>
        <CardTitle>Recent Blogs</CardTitle>
      </CardHeader>
      <CardContent>
        <div v-if="blogStore.blogs.length" class="space-y-3">
          <div
            v-for="blog in blogStore.blogs"
            :key="blog.id"
            class="flex items-center justify-between p-3 bg-muted rounded-lg"
          >
            <div>
              <p class="font-medium">{{ blog.title }}</p>
              <p class="text-sm text-muted-foreground">{{ blog.category_name || 'Uncategorized' }}</p>
            </div>
            <span class="text-xs text-muted-foreground">#{{ blog.id }}</span>
          </div>
        </div>
        <p v-else class="text-muted-foreground text-center py-4">No blogs yet.</p>
      </CardContent>
    </Card>
  </div>
</template>
