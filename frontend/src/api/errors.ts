import axios from 'axios'

import type { ApiErrorResponse } from '@/types/api'

export interface NormalizedApiError {
  message: string
  fields: Record<string, string[]>
  status?: number
}

export function normalizeApiError(error: unknown, fallback: string): NormalizedApiError {
  if (!axios.isAxiosError<ApiErrorResponse>(error)) {
    return { message: fallback, fields: {} }
  }

  return {
    message: error.response?.data?.message ?? fallback,
    fields: error.response?.data?.errors ?? {},
    status: error.response?.status,
  }
}

export function isUnauthorized(error: unknown): boolean {
  return axios.isAxiosError(error) && error.response?.status === 401
}
