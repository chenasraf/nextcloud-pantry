import { ref } from 'vue'
import * as api from '@/api/stores'
import type { StoreInput } from '@/api/stores'
import type { Store } from '@/api/types'
import type { StoreSort } from '@/api/prefs'
import { getStoreSort } from '@/api/prefs'

// Cache per house id so multiple components sharing the same house stay in sync.
const cache = new Map<number, ReturnType<typeof build>>()

function sortItems(items: Store[], sortBy: StoreSort): Store[] {
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

function build(houseId: number) {
  const items = ref<Store[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const loaded = ref(false)
  const sortBy = ref<StoreSort>('name_asc')

  async function load(force = false): Promise<void> {
    if (loaded.value && !force) return
    loading.value = true
    error.value = null
    try {
      const [fetched, pref] = await Promise.all([
        api.listStores(houseId),
        getStoreSort(houseId).catch(() => ({ sort: sortBy.value })),
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

  function setSortBy(value: StoreSort): void {
    sortBy.value = value
    items.value = sortItems(items.value, value)
  }

  async function create(input: StoreInput): Promise<Store> {
    const created = await api.createStore(houseId, input)
    items.value = sortItems([...items.value, created], sortBy.value)
    return created
  }

  async function update(id: number, patch: Parameters<typeof api.updateStore>[2]): Promise<Store> {
    const updated = await api.updateStore(houseId, id, patch)
    items.value = sortItems(
      items.value.map((s) => (s.id === id ? updated : s)),
      sortBy.value,
    )
    return updated
  }

  async function remove(id: number): Promise<void> {
    await api.deleteStore(houseId, id)
    items.value = items.value.filter((s) => s.id !== id)
  }

  async function reorder(entries: { id: number; sortOrder: number }[]): Promise<void> {
    // Apply optimistically so there's no visual jump while the API call is in flight.
    const map = new Map(entries.map((e) => [e.id, e.sortOrder]))
    items.value = items.value
      .map((s) => (map.has(s.id) ? { ...s, sortOrder: map.get(s.id)! } : s))
      .sort((a, b) => a.sortOrder - b.sortOrder || a.name.localeCompare(b.name))
    await api.reorderStores(houseId, entries)
  }

  function findById(id: number | null | undefined): Store | undefined {
    if (id == null) return undefined
    return items.value.find((s) => s.id === id)
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
  }
}

export function useStores(houseId: number) {
  let entry = cache.get(houseId)
  if (!entry) {
    entry = build(houseId)
    cache.set(houseId, entry)
  }
  return entry
}
