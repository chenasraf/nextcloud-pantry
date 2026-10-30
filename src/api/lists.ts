import { ocs } from '@/axios'
import type {
  AttachedTemplates,
  Checklist,
  ChecklistItem,
  ItemCustomFieldValue,
  ItemDefaultsPatch,
  ItemPrice,
  ListKind,
  ListSuggestions,
  StockLevel,
  StockLinkRow,
  StockMode,
  TemplateLists,
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
  /**
   * Inventory and template items. An empty target or reorder point clears it;
   * on a template an empty mode clears it too, leaving it to the inventory the
   * item is drawn into. Templates have no stock level.
   */
  stockMode?: StockMode | ''
  stockLevel?: StockLevel
  target?: string
  reorderPoint?: string
  /**
   * Inventory items: the expiry date, a midnight timestamp in seconds (0
   * clears). Without a shelf life alongside, a date after today also sets the
   * shelf life to match.
   */
  expiresAt?: number
  /**
   * Inventory and template items: the shelf life in days (0 clears). Without
   * a date alongside, an inventory item then expires that many days from today.
   */
  shelfLifeDays?: number
  /** Inventory items: whether an unlinked item is linked by name to a row on the restock checklist. */
  autoLink?: boolean
  /** Checklist items: false lets stock set a quantity a member set by hand again. */
  quantityPinned?: boolean
}

/** The API route each kind's lists are served from. `lists` is the checklist resource. */
const RESOURCE: Record<ListKind, string> = {
  checklist: 'lists',
  template: 'templates',
  inventory: 'inventories',
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
        /** Inventories only. */
        expiryLeadDays?: number
        /** Inventories only: the checklist low stock goes to (0 clears). */
        restockChecklistId?: number
        /** Inventories only: whether members get a daily stock summary. */
        stockSummary?: boolean
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

    /**
     * Duplicate a list of any kind as a list of this kind. `resetDone` only
     * means something checklist to checklist.
     */
    async duplicateAs(
      houseId: number,
      sourceListId: number,
      name: string,
      resetDone = true,
    ): Promise<Checklist> {
      const resp = await ocs.post<Checklist>(`${base(houseId)}/from/${sourceListId}`, {
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

    /** Checklists and inventories only: copy a template item onto the list. */
    async drawItem(houseId: number, listId: number, templateItemId: number): Promise<DrawResult> {
      const resp = await ocs.post<DrawResult>(`${base(houseId)}/${listId}/items/draw`, {
        templateItemId,
      })
      return resp.data
    },

    /** Checklists and inventories only: what their add box suggests. */
    async listSuggestions(
      houseId: number,
      listId: number,
      archived: boolean,
    ): Promise<ListSuggestions> {
      const resp = await ocs.get<ListSuggestions>(`${base(houseId)}/${listId}/suggestions`, {
        params: { archived },
      })
      return resp.data
    },

    /** Checklists and inventories only: the templates attached to the list. */
    async getAttachedTemplates(houseId: number, listId: number): Promise<AttachedTemplates> {
      const resp = await ocs.get<AttachedTemplates>(`${base(houseId)}/${listId}/templates`)
      return resp.data
    },

    async setAttachedTemplates(
      houseId: number,
      listId: number,
      templateIds: number[],
    ): Promise<AttachedTemplates> {
      const resp = await ocs.put<AttachedTemplates>(`${base(houseId)}/${listId}/templates`, {
        templateIds,
      })
      return resp.data
    },

    /** Templates only: the lists the template is attached to. */
    async getTemplateLists(houseId: number, listId: number): Promise<TemplateLists> {
      const resp = await ocs.get<TemplateLists>(`${base(houseId)}/${listId}/lists`)
      return resp.data
    },

    async setTemplateLists(
      houseId: number,
      listId: number,
      value: TemplateLists,
    ): Promise<TemplateLists> {
      const resp = await ocs.put<TemplateLists>(`${base(houseId)}/${listId}/lists`, value)
      return resp.data
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

const inventoryItemUrl = (houseId: number, listId: number, itemId: number) =>
  `/houses/${houseId}/inventories/${listId}/items/${itemId}`

/** Link an inventory item to the checklist item that restocks it. */
export async function linkInventoryItem(
  houseId: number,
  listId: number,
  itemId: number,
  targetItemId: number,
): Promise<ChecklistItem> {
  const resp = await ocs.post<ChecklistItem>(`${inventoryItemUrl(houseId, listId, itemId)}/link`, {
    targetItemId,
  })
  return resp.data
}

/** Unlink an inventory item; this also turns its auto-link off. */
export async function unlinkInventoryItem(
  houseId: number,
  listId: number,
  itemId: number,
): Promise<ChecklistItem> {
  const resp = await ocs.delete<ChecklistItem>(`${inventoryItemUrl(houseId, listId, itemId)}/link`)
  return resp.data
}

/** Checklist items the inventory's items can be linked to by hand. */
export async function listLinkTargets(
  houseId: number,
  listId: number,
  search = '',
): Promise<StockLinkRow[]> {
  const resp = await ocs.get<StockLinkRow[]>(
    `/houses/${houseId}/inventories/${listId}/link-targets`,
    {
      params: { search, limit: 50 },
    },
  )
  return resp.data ?? []
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

/** A drawn template item: the copy, or the same-name item it brought back. */
export interface DrawResult {
  item: ChecklistItem
  reused: boolean
}

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

/** Template items copied onto a checklist or inventory may revive a same-name item there. */
export interface BatchCopyResult extends BatchResult {
  // Ids among `items` that were already on the list and brought back, not added.
  reused: number[]
}

export async function batchCopyItems(
  houseId: number,
  itemIds: number[],
  targetListId: number,
): Promise<BatchCopyResult> {
  const resp = await ocs.post<BatchCopyResult>(`/houses/${houseId}/items/batch/copy`, {
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
