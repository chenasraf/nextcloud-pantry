import { ref } from 'vue'
import * as api from '@/api/categories'
import type { Category } from '@/api/types'
import type { CategorySort } from '@/api/prefs'
import { getCategorySort } from '@/api/prefs'

// Cache per house id so multiple components sharing the same house stay in sync.
const cache = new Map<number, ReturnType<typeof build>>()

function sortItems(items: Category[], sortBy: CategorySort): Category[] {
  const next = [...items]
  switch (sortBy) {
    case 'name_desc':
      next.sort((a, b) => b.name.localeCompare(a.name))
      break
    case 'custom':
      next.sort((a, b) => a.sortOrder - b.sortOrder || a.name.localeCompare(b.name))
      break
    default:
      next.sort((a, b) => a.name.localeCompare(b.name))
      break
  }
  return next
}

// Placing a category inside its group makes room by pushing everything at or
// past that position one step down. The server does this when it stores the
// category; mirroring it keeps the cached order matching the stored one until
// the next fetch.
function shiftFrom(items: Category[], from: number, exceptId: number): Category[] {
  return items.map((c) =>
    c.id !== exceptId && c.sortOrder >= from ? { ...c, sortOrder: c.sortOrder + 1 } : c,
  )
}

function build(houseId: number) {
  const items = ref<Category[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const loaded = ref(false)
  const sortBy = ref<CategorySort>('name_asc')

  async function load(force = false): Promise<void> {
    if (loaded.value && !force) return
    loading.value = true
    error.value = null
    try {
      const [fetched, pref] = await Promise.all([
        api.listCategories(houseId),
        getCategorySort(houseId).catch(() => ({ sort: sortBy.value })),
      ])
      sortBy.value = pref.sort
      items.value = sortItems(fetched, sortBy.value)
      loaded.value = true
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  function setSortBy(value: CategorySort): void {
    sortBy.value = value
    items.value = sortItems(items.value, value)
  }

  async function create(input: {
    name: string
    icon: string
    color: string
    listId?: number | null
  }): Promise<Category> {
    const created = await api.createCategory(houseId, input)
    items.value = sortItems(
      shiftFrom([...items.value, created], created.sortOrder, created.id),
      sortBy.value,
    )
    return created
  }

  async function update(
    id: number,
    patch: Parameters<typeof api.updateCategory>[2],
  ): Promise<Category> {
    const previous = items.value.find((c) => c.id === id)
    const updated = await api.updateCategory(houseId, id, patch)
    let next = items.value.map((c) => (c.id === id ? updated : c))
    // Moving a category to another list re-places it at the end of that group,
    // unless the patch pinned a position of its own.
    if (previous && previous.listId !== updated.listId && patch.sortOrder === undefined) {
      next = shiftFrom(next, updated.sortOrder, updated.id)
    }
    items.value = sortItems(next, sortBy.value)
    return updated
  }

  async function remove(id: number): Promise<void> {
    await api.deleteCategory(houseId, id)
    items.value = items.value.filter((c) => c.id !== id)
  }

  async function reorder(entries: { id: number; sortOrder: number }[]): Promise<void> {
    // Apply optimistically so there's no visual jump while the API call is in flight.
    const map = new Map(entries.map((e) => [e.id, e.sortOrder]))
    items.value = items.value
      .map((c) => (map.has(c.id) ? { ...c, sortOrder: map.get(c.id)! } : c))
      .sort((a, b) => a.sortOrder - b.sortOrder || a.name.localeCompare(b.name))
    await api.reorderCategories(houseId, entries)
  }

  function findById(id: number | null | undefined): Category | undefined {
    if (id == null) return undefined
    return items.value.find((c) => c.id === id)
  }

  // Categories offered for a given list: the list's own scoped categories plus
  // every global (null list) one. A null listId means no specific list is in
  // context (e.g. the "All lists" view), so only globals apply.
  function categoriesForList(listId: number | null): Category[] {
    return items.value.filter((c) => c.listId == null || c.listId === listId)
  }

  return {
    items,
    loading,
    error,
    loaded,
    sortBy,
    load,
    setSortBy,
    create,
    update,
    remove,
    reorder,
    findById,
    categoriesForList,
  }
}

export function useCategories(houseId: number) {
  let entry = cache.get(houseId)
  if (!entry) {
    entry = build(houseId)
    cache.set(houseId, entry)
  }
  return entry
}
