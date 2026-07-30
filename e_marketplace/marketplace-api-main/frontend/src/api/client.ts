import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'
import type { ApiErrorBody } from '@/types'
import { clearAuthToken, getAuthToken } from '@/utils/storage'

const baseURL = import.meta.env.VITE_API_URL

if (!baseURL) {
  throw new Error('VITE_API_URL is not defined')
}

export const apiClient = axios.create({
  baseURL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

apiClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = getAuthToken()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiErrorBody>) => {
    if (error.response?.status === 401) {
      clearAuthToken()
      window.dispatchEvent(new CustomEvent('auth:unauthorized'))
    }

    return Promise.reject(error)
  },
)

export function isApiError(error: unknown): error is AxiosError<ApiErrorBody> {
  return axios.isAxiosError(error)
}

export function getErrorMessage(error: unknown, fallback = 'Something went wrong.'): string {
  if (isApiError(error)) {
    return error.response?.data?.message ?? fallback
  }

  if (error instanceof Error) {
    return error.message
  }

  return fallback
}

export function getValidationErrors(error: unknown): Record<string, string[]> {
  if (isApiError(error) && error.response?.data?.errors) {
    return error.response.data.errors
  }

  return {}
}

export function getFirstFieldError(
  errors: Record<string, string[]>,
  field: string,
): string | undefined {
  return errors[field]?.[0]
}
