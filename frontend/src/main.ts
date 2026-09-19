import './assets/main.css'

import { createApp } from 'vue'
import App from './App.vue'
import { apiClient } from './api/client'
import { isUnauthorized } from './api/errors'
import router from './router'
import { authActions } from './stores/auth'

apiClient.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    if (isUnauthorized(error)) {
      authActions.markGuest()

      if (router.currentRoute.value.name !== 'login') {
        void router.replace({ name: 'login' })
      }
    }

    return Promise.reject(error)
  },
)

createApp(App).use(router).mount('#app')
