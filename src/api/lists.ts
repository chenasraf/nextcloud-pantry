import { ocs } from '@/axios'
import type { Checklist, ChecklistItem } from './types'

export async function listLists(houseId: number): Promise<Checklist[]> {
  const resp = await ocs.get<Checklist[]>(`/houses/${houseId}/lists`)
  return resp.data ?? []
}

export async function createList(
  houseId: number,
  name: string,
  description?: string | null,
  icon?: string | null,
): Promise<Checklist> {
  const resp = await ocs.post<Checklist>(`/houses/${houseId}/lists`, {
    name,
    description: description ?? null,
    icon: icon ?? null,
  })
  return resp.data
}

export async function getList(houseId: number, listId: number): Promise<Checklist> {
  const resp = await ocs.get<Checklist>(`/houses/${houseId}/lists/${listId}`)
  return resp.data
}

export async function updateList(
  houseId: number,
  listId: number,
  patch: { name?: string; description?: string | null; icon?: string; sortOrder?: number },
): Promise<Checklist> {
  const resp = await ocs.patch<Checklist>(`/houses/${houseId}/lists/${listId}`, patch)
  return resp.data
}

export async function deleteList(houseId: number, listId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/lists/${listId}`)
}

export async function listItems(
  houseId: number,
  listId: number,
  sortBy?: string,
): Promise<ChecklistItem[]> {
  const resp = await ocs.get<ChecklistItem[]>(`/houses/${houseId}/lists/${listId}/items`, {
    params: sortBy ? { sortBy } : undefined,
  })
  return resp.data ?? []
}

export interface ItemInput {
  name: string
  description?: string | null
  categoryId?: number | null
  quantity?: string | null
  rrule?: string | null
  repeatFromCompletion?: boolean
  deleteOnDone?: boolean
  sortOrder?: number
  targetListId?: number
}

export async function addItem(
  houseId: number,
  listId: number,
  input: ItemInput,
): Promise<ChecklistItem> {
  const resp = await ocs.post<ChecklistItem>(`/houses/${houseId}/lists/${listId}/items`, input)
  return resp.data
}

export async function updateItem(
  houseId: number,
  listId: number,
  itemId: number,
  patch: Partial<ItemInput>,
): Promise<ChecklistItem> {
  const resp = await ocs.patch<ChecklistItem>(
    `/houses/${houseId}/lists/${listId}/items/${itemId}`,
    patch,
  )
  return resp.data
}

export async function toggleItem(
  houseId: number,
  listId: number,
  itemId: number,
): Promise<ChecklistItem> {
  const resp = await ocs.post<ChecklistItem>(
    `/houses/${houseId}/lists/${listId}/items/${itemId}/toggle`,
  )
  return resp.data
}

export async function deleteItem(houseId: number, listId: number, itemId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/lists/${listId}/items/${itemId}`)
}

export async function reorderItems(
  houseId: number,
  listId: number,
  items: { id: number; sortOrder: number }[],
): Promise<void> {
  await ocs.post(`/houses/${houseId}/lists/${listId}/items/reorder`, { items })
}

export async function uploadItemImage(
  houseId: number,
  listId: number,
  itemId: number,
  file: File,
): Promise<ChecklistItem> {
  const form = new FormData()
  form.append('image', file, file.name)
  const resp = await ocs.post<ChecklistItem>(
    `/houses/${houseId}/lists/${listId}/items/${itemId}/image`,
    form,
  )
  return resp.data
}

export async function clearItemImage(
  houseId: number,
  listId: number,
  itemId: number,
): Promise<ChecklistItem> {
  const resp = await ocs.delete<ChecklistItem>(
    `/houses/${houseId}/lists/${listId}/items/${itemId}/image`,
  )
  return resp.data
}
