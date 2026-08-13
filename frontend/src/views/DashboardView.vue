<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useBlogStore } from '@/stores/blog'
import Card from '@/components/ui/Card.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import CardContent from '@/components/ui/CardContent.vue'
import ActivityLog from '@/components/ActivityLog.vue'
import { FileText, Tag, TrendingUp, BarChart3 } from 'lucide-vue-next'

const blogStore = useBlogStore()

onMounted(async () => {
  await Promise.all([blogStore.fetchBlogs(1, 50), blogStore.fetchCategories()])
})

const categoryStats = computed(() => {
  const stats: Record<string, number> = {}
  blogStore.blogs.forEach((blog) => {
    const cat = blog.category_name || 'Uncategorized'
    stats[cat] = (stats[cat] || 0) + 1
  })
  return Object.entries(stats)
    .map(([name, count]) => ({ name, count }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 5)
})

const maxCount = computed(() => Math.max(...categoryStats.value.map((s) => s.count), 1))
</script>

<template>
  <div class="space-y-6">
    <!-- Stats cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
          <CardTitle class="text-sm font-medium">Avg. per Category</CardTitle>
          <BarChart3 class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">
            {{ categoryStats.length ? Math.round(blogStore.blogs.length / categoryStats.length) : 0 }}
          </div>
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Category distribution chart -->
      <Card>
        <CardHeader>
          <CardTitle>Blogs by Category</CardTitle>
        </CardHeader>
        <CardContent>
          <div v-if="categoryStats.length" class="space-y-4">
            <div v-for="stat in categoryStats" :key="stat.name" class="space-y-2">
              <div class="flex items-center justify-between text-sm">
                <span class="font-medium truncate">{{ stat.name }}</span>
                <span class="text-muted-foreground">{{ stat.count }}</span>
              </div>
              <div class="h-2 bg-muted rounded-full overflow-hidden">
                <div
                  class="h-full bg-primary rounded-full transition-all duration-500"
                  :style="{ width: `${(stat.count / maxCount) * 100}%` }"
                />
              </div>
            </div>
          </div>
          <p v-else class="text-muted-foreground text-center py-4">No data yet.</p>
        </CardContent>
      </Card>

      <!-- Recent blogs -->
      <Card>
        <CardHeader>
          <CardTitle>Recent Blogs</CardTitle>
        </CardHeader>
        <CardContent>
          <div v-if="blogStore.blogs.length" class="space-y-3">
            <div
              v-for="blog in blogStore.blogs.slice(0, 5)"
              :key="blog.id"
              class="flex items-center justify-between p-3 bg-muted rounded-lg"
            >
              <div class="min-w-0">
                <p class="font-medium truncate">{{ blog.title }}</p>
                <p class="text-sm text-muted-foreground">{{ blog.category_name || 'Uncategorized' }}</p>
              </div>
              <span class="text-xs text-muted-foreground ml-2">#{{ blog.id }}</span>
            </div>
          </div>
          <p v-else class="text-muted-foreground text-center py-4">No blogs yet.</p>
        </CardContent>
      </Card>
    </div>

    <!-- Activity Log -->
    <ActivityLog />
  </div>
</template>
