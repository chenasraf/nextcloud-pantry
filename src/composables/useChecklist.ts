import { ref } from 'vue'
import * as api from '@/api/lists'
import type { Checklist, ChecklistItem } from '@/api/types'
import type { ChecklistItemSort } from '@/api/prefs'

export function useChecklists(houseId: number) {
  const lists = ref<Checklist[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function load(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      lists.value = await api.listLists(houseId)
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
  ): Promise<Checklist> {
    const created = await api.createList(houseId, name, description, icon)
    lists.value = [...lists.value, created]
    return created
  }

  async function update(
    listId: number,
    patch: { name?: string; description?: string | null; icon?: string },
  ): Promise<void> {
    const updated = await api.updateList(houseId, listId, patch)
    lists.value = lists.value.map((l) => (l.id === listId ? updated : l))
  }

  async function remove(listId: number): Promise<void> {
    await api.deleteList(houseId, listId)
    lists.value = lists.value.filter((l) => l.id !== listId)
  }

  return { lists, loading, error, load, create, update, remove }
}

export function useChecklistItems(houseId: number, listId: number) {
  const items = ref<ChecklistItem[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const sortBy = ref<ChecklistItemSort>('custom')

  async function load(sort?: ChecklistItemSort): Promise<void> {
    loading.value = true
    error.value = null
    const s = sort ?? sortBy.value
    try {
      items.value = await api.listItems(houseId, listId, s)
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
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

  async function toggle(itemId: number): Promise<void> {
    // Optimistic flip.
    const prev = items.value.find((i) => i.id === itemId)
    if (prev) {
      items.value = items.value.map((i) => (i.id === itemId ? { ...i, done: !i.done } : i))
    }
    try {
      const updated = await api.toggleItem(houseId, listId, itemId)
      items.value = items.value.map((i) => (i.id === itemId ? updated : i))
    } catch (e) {
      // Roll back on failure.
      if (prev) {
        items.value = items.value.map((i) => (i.id === itemId ? prev : i))
      }
      throw e
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
    load,
    add,
    update,
    toggle,
    reorderItems,
    remove,
    uploadImage,
    clearImage,
  }
}
