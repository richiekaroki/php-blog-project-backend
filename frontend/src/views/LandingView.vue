<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/composables/useApi'
import type { Blog, Category } from '@/types'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import CardContent from '@/components/ui/CardContent.vue'
import { RouterLink } from 'vue-router'
import { ArrowRight, Shield, Database, Code, Lock } from 'lucide-vue-next'

const blogs = ref<Blog[]>([])
const categories = ref<Category[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    const [blogsRes, catsRes] = await Promise.all([
      api.get('/api/index.php?action=blogs&limit=3'),
      api.get('/api/index.php?action=categories'),
    ])
    blogs.value = blogsRes.data.data?.slice(0, 3) || []
    categories.value = catsRes.data.data || []
  } catch {
    // API might not be running
  } finally {
    loading.value = false
  }
})

const features = [
  { icon: Shield, title: 'OWASP Security', desc: 'CSRF, XSS, rate limiting, session hardening' },
  { icon: Database, title: 'PostgreSQL', desc: 'Neon serverless database on Render' },
  { icon: Code, title: 'REST API', desc: 'Full CRUD with authentication' },
  { icon: Lock, title: 'Role-Based Access', desc: 'Admin, editor, viewer roles' },
]
</script>

<template>
  <div class="min-h-screen bg-background">
    <!-- Navbar -->
    <nav class="border-b">
      <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
            <span class="text-primary-foreground font-bold text-sm">B</span>
          </div>
          <span class="font-semibold text-lg">Blog Backend</span>
        </div>
        <div class="flex items-center gap-4">
          <RouterLink to="/login">
            <Button variant="ghost">Admin Login</Button>
          </RouterLink>
          <a href="https://github.com/richiekaroki/php-blog-project-backend" target="_blank">
            <Button variant="outline">GitHub</Button>
          </a>
        </div>
      </div>
    </nav>

    <!-- Hero -->
    <section class="py-20 px-6">
      <div class="max-w-4xl mx-auto text-center">
        <h1 class="text-5xl font-bold tracking-tight mb-6">
          Secure PHP Blog Backend
        </h1>
        <p class="text-xl text-muted-foreground mb-8 max-w-2xl mx-auto">
          A production-ready blog backend built with PHP 8.4, PostgreSQL, and Vue 3.
          Features role-based access control, REST API, and comprehensive security.
        </p>
        <div class="flex items-center justify-center gap-4">
          <RouterLink to="/login">
            <Button size="lg">
              Open Admin Panel
              <ArrowRight class="ml-2 h-4 w-4" />
            </Button>
          </RouterLink>
          <a href="https://php-blog-backend.onrender.com" target="_blank">
            <Button size="lg" variant="outline">Live Demo</Button>
          </a>
        </div>
      </div>
    </section>

    <!-- Features -->
    <section class="py-16 px-6 bg-muted/50">
      <div class="max-w-6xl mx-auto">
        <h2 class="text-2xl font-bold text-center mb-12">What's Under the Hood</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <Card v-for="feature in features" :key="feature.title">
            <CardContent class="pt-6">
              <component :is="feature.icon" class="h-10 w-10 mb-4 text-primary" />
              <h3 class="font-semibold mb-2">{{ feature.title }}</h3>
              <p class="text-sm text-muted-foreground">{{ feature.desc }}</p>
            </CardContent>
          </Card>
        </div>
      </div>
    </section>

    <!-- Tech Stack -->
    <section class="py-16 px-6">
      <div class="max-w-4xl mx-auto">
        <h2 class="text-2xl font-bold text-center mb-8">Tech Stack</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
          <div class="p-4 rounded-lg border">
            <p class="font-semibold">PHP 8.4</p>
            <p class="text-sm text-muted-foreground">Backend</p>
          </div>
          <div class="p-4 rounded-lg border">
            <p class="font-semibold">PostgreSQL</p>
            <p class="text-sm text-muted-foreground">Database</p>
          </div>
          <div class="p-4 rounded-lg border">
            <p class="font-semibold">Vue 3</p>
            <p class="text-sm text-muted-foreground">Frontend</p>
          </div>
          <div class="p-4 rounded-lg border">
            <p class="font-semibold">Docker</p>
            <p class="text-sm text-muted-foreground">Deployment</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="border-t py-8 px-6">
      <div class="max-w-6xl mx-auto flex items-center justify-between text-sm text-muted-foreground">
        <p>Built for portfolio demonstration</p>
        <div class="flex items-center gap-4">
          <a href="https://github.com/richiekaroki/php-blog-project-backend" class="hover:text-foreground">GitHub</a>
          <a href="https://php-blog-backend.onrender.com" class="hover:text-foreground">Live Demo</a>
        </div>
      </div>
    </footer>
  </div>
</template>
