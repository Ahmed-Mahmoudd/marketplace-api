import { apiClient } from './client'
import type { ApiResponse, AuthPayload, User } from '@/types'

export interface LoginInput {
  email: string
  password: string
}

export interface RegisterInput {
  name: string
  email: string
  password: string
  password_confirmation: string
  gender?: string
  city?: string
}

export async function login(input: LoginInput): Promise<AuthPayload> {
  const { data } = await apiClient.post<ApiResponse<AuthPayload>>('/auth/login', input)
  return data.data
}

export async function register(input: RegisterInput): Promise<AuthPayload> {
  const { data } = await apiClient.post<ApiResponse<AuthPayload>>('/auth/register', input)
  return data.data
}

export async function logout(): Promise<void> {
  await apiClient.post('/auth/logout')
}

export async function getCurrentUser(): Promise<User> {
  const { data } = await apiClient.get<ApiResponse<User>>('/auth/me')
  return data.data
}

export interface HandoffPayload {
  code: string
  expires_in: number
}

/**
 * Mints a short-lived, single-use code for signing into the analytics
 * dashboard without a second login. Deliberately not the bearer token — the
 * code goes in a URL, and a token there would leak into history and logs.
 */
export async function createHandoffCode(): Promise<HandoffPayload> {
  const { data } = await apiClient.post<ApiResponse<HandoffPayload>>('/auth/handoff')
  return data.data
}
