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
import { Loader2 } from 'lucide-vue-next'

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
  <div class="min-h-screen flex items-center justify-center bg-muted">
    <Card class="w-full max-w-md">
      <CardHeader class="text-center">
        <CardTitle class="text-2xl">Blog Admin</CardTitle>
        <CardDescription>Sign in to manage your content</CardDescription>
      </CardHeader>
      <CardContent>
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div v-if="authStore.error" class="p-3 bg-destructive/10 border border-destructive/20 rounded-md text-destructive text-sm">
            {{ authStore.error }}
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium leading-none">Username</label>
            <Input
              v-model="form.username"
              type="text"
              required
              placeholder="Enter username"
            />
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium leading-none">Password</label>
            <Input
              v-model="form.password"
              type="password"
              required
              placeholder="Enter password"
            />
          </div>

          <Button type="submit" :disabled="authStore.loading" class="w-full">
            <Loader2 v-if="authStore.loading" class="mr-2 h-4 w-4 animate-spin" />
            {{ authStore.loading ? 'Signing in...' : 'Sign In' }}
          </Button>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
