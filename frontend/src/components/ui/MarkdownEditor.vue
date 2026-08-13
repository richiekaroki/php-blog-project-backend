<script setup lang="ts">
import { ref, watch } from 'vue'
import { Bold, Italic, List, ListOrdered, Code, Quote, Link } from 'lucide-vue-next'

const props = defineProps<{
  modelValue: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const textarea = ref<HTMLTextAreaElement | null>(null)

function insert(before: string, after: string = '') {
  const el = textarea.value
  if (!el) return
  
  const start = el.selectionStart
  const end = el.selectionEnd
  const text = el.value
  const selected = text.substring(start, end)
  
  const newText = text.substring(0, start) + before + selected + after + text.substring(end)
  emit('update:modelValue', newText)
  
  // Restore cursor position
  setTimeout(() => {
    el.focus()
    el.selectionStart = start + before.length
    el.selectionEnd = start + before.length + selected.length
  }, 0)
}

function wrapSelection(wrapper: string) {
  const el = textarea.value
  if (!el) return
  
  const start = el.selectionStart
  const end = el.selectionEnd
  const text = el.value
  const selected = text.substring(start, end)
  
  if (selected.startsWith(wrapper) && selected.endsWith(wrapper)) {
    // Remove wrapper
    const newText = text.substring(0, start) + selected.slice(wrapper.length, -wrapper.length) + text.substring(end)
    emit('update:modelValue', newText)
  } else {
    // Add wrapper
    const newText = text.substring(0, start) + wrapper + selected + wrapper + text.substring(end)
    emit('update:modelValue', newText)
  }
  
  setTimeout(() => {
    el.focus()
  }, 0)
}
</script>

<template>
  <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
    <!-- Toolbar -->
    <div class="flex items-center gap-1 p-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
      <button
        type="button"
        @click="wrapSelection('**')"
        class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded"
        title="Bold"
      >
        <Bold class="w-4 h-4" />
      </button>
      <button
        type="button"
        @click="wrapSelection('*')"
        class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded"
        title="Italic"
      >
        <Italic class="w-4 h-4" />
      </button>
      <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1" />
      <button
        type="button"
        @click="insert('\n- ')"
        class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded"
        title="Bullet List"
      >
        <List class="w-4 h-4" />
      </button>
      <button
        type="button"
        @click="insert('\n1. ')"
        class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded"
        title="Numbered List"
      >
        <ListOrdered class="w-4 h-4" />
      </button>
      <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1" />
      <button
        type="button"
        @click="insert('\n```\n', '\n```')"
        class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded"
        title="Code Block"
      >
        <Code class="w-4 h-4" />
      </button>
      <button
        type="button"
        @click="insert('\n> ')"
        class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded"
        title="Quote"
      >
        <Quote class="w-4 h-4" />
      </button>
      <button
        type="button"
        @click="insert('[', '](url)')"
        class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded"
        title="Link"
      >
        <Link class="w-4 h-4" />
      </button>
    </div>
    
    <!-- Textarea -->
    <textarea
      ref="textarea"
      :value="modelValue"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
      class="w-full min-h-[200px] p-3 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white resize-y focus:outline-none"
      placeholder="Write your content here... (supports Markdown)"
    />
  </div>
</template>
