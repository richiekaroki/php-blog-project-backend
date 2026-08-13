<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { api } from '@/api/client'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Card from '@/components/ui/Card.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import CardDescription from '@/components/ui/CardDescription.vue'
import CardContent from '@/components/ui/CardContent.vue'
import { Loader2, ArrowLeft, Mail, ArrowRight, CheckCircle2 } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'

const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

const mode = ref<'password' | 'magic'>('password')

const form = ref({
  username: '',
  password: '',
})

const magicEmail = ref('')
const magicLoading = ref(false)
const magicSent = ref(false)
const magicError = ref('')

const handleSubmit = async () => {
  const success = await authStore.login(form.value)
  if (success) {
    toast.success('Welcome back!')
    router.push('/admin')
  } else {
    toast.error(authStore.error || 'Login failed')
  }
}

async function handleMagicSubmit() {
  if (!magicEmail.value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(magicEmail.value)) {
    magicError.value = 'Please enter a valid email address.'
    return
  }

  magicLoading.value = true
  magicError.value = ''
  try {
    await api.post('/api/magic/request', { email: magicEmail.value })
    magicSent.value = true
  } catch (e) {
    magicError.value = e instanceof Error ? e.message : 'Something went wrong. Please try again.'
  } finally {
    magicLoading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-background via-warm-cream/30 to-background px-4">
    <div class="w-full max-w-md">
      <RouterLink to="/" class="inline-flex items-center gap-2 text-muted-foreground hover:text-forest-green mb-8 transition-colors">
        <ArrowLeft class="h-4 w-4" />
        Back to blog
      </RouterLink>
      
      <Card class="border-0 shadow-xl bg-card">
        <CardHeader class="text-center pb-2">
          <div class="w-12 h-12 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
            <span class="text-primary-foreground font-display font-bold text-xl">W</span>
          </div>
          <CardTitle class="font-display text-2xl text-dark-olive">Welcome back</CardTitle>
          <CardDescription class="text-muted-foreground">Sign in to manage your stories</CardDescription>
        </CardHeader>
        <CardContent class="pt-6">
          <!-- Mode toggle -->
          <div class="flex items-center gap-1 p-1 bg-muted rounded-lg mb-5">
            <button
              @click="mode = 'password'"
              type="button"
              class="flex-1 px-3 py-2 text-sm font-medium rounded-md transition-colors"
              :class="mode === 'password' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
            >
              Password
            </button>
            <button
              @click="mode = 'magic'"
              type="button"
              class="flex-1 px-3 py-2 text-sm font-medium rounded-md transition-colors"
              :class="mode === 'magic' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
            >
              Magic Link
            </button>
          </div>

          <!-- Password form -->
          <form v-if="mode === 'password'" @submit.prevent="handleSubmit" class="space-y-5">
            <div v-if="authStore.error" class="p-4 bg-destructive/10 border border-destructive/20 rounded-lg text-destructive text-sm flex items-center gap-2">
              <span class="text-destructive font-medium">Error:</span>
              {{ authStore.error }}
            </div>

            <div class="space-y-2">
              <label class="text-sm font-medium text-dark-olive">Username</label>
              <Input
                v-model="form.username"
                type="text"
                required
                placeholder="Enter your username"
                class="bg-background border-border focus:border-forest-green focus:ring-forest-green"
              />
            </div>

            <div class="space-y-2">
              <label class="text-sm font-medium text-dark-olive">Password</label>
              <Input
                v-model="form.password"
                type="password"
                required
                placeholder="Enter your password"
                class="bg-background border-border focus:border-forest-green focus:ring-forest-green"
              />
            </div>

            <Button 
              type="submit" 
              :disabled="authStore.loading" 
              class="w-full bg-forest-green hover:bg-forest-green/90 text-white h-11"
            >
              <Loader2 v-if="authStore.loading" class="mr-2 h-4 w-4 animate-spin" />
              {{ authStore.loading ? 'Signing in...' : 'Sign In' }}
            </Button>
          </form>

          <!-- Magic link form -->
          <div v-else-if="mode === 'magic'" class="space-y-5">
            <div v-if="magicSent" class="text-center py-4">
              <CheckCircle2 class="h-12 w-12 text-forest-green mx-auto mb-3" />
              <p class="text-muted-foreground text-sm leading-relaxed">
                We sent a sign in link to <span class="font-medium text-foreground">{{ magicEmail }}</span>.
                Click it to sign in. The link expires in 15 minutes.
              </p>
            </div>

            <template v-else>
              <p class="text-sm text-muted-foreground leading-relaxed">
                No password needed — we'll email you a secure sign in link.
              </p>

              <div v-if="magicError" class="p-3 bg-destructive/10 border border-destructive/20 rounded-lg text-destructive text-sm">
                {{ magicError }}
              </div>

              <div class="space-y-2">
                <label class="text-sm font-medium text-dark-olive">Email address</label>
                <div class="relative">
                  <Mail class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                  <Input
                    v-model="magicEmail"
                    type="email"
                    required
                    placeholder="you@example.com"
                    class="pl-10 bg-background border-border focus:border-forest-green focus:ring-forest-green"
                  />
                </div>
              </div>

              <Button
                @click="handleMagicSubmit"
                :disabled="magicLoading"
                class="w-full bg-forest-green hover:bg-forest-green/90 text-white h-11"
              >
                <Loader2 v-if="magicLoading" class="mr-2 h-4 w-4 animate-spin" />
                <ArrowRight v-else class="mr-2 h-4 w-4" />
                {{ magicLoading ? 'Sending link...' : 'Email me a sign in link' }}
              </Button>
            </template>
          </div>
        </CardContent>
      </Card>
      
      <p class="text-center text-sm text-muted-foreground mt-6">
        A place for thoughtful stories and ideas.
      </p>
    </div>
  </div>
</template>
