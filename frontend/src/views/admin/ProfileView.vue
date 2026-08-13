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
import { Loader2, User, Mail, KeyRound, ShieldCheck, Save, Eye, EyeOff } from 'lucide-vue-next'

const authStore = useAuthStore()
const toast = useToast()

const loading = ref(true)
const savingProfile = ref(false)
const savingPassword = ref(false)

const profile = ref({
  username: '',
  email: '',
  role: '',
})

const passwordForm = ref({
  current: '',
  next: '',
  confirm: '',
})

const showPassword = ref(false)

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

async function savePassword() {
  const { current, next, confirm } = passwordForm.value
  if (!current || !next || !confirm) {
    toast.error('All password fields are required.')
    return
  }
  if (next.length < 8) {
    toast.error('New password must be at least 8 characters.')
    return
  }
  if (next !== confirm) {
    toast.error('New password and confirmation do not match.')
    return
  }

  savingPassword.value = true
  try {
    await api.put('/api/profile', { current_password: current, new_password: next })
    toast.success('Password updated')
    passwordForm.value = { current: '', next: '', confirm: '' }
  } catch (e) {
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as any).response?.data?.error?.message || (e as any).response?.data?.error || 'Failed to update password.'
        : 'Failed to update password.'
    toast.error(typeof msg === 'string' ? msg : 'Failed to update password.')
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl space-y-6">
    <div>
      <h2 class="font-display text-2xl font-bold text-dark-olive">Profile</h2>
      <p class="text-sm text-muted-foreground mt-1">Manage your account details and password</p>
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

      <Card class="border-0 shadow-sm">
        <CardHeader>
          <CardTitle class="font-display text-lg flex items-center gap-2">
            <KeyRound class="h-5 w-5 text-warm-orange" />
            Change password
          </CardTitle>
          <CardDescription class="text-muted-foreground">Use a strong password you don't use anywhere else</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <label class="text-sm font-medium text-foreground">Current password</label>
            <div class="relative">
              <KeyRound class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                v-model="passwordForm.current"
                :type="showPassword ? 'text' : 'password'"
                class="pl-10 pr-10 bg-background border-border focus:border-forest-green focus:ring-forest-green"
              />
            </div>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-foreground">New password</label>
            <div class="relative">
              <KeyRound class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                v-model="passwordForm.next"
                :type="showPassword ? 'text' : 'password'"
                class="pl-10 pr-10 bg-background border-border focus:border-forest-green focus:ring-forest-green"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
              >
                <EyeOff v-if="showPassword" class="h-4 w-4" />
                <Eye v-else class="h-4 w-4" />
              </button>
            </div>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-foreground">Confirm new password</label>
            <div class="relative">
              <KeyRound class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                v-model="passwordForm.confirm"
                :type="showPassword ? 'text' : 'password'"
                class="pl-10 bg-background border-border focus:border-forest-green focus:ring-forest-green"
              />
            </div>
          </div>

          <Button
            @click="savePassword"
            :disabled="savingPassword"
            variant="outline"
            class="border-forest-green/30 text-forest-green hover:bg-forest-green hover:text-white"
          >
            <Loader2 v-if="savingPassword" class="mr-2 h-4 w-4 animate-spin" />
            <KeyRound v-else class="mr-2 h-4 w-4" />
            Update password
          </Button>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
