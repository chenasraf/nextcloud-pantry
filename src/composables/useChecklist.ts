import { computed, ref, type Ref } from 'vue'
import * as api from '@/api/lists'
import type { Checklist, ChecklistItem, ListKind } from '@/api/types'
import type { ChecklistItemSort, ChecklistSort } from '@/api/prefs'
import type { ChecklistFormData } from '@/components/ChecklistIconPicker/checklistForm'
import {
  DEFAULT_EXPIRY_LEAD_DAYS,
  evaluateStock,
  expiryInput,
  expiryStatus,
  isEmpty,
  renewedExpiry,
  renewOffer,
  stockInput,
  todayEpoch,
} from '@/utils/stock'
import { announceRestock, announceRestockSync } from '@/components/StockLink/restockToasts'

/** An item a write returned, after telling the member what restocking did because of it. */
function received(item: ChecklistItem): ChecklistItem {
  announceRestockSync(item.restockSync)
  return item
}

// Per-house, per-kind state shared across all callers so sidebar and views stay in sync.
interface HouseChecklistState {
  lists: Ref<Checklist[]>
  deletedLists: Ref<Checklist[]>
  archivedLists: Ref<Checklist[]>
  loading: Ref<boolean>
  error: Ref<string | null>
  sortBy: Ref<ChecklistSort>
  viewMode: Ref<'active' | 'trash' | 'archive'>
  inflight: Promise<Checklist[]> | null
}
const houseStates = new Map<string, HouseChecklistState>()

function getState(houseId: number, kind: ListKind): HouseChecklistState {
  const key = `${kind}:${houseId}`
  let s = houseStates.get(key)
  if (!s) {
    s = {
      lists: ref<Checklist[]>([]),
      deletedLists: ref<Checklist[]>([]),
      archivedLists: ref<Checklist[]>([]),
      loading: ref(false),
      error: ref<string | null>(null),
      sortBy: ref<ChecklistSort>('custom'),
      viewMode: ref<'active' | 'trash' | 'archive'>('active'),
      inflight: null,
    }
    houseStates.set(key, s)
  }
  return s
}

export function useChecklists(houseId: number, kind: ListKind = 'checklist') {
  const state = getState(houseId, kind)
  const resource = api.listApi(kind)
  const { lists, deletedLists, archivedLists, loading, error, sortBy, viewMode } = state
  const trashMode = computed(() => viewMode.value === 'trash')
  const archiveMode = computed(() => viewMode.value === 'archive')

  function load(sortOrForce?: ChecklistSort | boolean): Promise<Checklist[]> {
    const force = sortOrForce === true
    const sort = typeof sortOrForce === 'string' ? sortOrForce : sortBy.value
    if (state.inflight && !force) return state.inflight
    loading.value = true
    error.value = null
    state.inflight = resource
      .listLists(houseId, sort)
      .then((result) => {
        lists.value = result
        return result
      })
      .catch((e) => {
        error.value = (e as Error).message
        return lists.value
      })
      .finally(() => {
        loading.value = false
        state.inflight = null
      })
    return state.inflight
  }

  async function loadDeleted(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      deletedLists.value = await resource.listDeletedLists(houseId)
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  async function loadArchived(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      archivedLists.value = await resource.listArchivedLists(houseId)
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  async function create(
    name: string,
    description?: string | null,
    icon?: string | null,
    color?: string | null,
    attachments?: ChecklistFormData['attachments'],
  ): Promise<Checklist> {
    const created = await resource.createList(houseId, name, description, icon, color)
    lists.value = [...lists.value, created]
    if (attachments && 'templateLists' in attachments) {
      const { everyList, listIds } = attachments.templateLists
      if (everyList || listIds.length > 0) {
        await resource.setTemplateLists(houseId, created.id, attachments.templateLists)
      }
    } else if (attachments && attachments.templateIds.length > 0) {
      await resource.setAttachedTemplates(houseId, created.id, attachments.templateIds)
    }
    return created
  }

  /** Swap in a list the server returned from an update made elsewhere. */
  function replace(updated: Checklist): void {
    lists.value = lists.value.map((l) => (l.id === updated.id ? updated : l))
  }

  async function update(
    listId: number,
    patch: {
      name?: string
      description?: string | null
      icon?: string
      color?: string | null
    },
  ): Promise<void> {
    const updated = await resource.updateList(houseId, listId, patch)
    lists.value = lists.value.map((l) => (l.id === listId ? updated : l))
  }

  async function remove(listId: number): Promise<void> {
    await resource.deleteList(houseId, listId)
    lists.value = lists.value.filter((l) => l.id !== listId)
  }

  async function restore(listId: number): Promise<void> {
    const restored = await resource.restoreList(houseId, listId)
    deletedLists.value = deletedLists.value.filter((l) => l.id !== listId)
    // Append so it shows up immediately if the user toggles back to the active view.
    lists.value = [...lists.value, restored]
  }

  async function removePermanently(listId: number): Promise<void> {
    await resource.permanentlyDeleteList(houseId, listId)
    deletedLists.value = deletedLists.value.filter((l) => l.id !== listId)
    lists.value = lists.value.filter((l) => l.id !== listId)
  }

  async function emptyTrash(): Promise<void> {
    await resource.emptyListsTrash(houseId)
    deletedLists.value = []
  }

  async function archive(listId: number): Promise<void> {
    const archived = await resource.archiveList(houseId, listId)
    lists.value = lists.value.filter((l) => l.id !== listId)
    // Prepend so it leads the archive view (most recently archived first).
    archivedLists.value = [archived, ...archivedLists.value]
  }

  async function unarchive(listId: number): Promise<void> {
    const restored = await resource.unarchiveList(houseId, listId)
    archivedLists.value = archivedLists.value.filter((l) => l.id !== listId)
    // Append so it shows up immediately if the user toggles back to the active view.
    lists.value = [...lists.value, restored]
  }

  async function duplicate(listId: number, name: string, resetDone: boolean): Promise<Checklist> {
    const created = await resource.duplicateList(houseId, listId, name, resetDone)
    lists.value = [...lists.value, created]
    return created
  }

  /** Duplicate a list of any kind as a list of this kind. */
  async function duplicateFrom(
    sourceListId: number,
    name: string,
    resetDone = true,
  ): Promise<Checklist> {
    const created = await resource.duplicateAs(houseId, sourceListId, name, resetDone)
    lists.value = [...lists.value, created]
    return created
  }

  async function reorder(items: { id: number; sortOrder: number }[]): Promise<void> {
    // Apply optimistically so there's no visual jump while the API call is in flight.
    const map = new Map(items.map((i) => [i.id, i.sortOrder]))
    lists.value = lists.value
      .map((l) => (map.has(l.id) ? { ...l, sortOrder: map.get(l.id)! } : l))
      .sort((a, b) => a.sortOrder - b.sortOrder)
    await api.reorderLists(houseId, items)
  }

  return {
    lists,
    deletedLists,
    archivedLists,
    loading,
    error,
    sortBy,
    viewMode,
    trashMode,
    archiveMode,
    load,
    loadDeleted,
    loadArchived,
    create,
    update,
    replace,
    remove,
    restore,
    removePermanently,
    emptyTrash,
    archive,
    unarchive,
    duplicate,
    duplicateFrom,
    reorder,
  }
}

/**
 * Active lists of every kind, in their shared sort order. For pickers where any
 * kind can appear, such as category, label and custom-field scoping.
 */
export function useAllKindLists(houseId: number) {
  const checklists = useChecklists(houseId, 'checklist')
  const templates = useChecklists(houseId, 'template')
  const inventories = useChecklists(houseId, 'inventory')
  const lists = computed(() =>
    [...checklists.lists.value, ...inventories.lists.value, ...templates.lists.value].sort(
      (a, b) => a.sortOrder - b.sortOrder || a.name.localeCompare(b.name),
    ),
  )

  async function load(): Promise<Checklist[]> {
    await Promise.all([checklists.load(), inventories.load(), templates.load()])
    return lists.value
  }

  return { lists, load }
}

// Sentinel for the "All lists" meta view. Items are aggregated from every
// list in the house; per-item operations resolve to the item's real listId.
export const ALL_LISTS_ID = 0

export function useChecklistItems(houseId: number, listId: number, kind: ListKind = 'checklist') {
  const resource = api.listApi(kind)
  const items = ref<ChecklistItem[]>([])
  const loading = ref(true)
  const error = ref<string | null>(null)
  const sortBy = ref<ChecklistItemSort>('custom')
  const viewMode = ref<'active' | 'trash' | 'archive'>('active')
  const trashMode = computed(() => viewMode.value === 'trash')
  const archiveMode = computed(() => viewMode.value === 'archive')

  const isMeta = listId === ALL_LISTS_ID

  function listIdFor(itemId: number): number {
    if (!isMeta) return listId
    const found = items.value.find((i) => i.id === itemId)
    if (!found) {
      throw new Error(`Item ${itemId} not found in current view`)
    }
    return found.listId
  }

  async function load(sort?: ChecklistItemSort, options?: { silent?: boolean }): Promise<void> {
    const silent = options?.silent ?? false
    if (!silent) {
      loading.value = true
      error.value = null
    }
    const s = sort ?? sortBy.value
    try {
      if (isMeta) {
        items.value = await api.listAllItems(houseId, s)
      } else if (archiveMode.value) {
        items.value = await resource.listArchivedItems(houseId, listId)
      } else if (trashMode.value) {
        items.value = await resource.listDeletedItems(houseId, listId)
      } else {
        items.value = await resource.listItems(houseId, listId, s)
      }
    } catch (e) {
      if (!silent) error.value = (e as Error).message
    } finally {
      if (!silent) loading.value = false
    }
  }

  async function add(input: api.ItemInput, targetListId?: number): Promise<ChecklistItem> {
    const useListId = isMeta ? targetListId : listId
    if (useListId === undefined || useListId <= 0) {
      throw new Error('A target list is required to add an item')
    }
    const created = received(await resource.addItem(houseId, useListId, input))
    items.value = [...items.value, created]
    return created
  }

  /**
   * Draw a template item onto the list, or revive the same-name item already
   * on it. A revived item that was archived joins the view.
   */
  async function draw(templateItemId: number, targetListId?: number): Promise<api.DrawResult> {
    const useListId = isMeta ? targetListId : listId
    if (useListId === undefined || useListId <= 0) {
      throw new Error('A target list is required to add an item')
    }
    const result = await resource.drawItem(houseId, useListId, templateItemId)
    received(result.item)
    mergeItems([result.item])
    return result
  }

  async function update(itemId: number, patch: Partial<api.ItemInput>): Promise<void> {
    dropRenewOffer(itemId)
    const updated = received(await resource.updateItem(houseId, listIdFor(itemId), itemId, patch))
    if (isMeta && patch.targetListId !== undefined && patch.targetListId !== updated.listId) {
      // Should not happen — the backend canonicalizes listId via targetListId.
    }
    items.value = items.value.map((i) => (i.id === itemId ? updated : i))
  }

  /** The latest stock write per item, so a slower earlier answer cannot undo a later step. */
  const stockWrites = new Map<number, number>()

  /**
   * Inventory items whose stock a member raised by hand onto stock already
   * there, and the date that offers to renew them to, until their next edit.
   */
  const renewOffers = ref(new Map<number, number>())

  function dropRenewOffer(itemId: number): void {
    if (renewOffers.value.has(itemId)) {
      const next = new Map(renewOffers.value)
      next.delete(itemId)
      renewOffers.value = next
    }
  }

  /** Inventory items: take up the offer to renew the expiry date after a raise by hand. */
  async function renewExpiry(itemId: number): Promise<void> {
    const date = renewOffers.value.get(itemId)
    if (date === undefined) return
    await update(itemId, { expiresAt: date })
  }

  /**
   * Change an inventory item's stock from its row. The row shows the new
   * amount and status straight away; the server's answer replaces them, and a
   * failed write puts the item back as it was.
   */
  async function updateStock(
    itemId: number,
    patch: Pick<api.ItemInput, 'quantity' | 'stockLevel'>,
  ): Promise<void> {
    const prev = items.value.find((i) => i.id === itemId)
    if (!prev) return
    const write = (stockWrites.get(itemId) ?? 0) + 1
    stockWrites.set(itemId, write)
    const today = todayEpoch()
    const next = { ...prev, ...patch }
    // Stock raised from empty is new stock, so the server gives it a fresh date.
    if (isEmpty(stockInput(prev)) && !isEmpty(stockInput(next))) {
      next.expiresAt = renewedExpiry(next.shelfLifeDays ?? null, today)
    }
    const offer = renewOffer(expiryInput(prev), expiryInput(next), today)
    const leadDays =
      getState(houseId, 'inventory').lists.value.find((l) => l.id === next.listId)
        ?.expiryLeadDays ?? DEFAULT_EXPIRY_LEAD_DAYS
    const optimistic = {
      ...next,
      ...evaluateStock(stockInput(next)),
      expiryStatus: expiryStatus(expiryInput(next), leadDays, today),
    }
    items.value = items.value.map((i) => (i.id === itemId ? optimistic : i))
    dropRenewOffer(itemId)
    if (offer !== null) {
      renewOffers.value = new Map(renewOffers.value).set(itemId, offer)
    }
    try {
      const updated = await resource.updateItem(houseId, listIdFor(itemId), itemId, patch)
      announceRestockSync(updated.restockSync)
      if (stockWrites.get(itemId) === write) {
        items.value = items.value.map((i) => (i.id === itemId ? updated : i))
      }
    } catch (e) {
      if (stockWrites.get(itemId) === write) {
        items.value = items.value.map((i) => (i.id === itemId ? prev : i))
        dropRenewOffer(itemId)
      }
      throw e
    }
  }

  function replaceItem(updated: ChecklistItem): ChecklistItem {
    items.value = items.value.map((i) => (i.id === updated.id ? updated : i))
    return updated
  }

  /** Inventory items: link to the checklist item that restocks this one. */
  async function link(itemId: number, targetItemId: number): Promise<ChecklistItem> {
    return replaceItem(
      received(await api.linkInventoryItem(houseId, listIdFor(itemId), itemId, targetItemId)),
    )
  }

  /** Inventory items: drop the link and turn auto-link off. */
  async function unlink(itemId: number): Promise<ChecklistItem> {
    return replaceItem(await api.unlinkInventoryItem(houseId, listIdFor(itemId), itemId))
  }

  /** Inventory items: turn auto-link on or off. Turning it on may link the item straight away. */
  async function setAutoLink(itemId: number, autoLink: boolean): Promise<ChecklistItem> {
    return replaceItem(
      received(await resource.updateItem(houseId, listIdFor(itemId), itemId, { autoLink })),
    )
  }

  async function copy(itemId: number, targetListId: number): Promise<ChecklistItem> {
    const sourceListId = listIdFor(itemId)
    const created = received(await resource.copyItem(houseId, sourceListId, itemId, targetListId))
    if (isMeta || targetListId === listId) {
      items.value = [...items.value, created]
    }
    return created
  }

  async function toggle(itemId: number): Promise<void> {
    // Optimistic flip. "Once" items disappear when marked done — the backend
    // deletes them in the same call, so we drop them locally too.
    const prev = items.value.find((i) => i.id === itemId)
    const willDelete = !!prev && !prev.done && prev.deleteOnDone
    const useListId = listIdFor(itemId)
    if (prev) {
      if (willDelete) {
        items.value = items.value.filter((i) => i.id !== itemId)
      } else {
        items.value = items.value.map((i) => (i.id === itemId ? { ...i, done: !i.done } : i))
      }
    }
    try {
      const updated = await api.toggleItem(houseId, useListId, itemId)
      announceRestock(updated)
      if (!willDelete) {
        items.value = items.value.map((i) => (i.id === itemId ? updated : i))
      }
    } catch (e) {
      // Roll back on failure.
      if (prev) {
        if (willDelete) {
          items.value = [...items.value, prev]
        } else {
          items.value = items.value.map((i) => (i.id === itemId ? prev : i))
        }
      }
      throw e
    }
  }

  async function undoToggle(prevItem: ChecklistItem): Promise<void> {
    // Reverses a just-completed toggle (undone → done).
    // If deleteOnDone was true, the backend soft-deleted the item — restore it
    // first so the subsequent toggle has something to act on. The item comes
    // back still marked done, so we toggle once more to flip it to undone.
    const useListId = isMeta ? prevItem.listId : listId
    const stillPresent = items.value.some((i) => i.id === prevItem.id)
    if (!stillPresent) {
      await resource.restoreItem(houseId, useListId, prevItem.id)
    }
    const updated = await api.toggleItem(houseId, useListId, prevItem.id)
    announceRestock(updated)
    if (stillPresent) {
      items.value = items.value.map((i) => (i.id === prevItem.id ? updated : i))
    } else {
      items.value = [...items.value, updated]
    }
  }

  async function reorderItems(reorderEntries: { id: number; sortOrder: number }[]): Promise<void> {
    if (isMeta) {
      throw new Error('Reordering is not supported in the All Lists view')
    }
    const map = new Map(reorderEntries.map((i) => [i.id, i.sortOrder]))
    items.value = items.value
      .map((i) => (map.has(i.id) ? { ...i, sortOrder: map.get(i.id)! } : i))
      .sort((a, b) => a.sortOrder - b.sortOrder)
    await resource.reorderItems(houseId, listId, reorderEntries)
  }

  async function remove(itemId: number): Promise<void> {
    await resource.deleteItem(houseId, listIdFor(itemId), itemId)
    items.value = items.value.filter((i) => i.id !== itemId)
  }

  async function undoRemove(prevItem: ChecklistItem): Promise<void> {
    // Restores a soft-deleted item without changing its done state.
    const useListId = isMeta ? prevItem.listId : listId
    const restored = await resource.restoreItem(houseId, useListId, prevItem.id)
    items.value = [...items.value, restored]
  }

  async function removePermanently(itemId: number): Promise<void> {
    await resource.permanentlyDeleteItem(houseId, listIdFor(itemId), itemId)
    items.value = items.value.filter((i) => i.id !== itemId)
  }

  async function restore(itemId: number): Promise<void> {
    await resource.restoreItem(houseId, listIdFor(itemId), itemId)
    // The item leaves the current view: in trash mode it returns to the active
    // list (and stays hidden here); in active mode it was never visible.
    items.value = items.value.filter((i) => i.id !== itemId)
  }

  async function emptyTrash(): Promise<void> {
    if (isMeta) return
    await resource.emptyTrash(houseId, listId)
    if (trashMode.value) {
      items.value = []
    }
  }

  async function archive(itemId: number): Promise<void> {
    await resource.archiveItem(houseId, listIdFor(itemId), itemId)
    // The item leaves the active view for the archive.
    items.value = items.value.filter((i) => i.id !== itemId)
  }

  async function unarchive(itemId: number): Promise<void> {
    await resource.unarchiveItem(houseId, listIdFor(itemId), itemId)
    // The item leaves the archive view; it returns to the active list.
    items.value = items.value.filter((i) => i.id !== itemId)
  }

  async function undoArchive(prevItem: ChecklistItem): Promise<void> {
    // Reverses a just-archived item without changing its state.
    const useListId = isMeta ? prevItem.listId : listId
    const restored = await resource.unarchiveItem(houseId, useListId, prevItem.id)
    items.value = [...items.value, restored]
  }

  // ----- Batch (group) actions -----
  //
  // Each returns the server's BatchResult (with the ids it skipped for access
  // reasons) so the caller can report partial success, and reconciles the local
  // items the same way the single-item ops do.

  async function moveMany(
    ids: number[],
    targetListId: number,
    targetKind: ListKind = kind,
  ): Promise<api.BatchResult> {
    const result = await api.batchMoveItems(houseId, ids, targetListId)
    if (isMeta && targetKind === kind) {
      // Items stay in the meta view under their new list — swap in the updated rows.
      const byId = new Map(result.items.map((i) => [i.id, i]))
      items.value = items.value.map((i) => byId.get(i.id) ?? i)
    } else {
      // Moved items leave a single-list view, and any view of another kind.
      const moved = new Set(ids.filter((id) => !result.skipped.includes(id)))
      items.value = items.value.filter((i) => !moved.has(i.id))
    }
    return result
  }

  async function copyMany(
    ids: number[],
    targetListId: number,
    targetKind: ListKind = kind,
  ): Promise<api.BatchCopyResult> {
    const result = await api.batchCopyItems(houseId, ids, targetListId)
    if (targetKind === kind && (isMeta || targetListId === listId)) {
      mergeItems(result.items)
    }
    return result
  }

  /** Show items fresh from the server, replacing the rows already shown. */
  function mergeItems(incoming: ChecklistItem[]): void {
    const byId = new Map(incoming.map((i) => [i.id, i]))
    const kept = items.value.map((i) => byId.get(i.id) ?? i)
    const shown = new Set(kept.map((i) => i.id))
    items.value = [...kept, ...incoming.filter((i) => !shown.has(i.id))]
  }

  async function removeMany(ids: number[]): Promise<api.BatchResult> {
    const result = await api.batchDeleteItems(houseId, ids, false)
    const removed = new Set(ids.filter((id) => !result.skipped.includes(id)))
    items.value = items.value.filter((i) => !removed.has(i.id))
    return result
  }

  async function removeManyPermanent(ids: number[]): Promise<api.BatchResult> {
    const result = await api.batchDeleteItems(houseId, ids, true)
    const removed = new Set(ids.filter((id) => !result.skipped.includes(id)))
    items.value = items.value.filter((i) => !removed.has(i.id))
    return result
  }

  async function archiveMany(ids: number[]): Promise<api.BatchResult> {
    const result = await api.batchArchiveItems(houseId, ids, true)
    const archived = new Set(ids.filter((id) => !result.skipped.includes(id)))
    items.value = items.value.filter((i) => !archived.has(i.id))
    return result
  }

  async function unarchiveMany(ids: number[]): Promise<api.BatchResult> {
    const result = await api.batchArchiveItems(houseId, ids, false)
    const unarchived = new Set(ids.filter((id) => !result.skipped.includes(id)))
    items.value = items.value.filter((i) => !unarchived.has(i.id))
    return result
  }

  async function undoArchiveMany(prevItems: ChecklistItem[]): Promise<void> {
    // Undo a bulk archive by unarchiving each snapshot (no batch endpoint gap:
    // unarchiveMany exists, but this restores into the active view in order).
    const ids = prevItems.map((i) => i.id)
    await api.batchArchiveItems(houseId, ids, false)
    items.value = [...items.value, ...prevItems]
  }

  async function setCategoryMany(
    ids: number[],
    categoryId: number | null,
  ): Promise<api.BatchResult> {
    const result = await api.batchSetCategory(houseId, ids, categoryId)
    const byId = new Map(result.items.map((i) => [i.id, i]))
    items.value = items.value.map((i) => byId.get(i.id) ?? i)
    return result
  }

  async function setStoresMany(ids: number[], storeIds: number[]): Promise<api.BatchResult> {
    const result = await api.batchSetStores(houseId, ids, storeIds)
    const byId = new Map(result.items.map((i) => [i.id, i]))
    items.value = items.value.map((i) => byId.get(i.id) ?? i)
    return result
  }

  async function setLabelsMany(ids: number[], labelIds: number[]): Promise<api.BatchResult> {
    const result = await api.batchSetLabels(houseId, ids, labelIds)
    const byId = new Map(result.items.map((i) => [i.id, i]))
    items.value = items.value.map((i) => byId.get(i.id) ?? i)
    return result
  }

  // Bulk "uncheck all": clears done-state on the given items in one request.
  // The server returns the now-unchecked rows; swap them in so they move from
  // the Done section back into the active list.
  async function uncheckMany(ids: number[]): Promise<api.BatchResult> {
    const result = await api.batchUncheckItems(houseId, ids)
    const byId = new Map(result.items.map((i) => [i.id, i]))
    items.value = items.value.map((i) => byId.get(i.id) ?? i)
    return result
  }

  async function undoRemoveMany(prevItems: ChecklistItem[]): Promise<void> {
    // Undo a bulk soft-delete by restoring each snapshot (undo is rare, so a
    // per-item loop is fine — there is no batch-restore endpoint).
    const restored: ChecklistItem[] = []
    for (const prev of prevItems) {
      const useListId = isMeta ? prev.listId : listId
      restored.push(await resource.restoreItem(houseId, useListId, prev.id))
    }
    items.value = [...items.value, ...restored]
  }

  async function uploadImage(itemId: number, file: File): Promise<void> {
    const updated = await resource.uploadItemImage(houseId, listIdFor(itemId), itemId, file)
    items.value = items.value.map((i) => (i.id === itemId ? updated : i))
  }

  async function clearImage(itemId: number): Promise<void> {
    const updated = await resource.clearItemImage(houseId, listIdFor(itemId), itemId)
    items.value = items.value.map((i) => (i.id === itemId ? updated : i))
  }

  return {
    items,
    loading,
    error,
    sortBy,
    viewMode,
    trashMode,
    archiveMode,
    load,
    add,
    draw,
    mergeItems,
    update,
    updateStock,
    renewOffers,
    renewExpiry,
    link,
    unlink,
    setAutoLink,
    copy,
    toggle,
    undoToggle,
    reorderItems,
    remove,
    undoRemove,
    removePermanently,
    restore,
    emptyTrash,
    archive,
    unarchive,
    undoArchive,
    uploadImage,
    clearImage,
    moveMany,
    copyMany,
    removeMany,
    removeManyPermanent,
    archiveMany,
    unarchiveMany,
    undoArchiveMany,
    setCategoryMany,
    setStoresMany,
    setLabelsMany,
    uncheckMany,
    undoRemoveMany,
  }
}
