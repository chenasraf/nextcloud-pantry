import { ocs } from '@/axios'
import type { Label } from './types'

export async function listLabels(houseId: number): Promise<Label[]> {
  const resp = await ocs.get<Label[]>(`/houses/${houseId}/labels`)
  return resp.data ?? []
}

export async function createLabel(
  houseId: number,
  input: { name: string; icon: string; color: string; listId?: number | null },
): Promise<Label> {
  const resp = await ocs.post<Label>(`/houses/${houseId}/labels`, input)
  return resp.data
}

export async function updateLabel(
  houseId: number,
  labelId: number,
  patch: {
    name?: string
    icon?: string
    color?: string
    sortOrder?: number
    listId?: number | null
  },
): Promise<Label> {
  const resp = await ocs.patch<Label>(`/houses/${houseId}/labels/${labelId}`, patch)
  return resp.data
}

export async function deleteLabel(houseId: number, labelId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/labels/${labelId}`)
}

export async function reorderLabels(
  houseId: number,
  items: { id: number; sortOrder: number }[],
): Promise<void> {
  await ocs.post(`/houses/${houseId}/labels/reorder`, { items })
}
