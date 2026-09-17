import { apiClient } from './client'

import type { ApiResponse, LoginCredentials, User } from '@/types/api'

export async function getCsrfCookie(): Promise<void> {
  await apiClient.get('/sanctum/csrf-cookie')
}

export async function login(credentials: LoginCredentials): Promise<User> {
  const response = await apiClient.post<ApiResponse<User>>('/api/login', credentials)

  return response.data.data
}

export async function getCurrentUser(): Promise<User> {
  const response = await apiClient.get<ApiResponse<User>>('/api/user')

  return response.data.data
}

export async function logout(): Promise<void> {
  await apiClient.post('/api/logout')
}
