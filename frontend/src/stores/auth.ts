import { readonly, reactive } from 'vue'

import * as authApi from '@/api/auth'
import { isUnauthorized, normalizeApiError } from '@/api/errors'
import type { LoginCredentials, User } from '@/types/api'

type AuthStatus = 'unknown' | 'loading' | 'authenticated' | 'guest'

const state = reactive<{
  user: User | null
  status: AuthStatus
  initializationError: string | null
}>({
  user: null,
  status: 'unknown',
  initializationError: null,
})

let initializationPromise: Promise<void> | null = null

async function initialize(): Promise<void> {
  if (initializationPromise) {
    return initializationPromise
  }

  if (state.status !== 'unknown') {
    return
  }

  state.status = 'loading'
  state.initializationError = null

  initializationPromise = authApi
    .getCurrentUser()
    .then((user) => {
      state.user = user
      state.status = 'authenticated'
    })
    .catch((error: unknown) => {
      state.user = null
      state.status = 'guest'

      if (!isUnauthorized(error)) {
        state.initializationError = normalizeApiError(
          error,
          'Не удалось проверить авторизацию. Убедитесь, что backend запущен.',
        ).message
      }
    })
    .finally(() => {
      initializationPromise = null
    })

  return initializationPromise
}

async function login(credentials: LoginCredentials): Promise<void> {
  await authApi.getCsrfCookie()
  const user = await authApi.login(credentials)

  state.user = user
  state.status = 'authenticated'
  state.initializationError = null
}

async function logout(): Promise<void> {
  await authApi.logout()

  state.user = null
  state.status = 'guest'
  state.initializationError = null
}

export const authState = readonly(state)
export const authActions = { initialize, login, logout }
