import { ref, watch, onMounted } from 'vue'

const isDark = ref(false)

export function useDarkMode() {
  onMounted(() => {
    const saved = localStorage.getItem('darkMode')
    if (saved !== null) {
      isDark.value = saved === 'true'
    } else {
      isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches
    }
    updateClass()
  })

  watch(isDark, () => {
    localStorage.setItem('darkMode', String(isDark.value))
    updateClass()
  })

  function updateClass() {
    document.documentElement.classList.toggle('dark', isDark.value)
  }

  function toggle() {
    isDark.value = !isDark.value
  }

  return { isDark, toggle }
}
