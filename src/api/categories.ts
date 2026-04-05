import { ocs } from '@/axios'
import type { Category } from './types'

export async function listCategories(houseId: number): Promise<Category[]> {
  const resp = await ocs.get<Category[]>(`/houses/${houseId}/categories`)
  return resp.data ?? []
}

export async function createCategory(
  houseId: number,
  input: { name: string; icon: string; color: string },
): Promise<Category> {
  const resp = await ocs.post<Category>(`/houses/${houseId}/categories`, input)
  return resp.data
}

export async function updateCategory(
  houseId: number,
  categoryId: number,
  patch: { name?: string; icon?: string; color?: string; sortOrder?: number },
): Promise<Category> {
  const resp = await ocs.patch<Category>(`/houses/${houseId}/categories/${categoryId}`, patch)
  return resp.data
}

export async function deleteCategory(houseId: number, categoryId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/categories/${categoryId}`)
}
