import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'landing',
      component: () => import('@/views/LandingView.vue'),
      meta: { requiresGuest: true },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { requiresGuest: true },
    },
    {
      path: '/admin',
      name: 'dashboard',
      component: () => import('@/views/admin/DashboardView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/admin/blogs',
      name: 'blogs',
      component: () => import('@/views/admin/BlogsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/admin/categories',
      name: 'categories',
      component: () => import('@/views/admin/CategoriesView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/admin/profile',
      name: 'profile',
      component: () => import('@/views/admin/ProfileView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
})

router.beforeEach((to, _from, next) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'login' })
  } else if (to.meta.requiresGuest && to.name !== 'landing' && authStore.isAuthenticated) {
    next({ name: 'dashboard' })
  } else {
    next()
  }
})

export default router
