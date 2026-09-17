import { createRouter, createWebHistory } from 'vue-router'

import { authActions, authState } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: { name: 'organizations' },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/organizations',
      name: 'organizations',
      component: () => import('@/views/OrganizationsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
})

router.beforeEach(async (to) => {
  await authActions.initialize()

  if (to.meta.requiresAuth && !authState.user) {
    return { name: 'login' }
  }

  if (to.meta.guestOnly && authState.user) {
    return { name: 'organizations' }
  }
})

export default router
