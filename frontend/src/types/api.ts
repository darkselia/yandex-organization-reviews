export interface User {
  id: number
  name: string
  email: string
}

export interface ApiResponse<T> {
  data: T
}

export interface ApiErrorResponse {
  message?: string
  errors?: Record<string, string[]>
}

export interface LoginCredentials {
  email: string
  password: string
}
