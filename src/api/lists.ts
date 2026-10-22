import { ocs } from '@/axios'
import type {
  Checklist,
  ChecklistItem,
  ItemCustomFieldValue,
  ItemDefaultsPatch,
  ItemPrice,
  ListKind,
} from './types'

// Server item endpoints page their results (indexItems caps at 200 by default,
// indexHouseItems at 1000). Page through with an explicit limit/offset until a
// short page comes back so long, category-sorted lists don't drop their tail.
const ITEMS_PAGE_SIZE = 500

async function fetchAllItems(url: string, sortBy?: string): Promise<ChecklistItem[]> {
  const all: ChecklistItem[] = []
  let offset = 0
  for (;;) {
    const resp = await ocs.get<ChecklistItem[]>(url, {
      params: { ...(sortBy ? { sortBy } : {}), limit: ITEMS_PAGE_SIZE, offset },
    })
    const page = resp.data ?? []
    all.push(...page)
    if (page.length < ITEMS_PAGE_SIZE) break
    offset += ITEMS_PAGE_SIZE
  }
  return all
}

export interface ItemInput {
  name: string
  description?: string | null
  categoryId?: number | null
  /**
   * The full set of attached label ids. On update, an empty array clears all
   * labels; omitting the field leaves them unchanged.
   */
  labelIds?: number[]
  storeIds?: number[]
  quantity?: string | null
  rrule?: string | null
  repeatFromCompletion?: boolean
  deleteOnDone?: boolean
  sortOrder?: number
  targetListId?: number
  barcode?: string | null
  /**
   * The full set of prices. On update, an empty array clears all prices;
   * omitting the field leaves them unchanged. A null store id is the store-less
   * (default) price.
   */
  prices?: ItemPrice[]
  /**
   * The full set of custom-field values. On update, an empty array clears all
   * values; omitting the field leaves them unchanged. Each entry carries the
   * field id plus the typed value.
   */
  customFields?: ItemCustomFieldValue[]
}

/** The API route each kind's lists are served from. `lists` is the checklist resource. */
const RESOURCE: Record<ListKind, string> = {
  checklist: 'lists',
  template: 'templates',
}

/**
 * The list and item calls every kind shares, against that kind's resource. A
 * list of another kind reads as missing (404).
 */
export function listApi(kind: ListKind) {
  const base = (houseId: number) => `/houses/${houseId}/${RESOURCE[kind]}`

  return {
    async listLists(houseId: number, sortBy?: string): Promise<Checklist[]> {
      const resp = await ocs.get<Checklist[]>(base(houseId), {
        params: sortBy ? { sortBy } : undefined,
      })
      return resp.data ?? []
    },

    async createList(
      houseId: number,
      name: string,
      description?: string | null,
      icon?: string | null,
      color?: string | null,
    ): Promise<Checklist> {
      const resp = await ocs.post<Checklist>(base(houseId), {
        name,
        description: description ?? null,
        icon: icon ?? null,
        color: color ?? null,
      })
      return resp.data
    },

    async getList(houseId: number, listId: number): Promise<Checklist> {
      const resp = await ocs.get<Checklist>(`${base(houseId)}/${listId}`)
      return resp.data
    },

    async updateList(
      houseId: number,
      listId: number,
      patch: {
        name?: string
        description?: string | null
        icon?: string
        color?: string | null
        sortOrder?: number
      },
    ): Promise<Checklist> {
      const resp = await ocs.patch<Checklist>(`${base(houseId)}/${listId}`, patch)
      return resp.data
    },

    /** Merge a patch into the list's item defaults, key by key (custom fields by field id). */
    async updateItemDefaults(
      houseId: number,
      listId: number,
      patch: ItemDefaultsPatch,
    ): Promise<Checklist> {
      const resp = await ocs.patch<Checklist>(`${base(houseId)}/${listId}/item-defaults`, patch)
      return resp.data
    },

    async deleteList(houseId: number, listId: number): Promise<void> {
      await ocs.delete(`${base(houseId)}/${listId}`)
    },

    async listDeletedLists(houseId: number): Promise<Checklist[]> {
      const resp = await ocs.get<Checklist[]>(`${base(houseId)}/trash`)
      return resp.data ?? []
    },

    async restoreList(houseId: number, listId: number): Promise<Checklist> {
      const resp = await ocs.post<Checklist>(`${base(houseId)}/${listId}/restore`)
      return resp.data
    },

    async permanentlyDeleteList(houseId: number, listId: number): Promise<void> {
      await ocs.delete(`${base(houseId)}/${listId}/permanent`)
    },

    async emptyListsTrash(houseId: number): Promise<void> {
      await ocs.delete(`${base(houseId)}/trash`)
    },

    async listArchivedLists(houseId: number): Promise<Checklist[]> {
      const resp = await ocs.get<Checklist[]>(`${base(houseId)}/archive`)
      return resp.data ?? []
    },

    async archiveList(houseId: number, listId: number): Promise<Checklist> {
      const resp = await ocs.post<Checklist>(`${base(houseId)}/${listId}/archive`)
      return resp.data
    },

    async unarchiveList(houseId: number, listId: number): Promise<Checklist> {
      const resp = await ocs.post<Checklist>(`${base(houseId)}/${listId}/unarchive`)
      return resp.data
    },

    /** `resetDone` only means something for checklists; templates have no done state. */
    async duplicateList(
      houseId: number,
      listId: number,
      name: string,
      resetDone: boolean,
    ): Promise<Checklist> {
      const resp = await ocs.post<Checklist>(`${base(houseId)}/${listId}/duplicate`, {
        name,
        ...(kind === 'checklist' ? { resetDone } : {}),
      })
      return resp.data
    },

    async listItems(houseId: number, listId: number, sortBy?: string): Promise<ChecklistItem[]> {
      return fetchAllItems(`${base(houseId)}/${listId}/items`, sortBy)
    },

    async listDeletedItems(houseId: number, listId: number): Promise<ChecklistItem[]> {
      const resp = await ocs.get<ChecklistItem[]>(`${base(houseId)}/${listId}/items/trash`)
      return resp.data ?? []
    },

    async listArchivedItems(houseId: number, listId: number): Promise<ChecklistItem[]> {
      const resp = await ocs.get<ChecklistItem[]>(`${base(houseId)}/${listId}/items/archive`)
      return resp.data ?? []
    },

    async addItem(houseId: number, listId: number, input: ItemInput): Promise<ChecklistItem> {
      const resp = await ocs.post<ChecklistItem>(`${base(houseId)}/${listId}/items`, input)
      return resp.data
    },

    async updateItem(
      houseId: number,
      listId: number,
      itemId: number,
      patch: Partial<ItemInput>,
    ): Promise<ChecklistItem> {
      const resp = await ocs.patch<ChecklistItem>(
        `${base(houseId)}/${listId}/items/${itemId}`,
        patch,
      )
      return resp.data
    },

    async copyItem(
      houseId: number,
      listId: number,
      itemId: number,
      targetListId: number,
    ): Promise<ChecklistItem> {
      const resp = await ocs.post<ChecklistItem>(
        `${base(houseId)}/${listId}/items/${itemId}/copy`,
        { targetListId },
      )
      return resp.data
    },

    async deleteItem(houseId: number, listId: number, itemId: number): Promise<void> {
      await ocs.delete(`${base(houseId)}/${listId}/items/${itemId}`)
    },

    async permanentlyDeleteItem(houseId: number, listId: number, itemId: number): Promise<void> {
      await ocs.delete(`${base(houseId)}/${listId}/items/${itemId}/permanent`)
    },

    async restoreItem(houseId: number, listId: number, itemId: number): Promise<ChecklistItem> {
      const resp = await ocs.post<ChecklistItem>(
        `${base(houseId)}/${listId}/items/${itemId}/restore`,
      )
      return resp.data
    },

    async emptyTrash(houseId: number, listId: number): Promise<void> {
      await ocs.delete(`${base(houseId)}/${listId}/items/trash`)
    },

    async archiveItem(houseId: number, listId: number, itemId: number): Promise<ChecklistItem> {
      const resp = await ocs.post<ChecklistItem>(
        `${base(houseId)}/${listId}/items/${itemId}/archive`,
      )
      return resp.data
    },

    async unarchiveItem(houseId: number, listId: number, itemId: number): Promise<ChecklistItem> {
      const resp = await ocs.post<ChecklistItem>(
        `${base(houseId)}/${listId}/items/${itemId}/unarchive`,
      )
      return resp.data
    },

    async reorderItems(
      houseId: number,
      listId: number,
      items: { id: number; sortOrder: number }[],
    ): Promise<void> {
      await ocs.post(`${base(houseId)}/${listId}/items/reorder`, { items })
    },

    async uploadItemImage(
      houseId: number,
      listId: number,
      itemId: number,
      file: File,
    ): Promise<ChecklistItem> {
      const form = new FormData()
      form.append('image', file, file.name)
      const resp = await ocs.post<ChecklistItem>(
        `${base(houseId)}/${listId}/items/${itemId}/image`,
        form,
      )
      return resp.data
    },

    async clearItemImage(houseId: number, listId: number, itemId: number): Promise<ChecklistItem> {
      const resp = await ocs.delete<ChecklistItem>(
        `${base(houseId)}/${listId}/items/${itemId}/image`,
      )
      return resp.data
    },
  }
}

export type ListApi = ReturnType<typeof listApi>

export const {
  listLists,
  createList,
  getList,
  updateList,
  updateItemDefaults,
  deleteList,
  listDeletedLists,
  restoreList,
  permanentlyDeleteList,
  emptyListsTrash,
  listArchivedLists,
  archiveList,
  unarchiveList,
  duplicateList,
  listItems,
  listDeletedItems,
  listArchivedItems,
  addItem,
  updateItem,
  copyItem,
  deleteItem,
  permanentlyDeleteItem,
  restoreItem,
  emptyTrash,
  archiveItem,
  unarchiveItem,
  reorderItems,
  uploadItemImage,
  clearItemImage,
} = listApi('checklist')

/** Lists of every kind share one sort order, so ids of any kind can be reordered together. */
export async function reorderLists(
  houseId: number,
  items: { id: number; sortOrder: number }[],
): Promise<void> {
  await ocs.post(`/houses/${houseId}/lists/reorder`, { items })
}

export async function listAllItems(houseId: number, sortBy?: string): Promise<ChecklistItem[]> {
  return fetchAllItems(`/houses/${houseId}/items`, sortBy)
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

// ----- Batch (group) actions -----

export interface BatchResult {
  success: boolean
  // Processed items: updated (move/category) or created (copy). Empty for delete.
  items: ChecklistItem[]
  // Ids dropped because the caller could not reach the item's source list.
  skipped: number[]
}

export async function batchMoveItems(
  houseId: number,
  itemIds: number[],
  targetListId: number,
): Promise<BatchResult> {
  const resp = await ocs.post<BatchResult>(`/houses/${houseId}/items/batch/move`, {
    itemIds,
    targetListId,
  })
  return resp.data
}

export async function batchCopyItems(
  houseId: number,
  itemIds: number[],
  targetListId: number,
): Promise<BatchResult> {
  const resp = await ocs.post<BatchResult>(`/houses/${houseId}/items/batch/copy`, {
    itemIds,
    targetListId,
  })
  return resp.data
}

export async function batchDeleteItems(
  houseId: number,
  itemIds: number[],
  permanent = false,
): Promise<BatchResult> {
  const resp = await ocs.post<BatchResult>(`/houses/${houseId}/items/batch/delete`, {
    itemIds,
    permanent,
  })
  return resp.data
}

export async function batchArchiveItems(
  houseId: number,
  itemIds: number[],
  archive = true,
): Promise<BatchResult> {
  const resp = await ocs.post<BatchResult>(`/houses/${houseId}/items/batch/archive`, {
    itemIds,
    archive,
  })
  return resp.data
}

export async function batchSetCategory(
  houseId: number,
  itemIds: number[],
  categoryId: number | null,
): Promise<BatchResult> {
  const resp = await ocs.post<BatchResult>(`/houses/${houseId}/items/batch/category`, {
    itemIds,
    categoryId,
  })
  return resp.data
}

export async function batchSetStores(
  houseId: number,
  itemIds: number[],
  storeIds: number[],
): Promise<BatchResult> {
  const resp = await ocs.post<BatchResult>(`/houses/${houseId}/items/batch/stores`, {
    itemIds,
    storeIds,
  })
  return resp.data
}

export async function batchSetLabels(
  houseId: number,
  itemIds: number[],
  labelIds: number[],
): Promise<BatchResult> {
  const resp = await ocs.post<BatchResult>(`/houses/${houseId}/items/batch/labels`, {
    itemIds,
    labelIds,
  })
  return resp.data
}

export async function batchUncheckItems(houseId: number, itemIds: number[]): Promise<BatchResult> {
  const resp = await ocs.post<BatchResult>(`/houses/${houseId}/items/batch/uncheck`, {
    itemIds,
  })
  return resp.data
}
