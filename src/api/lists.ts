import { ocs } from '@/axios'
import type { ShoppingList, ShoppingListItem } from './types'

export async function listLists(houseId: number): Promise<ShoppingList[]> {
  const resp = await ocs.get<ShoppingList[]>(`/houses/${houseId}/lists`)
  return resp.data ?? []
}

export async function createList(
  houseId: number,
  name: string,
  description?: string | null,
): Promise<ShoppingList> {
  const resp = await ocs.post<ShoppingList>(`/houses/${houseId}/lists`, {
    name,
    description: description ?? null,
  })
  return resp.data
}

export async function getList(houseId: number, listId: number): Promise<ShoppingList> {
  const resp = await ocs.get<ShoppingList>(`/houses/${houseId}/lists/${listId}`)
  return resp.data
}

export async function updateList(
  houseId: number,
  listId: number,
  patch: { name?: string; description?: string | null; sortOrder?: number },
): Promise<ShoppingList> {
  const resp = await ocs.patch<ShoppingList>(`/houses/${houseId}/lists/${listId}`, patch)
  return resp.data
}

export async function deleteList(houseId: number, listId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/lists/${listId}`)
}

export async function listItems(houseId: number, listId: number): Promise<ShoppingListItem[]> {
  const resp = await ocs.get<ShoppingListItem[]>(`/houses/${houseId}/lists/${listId}/items`)
  return resp.data ?? []
}

export interface ItemInput {
  name: string
  categoryId?: number | null
  quantity?: string | null
  rrule?: string | null
  repeatFromCompletion?: boolean
  sortOrder?: number
}

export async function addItem(
  houseId: number,
  listId: number,
  input: ItemInput,
): Promise<ShoppingListItem> {
  const resp = await ocs.post<ShoppingListItem>(`/houses/${houseId}/lists/${listId}/items`, input)
  return resp.data
}

export async function updateItem(
  houseId: number,
  listId: number,
  itemId: number,
  patch: Partial<ItemInput>,
): Promise<ShoppingListItem> {
  const resp = await ocs.patch<ShoppingListItem>(
    `/houses/${houseId}/lists/${listId}/items/${itemId}`,
    patch,
  )
  return resp.data
}

export async function toggleItem(
  houseId: number,
  listId: number,
  itemId: number,
): Promise<ShoppingListItem> {
  const resp = await ocs.post<ShoppingListItem>(
    `/houses/${houseId}/lists/${listId}/items/${itemId}/toggle`,
  )
  return resp.data
}

export async function deleteItem(houseId: number, listId: number, itemId: number): Promise<void> {
  await ocs.delete(`/houses/${houseId}/lists/${listId}/items/${itemId}`)
}
