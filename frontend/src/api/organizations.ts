import { apiClient } from './client'

import type {
  ApiResponse,
  Organization,
  OrganizationParseRequest,
  PaginatedResponse,
  ParseRun,
  Review,
} from '@/types/api'

export async function getOrganization(organizationId: number): Promise<Organization> {
  const response = await apiClient.get<ApiResponse<Organization>>(
    '/api/organizations/' + organizationId,
  )

  return response.data.data
}

export async function connectOrganization(url: string): Promise<OrganizationParseRequest> {
  const response = await apiClient.post<ApiResponse<OrganizationParseRequest>>(
    '/api/organizations',
    { url },
  )

  return response.data.data
}

export async function getParseRun(parseRunId: number): Promise<ParseRun> {
  const response = await apiClient.get<ApiResponse<ParseRun>>('/api/parse-runs/' + parseRunId)

  return response.data.data
}

export async function getReviews(
  organizationId: number,
  page: number,
): Promise<PaginatedResponse<Review>> {
  const response = await apiClient.get<PaginatedResponse<Review>>(
    '/api/organizations/' + organizationId + '/reviews',
    { params: { page } },
  )

  return response.data
}
