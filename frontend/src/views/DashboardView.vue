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
    <!-- Welcome header -->
    <div class="bg-gradient-to-r from-forest-green to-forest-green/80 rounded-2xl p-6 text-white">
      <h1 class="font-display text-2xl font-bold mb-1">Content Dashboard</h1>
      <p class="text-white/80">Manage your stories and track their performance</p>
    </div>

    <!-- Stats cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <Card class="border-0 shadow-sm hover:shadow-md transition-shadow">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Total Stories</CardTitle>
          <div class="p-2 bg-forest-green/10 rounded-lg">
            <FileText class="h-4 w-4 text-forest-green" />
          </div>
        </CardHeader>
        <CardContent>
          <div class="text-3xl font-bold text-dark-olive">{{ blogStore.pagination.total }}</div>
        </CardContent>
      </Card>

      <Card class="border-0 shadow-sm hover:shadow-md transition-shadow">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Categories</CardTitle>
          <div class="p-2 bg-warm-orange/10 rounded-lg">
            <Tag class="h-4 w-4 text-warm-orange" />
          </div>
        </CardHeader>
        <CardContent>
          <div class="text-3xl font-bold text-dark-olive">{{ blogStore.categories.length }}</div>
        </CardContent>
      </Card>

      <Card class="border-0 shadow-sm hover:shadow-md transition-shadow">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Avg. per Category</CardTitle>
          <div class="p-2 bg-forest-green/10 rounded-lg">
            <BarChart3 class="h-4 w-4 text-forest-green" />
          </div>
        </CardHeader>
        <CardContent>
          <div class="text-3xl font-bold text-dark-olive">
            {{ categoryStats.length ? Math.round(blogStore.blogs.length / categoryStats.length) : 0 }}
          </div>
        </CardContent>
      </Card>

      <Card class="border-0 shadow-sm hover:shadow-md transition-shadow">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Status</CardTitle>
          <div class="p-2 bg-forest-green/10 rounded-lg">
            <TrendingUp class="h-4 w-4 text-forest-green" />
          </div>
        </CardHeader>
        <CardContent>
          <div class="text-3xl font-bold text-forest-green">Active</div>
        </CardContent>
      </Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Category distribution chart -->
      <Card class="border-0 shadow-sm">
        <CardHeader>
          <CardTitle class="font-display text-lg">Stories by Category</CardTitle>
        </CardHeader>
        <CardContent>
          <div v-if="categoryStats.length" class="space-y-4">
            <div v-for="stat in categoryStats" :key="stat.name" class="space-y-2">
              <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-foreground truncate">{{ stat.name }}</span>
                <span class="text-muted-foreground">{{ stat.count }}</span>
              </div>
              <div class="h-2.5 bg-muted rounded-full overflow-hidden">
                <div
                  class="h-full bg-gradient-to-r from-forest-green to-forest-green/70 rounded-full transition-all duration-500"
                  :style="{ width: `${(stat.count / maxCount) * 100}%` }"
                />
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8">
            <BarChart3 class="h-12 w-12 text-muted-foreground/30 mx-auto mb-3" />
            <p class="text-muted-foreground">No data yet.</p>
          </div>
        </CardContent>
      </Card>

      <!-- Recent blogs -->
      <Card class="border-0 shadow-sm">
        <CardHeader>
          <CardTitle class="font-display text-lg">Recent Stories</CardTitle>
        </CardHeader>
        <CardContent>
          <div v-if="blogStore.blogs.length" class="space-y-3">
            <div
              v-for="blog in blogStore.blogs.slice(0, 5)"
              :key="blog.id"
              class="flex items-center justify-between p-3 bg-muted/50 rounded-xl hover:bg-muted transition-colors"
            >
              <div class="min-w-0">
                <p class="font-medium text-foreground truncate">{{ blog.title }}</p>
                <p class="text-sm text-muted-foreground">{{ blog.category_name || 'Uncategorized' }}</p>
              </div>
              <span class="text-xs text-muted-foreground ml-2 font-mono">#{{ blog.id }}</span>
            </div>
          </div>
          <div v-else class="text-center py-8">
            <FileText class="h-12 w-12 text-muted-foreground/30 mx-auto mb-3" />
            <p class="text-muted-foreground">No stories yet.</p>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Activity Log -->
    <ActivityLog />
  </div>
</template>
