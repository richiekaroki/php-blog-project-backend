<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Activity, FileText, Tag, Trash2, Edit, Plus } from 'lucide-vue-next'

interface ActivityItem {
  id: number
  action: string
  entity_type: string
  entity_id: number
  details: Record<string, unknown> | null
  user_ip: string
  created_at: string
}

const activities = ref<ActivityItem[]>([])
const loading = ref(true)

const actionIcons: Record<string, typeof Plus> = {
  created: Plus,
  updated: Edit,
  deleted: Trash2,
}

const actionColors: Record<string, string> = {
  created: 'text-green-600 bg-green-100',
  updated: 'text-blue-600 bg-blue-100',
  deleted: 'text-red-600 bg-red-100',
}

const entityIcons: Record<string, typeof FileText> = {
  blog: FileText,
  category: Tag,
}

async function fetchActivities() {
  loading.value = true
  try {
    const token = localStorage.getItem('token')
    const res = await fetch('/api/activity?limit=20', {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    const data = await res.json()
    activities.value = data.data || []
  } catch (error) {
    console.error('Failed to fetch activities:', error)
  } finally {
    loading.value = false
  }
}

function formatTime(dateStr: string): string {
  const date = new Date(dateStr)
  const now = new Date()
  const diff = now.getTime() - date.getTime()
  
  if (diff < 60000) return 'Just now'
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`
  return date.toLocaleDateString()
}

function formatAction(action: string): string {
  return action.charAt(0).toUpperCase() + action.slice(1)
}

onMounted(fetchActivities)
</script>

<template>
  <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
    <div class="p-4 border-b border-gray-200 dark:border-gray-800">
      <div class="flex items-center gap-2">
        <Activity class="w-5 h-5 text-gray-500 dark:text-gray-400" />
        <h3 class="font-semibold text-gray-900 dark:text-white">Recent Activity</h3>
      </div>
    </div>
    
    <div class="divide-y divide-gray-200 dark:divide-gray-800">
      <div v-if="loading" class="p-8 text-center text-gray-500 dark:text-gray-400">
        Loading activity...
      </div>
      
      <div v-else-if="activities.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
        No recent activity
      </div>
      
      <div
        v-for="activity in activities"
        :key="activity.id"
        class="p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
      >
        <div class="flex items-start gap-3">
          <div
            class="p-2 rounded-lg"
            :class="actionColors[activity.action] || 'text-gray-600 bg-gray-100'"
          >
            <component
              :is="entityIcons[activity.entity_type] || FileText"
              class="w-4 h-4"
            />
          </div>
          
          <div class="flex-1 min-w-0">
            <p class="text-sm text-gray-900 dark:text-white">
              <span class="font-medium">{{ formatAction(activity.action) }}</span>
              <span class="text-gray-500 dark:text-gray-400">{{ activity.entity_type }}</span>
              <span class="text-gray-400 dark:text-gray-500">#{{ activity.entity_id }}</span>
            </p>
            <p v-if="activity.details?.title" class="text-sm text-gray-500 dark:text-gray-400 truncate">
              "{{ activity.details.title }}"
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
              {{ formatTime(activity.created_at) }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
