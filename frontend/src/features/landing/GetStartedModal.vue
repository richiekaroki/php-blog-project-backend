<script setup lang="ts">
import { ref } from 'vue'
import { api } from '@/api/client'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import { Loader2, Mail, X, CheckCircle2, ArrowRight } from 'lucide-vue-next'

const emit = defineEmits<{
  close: []
}>()

const email = ref('')
const loading = ref(false)
const error = ref('')
const sent = ref(false)

async function submit() {
  if (!email.value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    error.value = 'Please enter a valid email address.'
    return
  }

  loading.value = true
  error.value = ''
  try {
    await api.post('/api/magic/request', { email: email.value })
    sent.value = true
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Something went wrong. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
    @click.self="emit('close')"
  >
    <div class="w-full max-w-md bg-card border border-border rounded-2xl shadow-xl overflow-hidden">
      <div class="flex items-center justify-between px-6 py-4 border-b border-border">
        <h2 class="font-display text-lg font-semibold text-foreground">Get Started</h2>
        <button
          @click="emit('close')"
          class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-lg transition-colors"
          aria-label="Close"
        >
          <X class="h-5 w-5" />
        </button>
      </div>

      <div class="p-6">
        <div v-if="sent" class="text-center py-6">
          <CheckCircle2 class="h-14 w-14 text-forest-green mx-auto mb-4" />
          <h3 class="font-display text-xl font-semibold text-foreground mb-2">Check your inbox</h3>
          <p class="text-muted-foreground text-sm leading-relaxed">
            We sent a sign in link to <span class="font-medium text-foreground">{{ email }}</span>.
            Click it to get started. The link expires in 15 minutes.
          </p>
        </div>

        <template v-else>
          <p class="text-muted-foreground text-sm mb-6 leading-relaxed">
            Enter your email and we'll send you a secure sign in link.
          </p>

          <div v-if="error" class="p-3 mb-4 bg-destructive/10 border border-destructive/20 rounded-lg text-destructive text-sm">
            {{ error }}
          </div>

          <form @submit.prevent="submit" class="space-y-4">
            <div class="space-y-2">
              <label class="text-sm font-medium text-foreground">Email address</label>
              <div class="relative">
                <Mail class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                  v-model="email"
                  type="email"
                  required
                  placeholder="you@example.com"
                  class="pl-10 bg-background border-border focus:border-forest-green focus:ring-forest-green h-11"
                />
              </div>
            </div>

            <Button
              type="submit"
              :disabled="loading"
              class="w-full bg-forest-green hover:bg-forest-green/90 text-white h-11"
            >
              <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
              <ArrowRight v-else class="mr-2 h-4 w-4" />
              {{ loading ? 'Sending link...' : 'Send me a sign in link' }}
            </Button>
          </form>
        </template>
      </div>
    </div>
  </div>
</template>