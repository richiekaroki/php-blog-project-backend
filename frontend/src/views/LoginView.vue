<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Card from '@/components/ui/Card.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import CardDescription from '@/components/ui/CardDescription.vue'
import CardContent from '@/components/ui/CardContent.vue'
import { Loader2, ArrowLeft } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'

const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

const form = ref({
  username: '',
  password: '',
})

const handleSubmit = async () => {
  const success = await authStore.login(form.value)
  if (success) {
    toast.success('Welcome back!')
    router.push('/admin')
  } else {
    toast.error(authStore.error || 'Login failed')
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
          <form @submit.prevent="handleSubmit" class="space-y-5">
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
        </CardContent>
      </Card>
      
      <p class="text-center text-sm text-muted-foreground mt-6">
        A place for thoughtful stories and ideas.
      </p>
    </div>
  </div>
</template>
