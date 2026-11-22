import { ocs } from '@/axios'

export interface ItemCategoryMemory {
  nameKey: string
  categoryId: number
  uses: number
  rejections: number
  updatedAt: number
}

export interface ItemQuantityMemory {
  listId: number
  nameKey: string
  /** Null when the amount was forgotten since the previous sync. */
  quantity: string | null
  updatedAt: number
}

export interface ItemMemory {
  categories: ItemCategoryMemory[]
  quantities: ItemQuantityMemory[]
  /** Pass back as `since` to fetch only what changed. */
  serverTime: number
}

/** Suggestions the person dropped before saving an item, sent along with it. */
export interface SuggestionRejected {
  categoryId?: number | null
  quantity?: boolean
}

export async function getItemMemory(houseId: number, since?: number): Promise<ItemMemory> {
  const resp = await ocs.get<ItemMemory>(`/houses/${houseId}/item-memory`, {
    params: since === undefined ? {} : { since },
  })
  return resp.data
}
