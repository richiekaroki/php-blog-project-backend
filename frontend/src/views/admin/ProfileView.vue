<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Card from '@/components/ui/Card.vue'
import CardHeader from '@/components/ui/CardHeader.vue'
import CardTitle from '@/components/ui/CardTitle.vue'
import CardDescription from '@/components/ui/CardDescription.vue'
import CardContent from '@/components/ui/CardContent.vue'
import { Loader2, User, Mail, ShieldCheck, Save } from 'lucide-vue-next'

const authStore = useAuthStore()
const toast = useToast()

const loading = ref(true)
const savingProfile = ref(false)

const profile = ref({
  username: '',
  email: '',
  role: '',
})

onMounted(async () => {
  try {
    const res = await api.get('/api/profile')
    const data = res.data?.data
    if (data) {
      profile.value.username = data.username ?? ''
      profile.value.email = data.email ?? ''
      profile.value.role = data.role ?? ''
    }
  } catch {
    toast.error('Could not load your profile.')
  } finally {
    loading.value = false
  }
})

async function saveProfile() {
  const username = profile.value.username.trim()
  const email = profile.value.email.trim()
  if (!username) {
    toast.error('Username cannot be empty.')
    return
  }
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    toast.error('Please enter a valid email address.')
    return
  }

  savingProfile.value = true
  try {
    const res = await api.put('/api/profile', { username, email })
    const data = res.data?.data
    if (data) {
      profile.value.username = data.username ?? ''
      profile.value.email = data.email ?? ''
      authStore.user = data
    }
    toast.success('Profile updated')
  } catch (e) {
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as any).response?.data?.error?.message || (e as any).response?.data?.error || 'Failed to update profile.'
        : 'Failed to update profile.'
    toast.error(typeof msg === 'string' ? msg : 'Failed to update profile.')
  } finally {
    savingProfile.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl space-y-6">
    <div>
      <h2 class="font-display text-2xl font-bold text-dark-olive">Profile</h2>
      <p class="text-sm text-muted-foreground mt-1">Manage your account details</p>
    </div>

    <div v-if="loading" class="space-y-4">
      <div class="bg-muted rounded-2xl h-40 animate-pulse"></div>
      <div class="bg-muted rounded-2xl h-40 animate-pulse"></div>
    </div>

    <template v-else>
      <Card class="border-0 shadow-sm">
        <CardHeader>
          <CardTitle class="font-display text-lg flex items-center gap-2">
            <User class="h-5 w-5 text-forest-green" />
            Account details
          </CardTitle>
          <CardDescription class="text-muted-foreground">Update your username and email</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <label class="text-sm font-medium text-foreground">Username</label>
            <div class="relative">
              <User class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                v-model="profile.username"
                type="text"
                class="pl-10 bg-background border-border focus:border-forest-green focus:ring-forest-green"
              />
            </div>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-foreground">Email address</label>
            <div class="relative">
              <Mail class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                v-model="profile.email"
                type="email"
                placeholder="you@example.com"
                class="pl-10 bg-background border-border focus:border-forest-green focus:ring-forest-green"
              />
            </div>
            <p class="text-xs text-muted-foreground">Used for passwordless magic-link sign in.</p>
          </div>

          <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <ShieldCheck class="h-4 w-4 text-forest-green" />
            <span class="capitalize">{{ profile.role || 'admin' }}</span>
            <span class="text-xs text-muted-foreground/60">· role</span>
          </div>

          <Button
            @click="saveProfile"
            :disabled="savingProfile"
            class="bg-forest-green hover:bg-forest-green/90 text-white"
          >
            <Loader2 v-if="savingProfile" class="mr-2 h-4 w-4 animate-spin" />
            <Save v-else class="mr-2 h-4 w-4" />
            Save changes
          </Button>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
