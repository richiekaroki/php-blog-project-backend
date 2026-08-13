<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue'
import Toast from '@/components/ui/Toast.vue'
</script>

<template>
  <Toast />
  <AppLayout>
    <RouterView />
  </AppLayout>
</template>
