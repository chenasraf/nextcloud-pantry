import { ref } from 'vue'
import * as api from '@/api/stores'
import type { StoreInput } from '@/api/stores'
import type { Store } from '@/api/types'

// Cache per house id so multiple components sharing the same house stay in sync.
const cache = new Map<number, ReturnType<typeof build>>()

function sortItems(items: Store[]): Store[] {
  return [...items].sort((a, b) => a.name.localeCompare(b.name))
}

function build(houseId: number) {
  const items = ref<Store[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const loaded = ref(false)

  async function load(force = false): Promise<void> {
    if (loaded.value && !force) return
    loading.value = true
    error.value = null
    try {
      const fetched = await api.listStores(houseId)
      items.value = sortItems(fetched)
      loaded.value = true
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  async function create(input: StoreInput): Promise<Store> {
    const created = await api.createStore(houseId, input)
    items.value = sortItems([...items.value, created])
    return created
  }

  async function update(id: number, patch: Parameters<typeof api.updateStore>[2]): Promise<Store> {
    const updated = await api.updateStore(houseId, id, patch)
    items.value = sortItems(items.value.map((s) => (s.id === id ? updated : s)))
    return updated
  }

  async function remove(id: number): Promise<void> {
    await api.deleteStore(houseId, id)
    items.value = items.value.filter((s) => s.id !== id)
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
    load,
    create,
    update,
    remove,
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
