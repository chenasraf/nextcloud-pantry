import { ref } from 'vue'
import * as api from '@/api/categories'
import type { Category } from '@/api/types'

// Cache per house id so multiple components sharing the same house stay in sync.
const cache = new Map<number, ReturnType<typeof build>>()

function build(houseId: number) {
  const items = ref<Category[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const loaded = ref(false)

  async function load(force = false): Promise<void> {
    if (loaded.value && !force) return
    loading.value = true
    error.value = null
    try {
      items.value = await api.listCategories(houseId)
      loaded.value = true
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  async function create(input: { name: string; icon: string; color: string }): Promise<Category> {
    const created = await api.createCategory(houseId, input)
    items.value = [...items.value, created].sort((a, b) => a.name.localeCompare(b.name))
    return created
  }

  async function update(
    id: number,
    patch: Parameters<typeof api.updateCategory>[2],
  ): Promise<Category> {
    const updated = await api.updateCategory(houseId, id, patch)
    items.value = items.value.map((c) => (c.id === id ? updated : c))
    return updated
  }

  async function remove(id: number): Promise<void> {
    await api.deleteCategory(houseId, id)
    items.value = items.value.filter((c) => c.id !== id)
  }

  function findById(id: number | null | undefined): Category | undefined {
    if (id == null) return undefined
    return items.value.find((c) => c.id === id)
  }

  return { items, loading, error, loaded, load, create, update, remove, findById }
}

export function useCategories(houseId: number) {
  let entry = cache.get(houseId)
  if (!entry) {
    entry = build(houseId)
    cache.set(houseId, entry)
  }
  return entry
}
