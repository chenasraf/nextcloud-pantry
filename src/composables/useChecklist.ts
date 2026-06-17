import { ref, type Ref } from 'vue'
import * as api from '@/api/lists'
import type { Checklist, ChecklistItem } from '@/api/types'
import type { ChecklistItemSort, ChecklistSort } from '@/api/prefs'

// Per-house state shared across all callers so sidebar and views stay in sync.
interface HouseChecklistState {
  lists: Ref<Checklist[]>
  deletedLists: Ref<Checklist[]>
  loading: Ref<boolean>
  error: Ref<string | null>
  sortBy: Ref<ChecklistSort>
  trashMode: Ref<boolean>
  inflight: Promise<Checklist[]> | null
}
const houseStates = new Map<number, HouseChecklistState>()

function getState(houseId: number): HouseChecklistState {
  let s = houseStates.get(houseId)
  if (!s) {
    s = {
      lists: ref<Checklist[]>([]),
      deletedLists: ref<Checklist[]>([]),
      loading: ref(false),
      error: ref<string | null>(null),
      sortBy: ref<ChecklistSort>('custom'),
      trashMode: ref(false),
      inflight: null,
    }
    houseStates.set(houseId, s)
  }
  return s
}

export function useChecklists(houseId: number) {
  const state = getState(houseId)
  const { lists, deletedLists, loading, error, sortBy, trashMode } = state

  function load(sortOrForce?: ChecklistSort | boolean): Promise<Checklist[]> {
    const force = sortOrForce === true
    const sort = typeof sortOrForce === 'string' ? sortOrForce : sortBy.value
    if (state.inflight && !force) return state.inflight
    loading.value = true
    error.value = null
    state.inflight = api
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
      deletedLists.value = await api.listDeletedLists(houseId)
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
  ): Promise<Checklist> {
    const created = await api.createList(houseId, name, description, icon, color)
    lists.value = [...lists.value, created]
    return created
  }

  async function update(
    listId: number,
    patch: {
      name?: string
      description?: string | null
      icon?: string
      color?: string | null
      deleteOnDoneDefault?: boolean
    },
  ): Promise<void> {
    const updated = await api.updateList(houseId, listId, patch)
    lists.value = lists.value.map((l) => (l.id === listId ? updated : l))
  }

  async function remove(listId: number): Promise<void> {
    await api.deleteList(houseId, listId)
    lists.value = lists.value.filter((l) => l.id !== listId)
  }

  async function restore(listId: number): Promise<void> {
    const restored = await api.restoreList(houseId, listId)
    deletedLists.value = deletedLists.value.filter((l) => l.id !== listId)
    // Append so it shows up immediately if the user toggles back to the active view.
    lists.value = [...lists.value, restored]
  }

  async function removePermanently(listId: number): Promise<void> {
    await api.permanentlyDeleteList(houseId, listId)
    deletedLists.value = deletedLists.value.filter((l) => l.id !== listId)
    lists.value = lists.value.filter((l) => l.id !== listId)
  }

  async function emptyTrash(): Promise<void> {
    await api.emptyListsTrash(houseId)
    deletedLists.value = []
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
    loading,
    error,
    sortBy,
    trashMode,
    load,
    loadDeleted,
    create,
    update,
    remove,
    restore,
    removePermanently,
    emptyTrash,
    reorder,
  }
}

export function useChecklistItems(houseId: number, listId: number) {
  const items = ref<ChecklistItem[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const sortBy = ref<ChecklistItemSort>('custom')
  const trashMode = ref(false)

  async function load(sort?: ChecklistItemSort, options?: { silent?: boolean }): Promise<void> {
    const silent = options?.silent ?? false
    if (!silent) {
      loading.value = true
      error.value = null
    }
    const s = sort ?? sortBy.value
    try {
      items.value = trashMode.value
        ? await api.listDeletedItems(houseId, listId)
        : await api.listItems(houseId, listId, s)
    } catch (e) {
      if (!silent) error.value = (e as Error).message
    } finally {
      if (!silent) loading.value = false
    }
  }

  async function add(input: api.ItemInput): Promise<ChecklistItem> {
    const created = await api.addItem(houseId, listId, input)
    items.value = [...items.value, created]
    return created
  }

  async function update(itemId: number, patch: Partial<api.ItemInput>): Promise<void> {
    const updated = await api.updateItem(houseId, listId, itemId, patch)
    items.value = items.value.map((i) => (i.id === itemId ? updated : i))
  }

  async function copy(itemId: number, targetListId: number): Promise<ChecklistItem> {
    const created = await api.copyItem(houseId, listId, itemId, targetListId)
    // Only append locally when the destination is the current list — otherwise
    // it belongs to a different view.
    if (targetListId === listId) {
      items.value = [...items.value, created]
    }
    return created
  }

  async function toggle(itemId: number): Promise<void> {
    // Optimistic flip. "Once" items disappear when marked done — the backend
    // deletes them in the same call, so we drop them locally too.
    const prev = items.value.find((i) => i.id === itemId)
    const willDelete = !!prev && !prev.done && prev.deleteOnDone
    if (prev) {
      if (willDelete) {
        items.value = items.value.filter((i) => i.id !== itemId)
      } else {
        items.value = items.value.map((i) => (i.id === itemId ? { ...i, done: !i.done } : i))
      }
    }
    try {
      const updated = await api.toggleItem(houseId, listId, itemId)
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
    const stillPresent = items.value.some((i) => i.id === prevItem.id)
    if (!stillPresent) {
      await api.restoreItem(houseId, listId, prevItem.id)
    }
    const updated = await api.toggleItem(houseId, listId, prevItem.id)
    if (stillPresent) {
      items.value = items.value.map((i) => (i.id === prevItem.id ? updated : i))
    } else {
      items.value = [...items.value, updated]
    }
  }

  async function reorderItems(reorderEntries: { id: number; sortOrder: number }[]): Promise<void> {
    const map = new Map(reorderEntries.map((i) => [i.id, i.sortOrder]))
    items.value = items.value
      .map((i) => (map.has(i.id) ? { ...i, sortOrder: map.get(i.id)! } : i))
      .sort((a, b) => a.sortOrder - b.sortOrder)
    await api.reorderItems(houseId, listId, reorderEntries)
  }

  async function remove(itemId: number): Promise<void> {
    await api.deleteItem(houseId, listId, itemId)
    items.value = items.value.filter((i) => i.id !== itemId)
  }

  async function undoRemove(prevItem: ChecklistItem): Promise<void> {
    // Restores a soft-deleted item without changing its done state.
    const restored = await api.restoreItem(houseId, listId, prevItem.id)
    items.value = [...items.value, restored]
  }

  async function removePermanently(itemId: number): Promise<void> {
    await api.permanentlyDeleteItem(houseId, listId, itemId)
    items.value = items.value.filter((i) => i.id !== itemId)
  }

  async function restore(itemId: number): Promise<void> {
    await api.restoreItem(houseId, listId, itemId)
    // The item leaves the current view: in trash mode it returns to the active
    // list (and stays hidden here); in active mode it was never visible.
    items.value = items.value.filter((i) => i.id !== itemId)
  }

  async function emptyTrash(): Promise<void> {
    await api.emptyTrash(houseId, listId)
    if (trashMode.value) {
      items.value = []
    }
  }

  async function uploadImage(itemId: number, file: File): Promise<void> {
    const updated = await api.uploadItemImage(houseId, listId, itemId, file)
    items.value = items.value.map((i) => (i.id === itemId ? updated : i))
  }

  async function clearImage(itemId: number): Promise<void> {
    const updated = await api.clearItemImage(houseId, listId, itemId)
    items.value = items.value.map((i) => (i.id === itemId ? updated : i))
  }

  return {
    items,
    loading,
    error,
    sortBy,
    trashMode,
    load,
    add,
    update,
    copy,
    toggle,
    undoToggle,
    reorderItems,
    remove,
    undoRemove,
    removePermanently,
    restore,
    emptyTrash,
    uploadImage,
    clearImage,
  }
}
