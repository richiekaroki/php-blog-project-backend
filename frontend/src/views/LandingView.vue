<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/api/client'
import { useDarkMode } from '@/composables/useDarkMode'
import type { Blog, Category } from '@/types'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import GetStartedModal from '@/features/landing/GetStartedModal.vue'
import { RouterLink } from 'vue-router'
import { ArrowRight, BookOpen, PenLine, Sparkles, Sun, Moon, Heart, EyeOff, Palette } from 'lucide-vue-next'

const blogs = ref<Blog[]>([])
const categories = ref<Category[]>([])
const loading = ref(true)
const showGetStarted = ref(false)
const { isDark, toggle } = useDarkMode()

const liveBlogBase = 'https://php-blog-backend.onrender.com'

onMounted(async () => {
  try {
    const [blogsRes, catsRes] = await Promise.all([
      api.get('/api/index.php?action=blogs&limit=6'),
      api.get('/api/index.php?action=categories'),
    ])
    blogs.value = blogsRes.data.data?.slice(0, 6) || []
    categories.value = catsRes.data.data || []
  } catch {
    // API might not be running
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="min-h-screen bg-background">
    <!-- Navbar -->
    <nav class="border-b border-border/50 bg-background/80 backdrop-blur-sm sticky top-0 z-50">
      <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 bg-primary rounded-full flex items-center justify-center">
            <span class="text-primary-foreground font-display font-bold text-lg">W</span>
          </div>
          <span class="font-display font-semibold text-xl text-foreground">WAM Blog</span>
        </div>
        <div class="flex items-center gap-3">
          <button
            @click="toggle"
            class="p-2 text-muted-foreground hover:text-foreground hover:bg-muted rounded-lg transition-colors"
            :title="isDark ? 'Light mode' : 'Dark mode'"
            aria-label="Toggle theme"
          >
            <Sun v-if="isDark" class="h-5 w-5" />
            <Moon v-else class="h-5 w-5" />
          </button>
          <RouterLink to="/login">
            <Button variant="ghost" class="text-muted-foreground hover:text-foreground">Sign In</Button>
          </RouterLink>
          <Button
            @click="showGetStarted = true"
            class="bg-forest-green hover:bg-forest-green/90 text-white"
          >
            Get Started
          </Button>
          <a :href="liveBlogBase" target="_blank" rel="noopener noreferrer">
            <Button variant="outline" class="border-forest-green/30 text-forest-green hover:bg-forest-green hover:text-white">Read Blog</Button>
          </a>
        </div>
      </div>
    </nav>

    <!-- Hero -->
    <section class="pt-20 pb-16 px-6 bg-gradient-to-b from-warm-cream/60 via-background to-background">
      <div class="max-w-4xl mx-auto text-center">
        <div class="inline-flex items-center gap-2 px-4 py-2 bg-forest-green/10 rounded-full text-forest-green text-sm font-medium mb-8">
          <Sparkles class="h-4 w-4" />
          Welcome to thoughtful reading
        </div>
        <h1 class="font-display text-4xl sm:text-5xl md:text-6xl font-bold tracking-tight mb-6 text-dark-olive leading-[1.15]">
          Stories worth your time
        </h1>
        <p class="text-lg sm:text-xl text-muted-foreground mb-10 max-w-2xl mx-auto leading-relaxed">
          A space for carefully crafted articles, ideas, and perspectives.
          No noise, no distractions — just meaningful content that inspires curiosity.
        </p>
        <div class="flex items-center justify-center gap-4 flex-wrap">
          <a href="#featured">
            <Button size="lg" class="bg-forest-green hover:bg-forest-green/90 text-white px-8">
              Start Reading
              <ArrowRight class="ml-2 h-4 w-4" />
            </Button>
          </a>
          <Button size="lg" variant="outline" @click="showGetStarted = true" class="border-forest-green/30 text-forest-green hover:bg-forest-green hover:text-white">
            <PenLine class="mr-2 h-4 w-4" />
            Get Started
          </Button>
        </div>
      </div>
    </section>

    <!-- Featured Posts -->
    <section id="featured" class="py-16 px-6">
      <div class="max-w-6xl mx-auto">
        <div class="flex items-end justify-between mb-10">
          <div>
            <h2 class="font-display text-3xl font-bold text-dark-olive mb-1">Featured Stories</h2>
            <p class="text-muted-foreground">Handpicked articles we think you'll enjoy</p>
          </div>
          <a :href="liveBlogBase" target="_blank" rel="noopener noreferrer" class="text-forest-green hover:text-forest-green/80 font-medium flex items-center gap-1">
            View all
            <ArrowRight class="h-4 w-4" />
          </a>
        </div>

        <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div v-for="i in 3" :key="i" class="animate-pulse">
            <div class="bg-muted rounded-2xl aspect-[16/10] mb-4"></div>
            <div class="bg-muted rounded h-4 w-3/4 mb-2"></div>
            <div class="bg-muted rounded h-4 w-1/2"></div>
          </div>
        </div>

        <div v-else-if="blogs.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <a
            v-for="blog in blogs.slice(0, 3)"
            :key="blog.id"
            :href="`${liveBlogBase}/post.php?id=${blog.id}`"
            target="_blank"
            rel="noopener noreferrer"
            class="group"
          >
            <Card class="h-full border-0 shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden bg-card rounded-2xl">
              <div class="aspect-[16/10] overflow-hidden bg-muted">
                <img
                  v-if="blog.image"
                  :src="blog.image"
                  :alt="blog.title"
                  class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                >
                <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-warm-cream to-muted">
                  <BookOpen class="h-12 w-12 text-forest-green/30" />
                </div>
              </div>
              <CardContent class="p-6">
                <div v-if="blog.category_name" class="inline-block px-3 py-1 bg-forest-green/10 text-forest-green text-xs font-medium rounded-full mb-3">
                  {{ blog.category_name }}
                </div>
                <h3 class="font-display text-xl font-semibold mb-2 text-dark-olive group-hover:text-forest-green transition-colors">
                  {{ blog.title }}
                </h3>
                <p class="text-muted-foreground text-sm leading-relaxed">
                  {{ blog.content?.substring(0, 120) }}…
                </p>
              </CardContent>
            </Card>
          </a>
        </div>

        <div v-else class="text-center py-14 bg-card border border-border/50 rounded-2xl">
          <BookOpen class="h-14 w-14 text-forest-green/25 mx-auto mb-4" />
          <h3 class="font-display text-xl font-semibold text-dark-olive mb-1">Fresh stories on the way</h3>
          <p class="text-muted-foreground">New articles are being written. Check back soon.</p>
        </div>
      </div>
    </section>

    <!-- About Section -->
    <section class="py-16 px-6 bg-warm-cream/30">
      <div class="max-w-4xl mx-auto text-center">
        <h2 class="font-display text-3xl font-bold text-dark-olive mb-6">A quiet corner for curious minds</h2>
        <p class="text-lg text-muted-foreground mb-8 max-w-2xl mx-auto leading-relaxed">
          In a world of endless scrolling and clickbait, we believe in the power of
          thoughtful writing. Each piece here is crafted with care, designed to inform,
          inspire, and spark meaningful conversation.
        </p>
        <div class="flex items-center justify-center gap-8 text-sm text-muted-foreground flex-wrap">
          <div class="flex items-center gap-2">
            <Heart class="h-4 w-4 text-forest-green" />
            <span>Thoughtful content</span>
          </div>
          <div class="flex items-center gap-2">
            <EyeOff class="h-4 w-4 text-warm-orange" />
            <span>No distractions</span>
          </div>
          <div class="flex items-center gap-2">
            <Palette class="h-4 w-4 text-dark-olive" />
            <span>Clean design</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Categories -->
    <section class="py-16 px-6">
      <div class="max-w-6xl mx-auto">
        <h2 class="font-display text-3xl font-bold text-dark-olive mb-8 text-center">Explore Topics</h2>
        <div v-if="categories.length" class="flex flex-wrap justify-center gap-3">
          <span
            v-for="cat in categories.slice(0, 8)"
            :key="cat.id"
            class="px-5 py-2.5 bg-card border border-border rounded-full text-sm font-medium text-muted-foreground hover:border-forest-green hover:text-forest-green hover:bg-forest-green/5 transition-colors cursor-pointer"
          >
            {{ cat.name }}
          </span>
        </div>
        <div v-else class="flex flex-wrap justify-center gap-3">
          <span class="px-5 py-2.5 bg-card border border-border/60 rounded-full text-sm font-medium text-muted-foreground">Technology</span>
          <span class="px-5 py-2.5 bg-card border border-border/60 rounded-full text-sm font-medium text-muted-foreground">Design</span>
          <span class="px-5 py-2.5 bg-card border border-border/60 rounded-full text-sm font-medium text-muted-foreground">Life</span>
          <span class="px-5 py-2.5 bg-card border border-border/60 rounded-full text-sm font-medium text-muted-foreground">Ideas</span>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-border/50 py-12 px-6 bg-warm-cream/20">
      <div class="max-w-6xl mx-auto">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
              <span class="text-primary-foreground font-display font-bold text-sm">W</span>
            </div>
            <span class="font-display font-semibold text-foreground">WAM Blog</span>
          </div>
          <p class="text-sm text-muted-foreground">
            Crafted with care for readers who appreciate quality content.
          </p>
          <div class="flex items-center gap-6 text-sm text-muted-foreground">
            <a href="https://github.com/richiekaroki/php-blog-project-backend" target="_blank" rel="noopener noreferrer" class="hover:text-forest-green transition-colors">GitHub</a>
            <a :href="liveBlogBase" target="_blank" rel="noopener noreferrer" class="hover:text-forest-green transition-colors">Live Demo</a>
          </div>
        </div>
      </div>
    </footer>

    <GetStartedModal v-if="showGetStarted" @close="showGetStarted = false" />
  </div>
</template>
