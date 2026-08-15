<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/api/client'
import { useDarkMode } from '@/composables/useDarkMode'
import type { Blog, Category } from '@/types'
import Button from '@/components/ui/Button.vue'
import GetStartedModal from '@/features/landing/GetStartedModal.vue'
import { ArrowRight, BookOpen, Sun, Moon, Feather, CalendarDays, Clock } from 'lucide-vue-next'

const blogs = ref<Blog[]>([])
const categories = ref<Category[]>([])
const loading = ref(true)
const showGetStarted = ref(false)
const { isDark, toggle } = useDarkMode()

const liveBlogBase = import.meta.env.VITE_BLOG_BASE || (import.meta.env.DEV ? 'http://php-blog-backend-project.test' : '')

function formatDate(iso?: string): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
}

function readingTime(blog: Blog): string {
  const words = blog.word_count ?? (blog.content ? blog.content.trim().split(/\s+/).filter(Boolean).length : 0)
  return `${Math.max(1, Math.round(words / 200))} min read`
}

function excerpt(content?: string, len = 160): string {
  const text = (content ?? '').replace(/\s+/g, ' ').trim()
  return text.length > len ? text.slice(0, len).trimEnd() + '…' : text
}

function scrollToTop() {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' })
}

const featured = () => blogs.value[0]
const rest = () => blogs.value.slice(1)

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
    <nav class="border-b border-border/60 bg-background/85 backdrop-blur-md sticky top-0 z-50">
      <div class="max-w-6xl mx-auto px-5 sm:px-6 h-16 flex items-center justify-between">
        <a href="/" class="flex items-center gap-2.5">
          <img src="/favicon.svg" alt="WAM logo" width="64" height="64" class="h-9 w-9 shrink-0" />
          <span class="font-display font-semibold text-2xl tracking-tight text-dark-olive">WAM</span>
          <span class="eyebrow text-forest-green hidden sm:inline">Blog</span>
        </a>
        <div class="flex items-center gap-2 sm:gap-3">
          <button
            @click="toggle"
            class="p-2 text-muted-foreground hover:text-forest-green hover:bg-muted/70 rounded-md transition-colors"
            :title="isDark ? 'Light mode' : 'Dark mode'"
            aria-label="Toggle theme"
          >
            <Sun v-if="isDark" class="h-4 w-4" />
            <Moon v-else class="h-4 w-4" />
          </button>
          <button
            @click="showGetStarted = true"
            class="hidden sm:inline-flex px-3 py-2 text-sm font-medium text-muted-foreground hover:text-forest-green transition-colors cursor-pointer"
          >
            Join the journal
          </button>
          <a href="#latest">
            <Button class="h-9 rounded-md px-4 bg-forest-green hover:bg-forest-green/90 text-primary-foreground">
              Start reading
              <ArrowRight class="ml-1.5 h-4 w-4" />
            </Button>
          </a>
        </div>
      </div>
    </nav>

    <!-- Hero -->
    <section class="paper-texture relative pt-16 sm:pt-20 pb-14 sm:pb-16 px-5 sm:px-6 overflow-hidden">
      <div class="max-w-4xl mx-auto text-center">
        <p class="eyebrow mb-7 flex items-center justify-center gap-2">
          <Feather class="h-3.5 w-3.5 text-warm-orange" />
          Stories worth your time
        </p>
        <h1 class="font-display text-[2.6rem] leading-[1.08] sm:text-6xl font-semibold tracking-tight text-dark-olive mb-6">
          A quiet place for
          <span class="relative inline-block">
            <em class="italic text-forest-green">stories</em>
            <svg
              class="absolute -left-1 -bottom-2 w-[calc(100%+0.5rem)] h-3 text-warm-orange"
              viewBox="0 0 120 12"
              fill="none"
              preserveAspectRatio="none"
              aria-hidden="true"
            >
              <path
                d="M3 9c22-7 46-8 60-5 14 3 32 3 54-2"
                stroke="currentColor"
                stroke-width="3"
                stroke-linecap="round"
              />
            </svg>
          </span>
          worth your time.
        </h1>
        <p class="text-lg sm:text-xl text-muted-foreground max-w-2xl mx-auto mb-10 leading-relaxed">
          Carefully written articles on the craft of writing, building, and the ideas we keep coming back to.
          No noise, no clickbait — just reading worth slowing down for.
        </p>
        <div class="flex items-center justify-center gap-4 flex-wrap">
          <a href="#latest">
            <Button size="lg" class="bg-forest-green hover:bg-forest-green/90 text-primary-foreground px-8 rounded-lg">
              Start reading
              <ArrowRight class="ml-2 h-4 w-4" />
            </Button>
          </a>
          <Button
            size="lg"
            variant="ghost"
            class="text-forest-green hover:text-forest-green/80 px-2 hover:bg-transparent"
            @click="showGetStarted = true"
          >
            Sign in to write
          </Button>
        </div>
        <div class="mt-12 sm:mt-14 flex justify-center">
          <span class="fleuron" aria-hidden="true"><span class="glyph">&#10086;</span></span>
        </div>
      </div>
    </section>

    <!-- The Latest -->
    <section id="latest" class="py-16 sm:py-20 px-5 sm:px-6">
      <div class="max-w-6xl mx-auto">
        <div class="flex items-end justify-between gap-4 mb-5">
          <div>
            <h2 class="font-display text-3xl sm:text-4xl font-semibold tracking-tight text-dark-olive">The Latest</h2>
          </div>
          <a
            :href="`${liveBlogBase}/index.php`"
            target="_blank"
            rel="noopener noreferrer"
            class="group inline-flex items-center gap-1.5 text-sm font-medium text-forest-green hover:text-forest-green/80 pb-1"
          >
            All stories
            <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
          </a>
        </div>
        <div class="border-b border-border/70 mb-10"></div>

        <!-- Loading -->
        <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <div v-for="i in 2" :key="i" class="animate-pulse">
            <div class="bg-muted rounded-xl aspect-[16/9] mb-4"></div>
            <div class="bg-muted rounded h-3 w-1/3 mb-3"></div>
            <div class="bg-muted rounded h-5 w-3/4 mb-2"></div>
            <div class="bg-muted rounded h-4 w-1/2"></div>
          </div>
        </div>

        <!-- Empty -->
        <div v-else-if="!blogs.length" class="text-center py-16 px-6 bg-card border border-border/60 rounded-xl">
          <span class="fleuron mb-6" aria-hidden="true"><span class="glyph">&#10086;</span></span>
          <h3 class="font-display text-xl font-semibold text-dark-olive mb-2">The journal is still being written</h3>
          <p class="text-muted-foreground max-w-md mx-auto mb-6">Stories are taking shape behind the scenes. Come back soon — or sign in and start writing your own.</p>
          <a
            :href="`${liveBlogBase}/signup.php`"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-2 text-sm font-medium text-forest-green hover:text-forest-green/80"
          >
            Join the journal
            <ArrowRight class="h-4 w-4" />
          </a>
        </div>

        <template v-else>
          <!-- Featured story -->
          <a
            v-if="featured()"
            :href="`${liveBlogBase}/post.php?id=${featured()!.id}`"
            target="_blank"
            rel="noopener noreferrer"
            class="group grid grid-cols-1 lg:grid-cols-2 gap-0 lg:gap-10 bg-card border border-border/60 rounded-xl overflow-hidden hover:shadow-[0_18px_40px_-20px_rgba(38,33,25,0.35)] transition-shadow mb-8"
          >
            <div class="aspect-[16/10] lg:aspect-auto lg:h-full overflow-hidden bg-muted">
              <img
                v-if="featured()!.image"
                :src="featured()!.image"
                :alt="featured()!.title"
                class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-700"
              >
              <div v-else class="w-full h-full flex items-center justify-center bg-secondary/60">
                <span class="font-display italic text-[4.5rem] leading-none text-forest-green/40 select-none">{{ featured()!.title.charAt(0) }}</span>
              </div>
            </div>
            <div class="p-7 sm:p-9 flex flex-col justify-center">
              <p class="eyebrow mb-3 flex items-center gap-3">
                <span class="text-warm-orange">Featured</span>
                <span class="text-border/70" aria-hidden="true">·</span>
                <span v-if="featured()!.category_name" class="text-forest-green">{{ featured()!.category_name }}</span>
                <span v-if="formatDate(featured()!.created_at)" class="inline-flex items-center gap-1.5">
                  <CalendarDays class="h-3.5 w-3.5" /> {{ formatDate(featured()!.created_at) }}
                </span>
              </p>
              <h3 class="font-display text-2xl sm:text-3xl font-semibold leading-snug text-dark-olive group-hover:text-forest-green transition-colors mb-4">
                {{ featured()!.title }}
              </h3>
              <p class="text-muted-foreground leading-relaxed mb-6">
                {{ excerpt(featured()!.excerpt ?? featured()!.content) }}
              </p>
              <span class="inline-flex items-center gap-1.5 text-sm font-medium text-warm-orange group-hover:gap-2.5 transition-all">
                Read the story
                <ArrowRight class="h-4 w-4" />
              </span>
            </div>
          </a>

          <!-- Remaining stories -->
          <div v-if="rest().length" class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-10">
            <a
              v-for="blog in rest()"
              :key="blog.id"
              :href="`${liveBlogBase}/post.php?id=${blog.id}`"
              target="_blank"
              rel="noopener noreferrer"
              class="group flex flex-col"
            >
              <div class="aspect-[16/10] overflow-hidden bg-muted rounded-lg mb-5">
                <img
                  v-if="blog.image"
                  :src="blog.image"
                  :alt="blog.title"
                  class="w-full h-full object-cover group-hover:scale-[1.04] transition-transform duration-700"
                >
                <div v-else class="w-full h-full flex items-center justify-center bg-secondary/60">
                  <span class="font-display italic text-[3.5rem] leading-none text-forest-green/40 select-none">{{ blog.title.charAt(0) }}</span>
                </div>
              </div>
              <div class="border-t border-border/70 pt-4">
                <p class="eyebrow mb-2 flex items-center gap-2.5">
                  <span v-if="blog.category_name" class="text-forest-green">{{ blog.category_name }}</span>
                  <span v-if="blog.category_name && formatDate(blog.created_at)" class="text-border/70" aria-hidden="true">·</span>
                  <span v-if="formatDate(blog.created_at)">{{ formatDate(blog.created_at) }}</span>
                </p>
                <h3 class="font-display text-xl font-semibold leading-snug text-dark-olive group-hover:text-forest-green transition-colors mb-2">
                  {{ blog.title }}
                </h3>
                <p class="text-muted-foreground text-[0.95rem] leading-relaxed mb-3">
                  {{ excerpt(blog.excerpt ?? blog.content, 140) }}
                </p>
                <span class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground group-hover:text-warm-orange transition-colors">
                  <Clock class="h-3.5 w-3.5" />
                  {{ readingTime(blog) }}
                  <span v-if="(blog.views ?? 0) > 0" class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground group-hover:text-warm-orange transition-colors">
                    <span class="text-border/70" aria-hidden="true">·</span>
                    {{ Number(blog.views).toLocaleString() }} reads
                  </span>
                </span>
              </div>
            </a>
          </div>
        </template>
      </div>
    </section>

    <!-- Topics -->
    <section v-if="categories.length" class="py-16 sm:py-20 px-5 sm:px-6 bg-secondary/40 border-y border-border/60">
      <div class="max-w-4xl mx-auto">
        <div class="flex flex-col gap-1.5 mb-6">
          <h2 class="font-display text-3xl sm:text-4xl font-semibold tracking-tight text-dark-olive">Explore the journal</h2>
        </div>
        <p class="text-muted-foreground text-[0.95rem] leading-relaxed mb-8 max-w-xl">
          Wander by subject — every section is filed and kept by hand, one story at a time.
        </p>
        <div class="toc-list">
          <a
            v-for="(cat, i) in categories.slice(0, 8)"
            :key="cat.id"
            :href="`${liveBlogBase}/index.php?category=${cat.id}`"
            target="_blank"
            rel="noopener noreferrer"
            class="toc-row"
          >
            <span class="flex items-center gap-4">
              <span class="toc-count">{{ String(i + 1).padStart(2, '0') }}</span>
              <span class="toc-name">{{ cat.name }}</span>
            </span>
            <ArrowRight class="toc-arrow h-4 w-4" />
          </a>
        </div>
      </div>
    </section>

    <!-- About -->
    <section class="py-16 sm:py-20 px-5 sm:px-6">
      <div class="max-w-3xl mx-auto text-center">
        <div class="flex justify-center mb-6">
          <span class="fleuron" aria-hidden="true"><span class="glyph">&#10086;</span></span>
        </div>
        <blockquote class="font-display text-2xl sm:text-[2rem] leading-snug font-medium text-dark-olive">
          “We started WAM because the internet forgot how to slow down. Every piece here is
          written, built, edited, and published by hand — for readers who still believe the best ideas
          deserve more than a passing glance.”
        </blockquote>
        <p class="text-muted-foreground mt-8 text-[0.95rem] leading-relaxed max-w-xl mx-auto">
          That means thoughtful essays, careful software, and a few strong opinions — and not a single popup.
          If that sounds like your kind of reading, you're already in the right place.
        </p>
      </div>
    </section>

    <!-- Join -->
    <section class="px-5 sm:px-6 pb-16 sm:pb-20">
      <div class="max-w-3xl mx-auto text-center border border-border/70 rounded-xl bg-card paper-texture px-6 sm:px-12 py-14 sm:py-16">
        <h2 class="font-display text-3xl sm:text-4xl font-semibold tracking-tight text-dark-olive mb-4">
          Your first story starts here
        </h2>
        <p class="text-muted-foreground max-w-xl mx-auto mb-8 leading-relaxed">
          Create an account and we'll email you a link to sign in —
          no password to remember. Then write, edit, and publish from your own quiet corner.
        </p>
        <div class="flex items-center justify-center gap-4 flex-wrap">
          <a
            :href="`${liveBlogBase}/signup.php`"
            target="_blank"
            rel="noopener noreferrer"
          >
            <Button size="lg" class="bg-forest-green hover:bg-forest-green/90 text-primary-foreground px-8 rounded-lg">
              Create an account
            </Button>
          </a>
          <Button
            size="lg"
            variant="ghost"
            class="text-forest-green hover:text-forest-green/80 px-2 hover:bg-transparent"
            @click="showGetStarted = true"
          >
            Sign in to write
          </Button>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-border/60 py-12 px-5 sm:px-6">
      <div class="max-w-6xl mx-auto">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6 mb-8">
          <div class="flex items-center gap-2">
            <img src="/favicon.svg" alt="WAM logo" width="64" height="64" class="h-8 w-8 shrink-0" />
            <span class="font-display font-semibold text-xl text-dark-olive">WAM</span>
            <span class="eyebrow text-forest-green">Blog</span>
          </div>
          <p class="text-sm text-muted-foreground">
            Stories worth your time, written with care.
          </p>
          <div class="flex items-center gap-6 text-sm text-muted-foreground">
            <a href="https://github.com/richiekaroki/php-blog-project-backend" target="_blank" rel="noopener noreferrer" class="hover:text-forest-green transition-colors">GitHub</a>
            <button
              type="button"
              @click="showGetStarted = true"
              class="hover:text-forest-green transition-colors cursor-pointer"
            >
              Join the journal
            </button>
          </div>
        </div>
        <div class="border-t border-border/50 pt-8 flex items-center justify-center gap-4">
          <span class="fleuron" aria-hidden="true"><span class="glyph">&#10086;</span></span>
          <button
            type="button"
            @click="scrollToTop"
            class="text-xs uppercase tracking-[0.14em] font-semibold text-muted-foreground hover:text-forest-green transition-colors cursor-pointer"
          >
            Back to top
          </button>
        </div>
      </div>
    </footer>

    <GetStartedModal v-if="showGetStarted" @close="showGetStarted = false" />
  </div>
</template>