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

export type ParseRunStatus = 'queued' | 'running' | 'retrying' | 'succeeded' | 'failed'

export type ParseRunErrorCode =
  | 'organization_unavailable'
  | 'source_temporarily_unavailable'
  | 'processing_failed'

export interface ParseRunError {
  code: ParseRunErrorCode
}

export interface ParseRun {
  id: number
  organization_id: number | null
  status: ParseRunStatus
  attempt_count: number
  reviews_fetched: number
  reviews_expected: number | null
  progress_percent: number | null
  error: ParseRunError | null
}

export interface Organization {
  id: number
  source_url: string
  name: string
  rating: number | null
  ratings_count: number
  reviews_count: number
  last_synced_at: string | null
  latest_parse_run: ParseRun | null
}

export interface OrganizationParseRequest {
  parse_run: ParseRun
}

export interface Review {
  id: number
  external_id: string
  author_name: string
  published_at: string
  text: string | null
  rating: number
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: PaginationMeta
}
