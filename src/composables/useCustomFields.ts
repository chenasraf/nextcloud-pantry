import { ref } from 'vue'
import * as api from '@/api/customFields'
import type { FieldDefinition } from '@/api/types'

// Cache per house id so components sharing a house stay in sync.
const cache = new Map<number, ReturnType<typeof build>>()

function sortItems(items: FieldDefinition[]): FieldDefinition[] {
  return [...items].sort((a, b) => a.sortOrder - b.sortOrder || a.name.localeCompare(b.name))
}

function build(houseId: number) {
  const items = ref<FieldDefinition[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const loaded = ref(false)

  async function load(force = false): Promise<void> {
    if (loaded.value && !force) return
    loading.value = true
    error.value = null
    try {
      items.value = sortItems(await api.listFields(houseId))
      loaded.value = true
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  async function create(input: api.CreateFieldInput): Promise<FieldDefinition> {
    const created = await api.createField(houseId, input)
    items.value = sortItems([...items.value, created])
    return created
  }

  async function update(id: number, patch: api.UpdateFieldPatch): Promise<FieldDefinition> {
    const updated = await api.updateField(houseId, id, patch)
    items.value = sortItems(items.value.map((f) => (f.id === id ? updated : f)))
    return updated
  }

  async function remove(id: number): Promise<void> {
    await api.deleteField(houseId, id)
    items.value = items.value.filter((f) => f.id !== id)
  }

  async function reorder(entries: { id: number; sortOrder: number }[]): Promise<void> {
    const map = new Map(entries.map((e) => [e.id, e.sortOrder]))
    items.value = sortItems(
      items.value.map((f) => (map.has(f.id) ? { ...f, sortOrder: map.get(f.id)! } : f)),
    )
    await api.reorderFields(houseId, entries)
  }

  return { items, loading, error, loaded, load, create, update, remove, reorder }
}

export function useCustomFields(houseId: number) {
  let entry = cache.get(houseId)
  if (!entry) {
    entry = build(houseId)
    cache.set(houseId, entry)
  }
  return entry
}
