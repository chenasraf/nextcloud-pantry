import { ref } from 'vue'
import * as api from '@/api/labels'
import type { Label } from '@/api/types'
import type { LabelSort } from '@/api/prefs'
import { getLabelSort } from '@/api/prefs'

// Cache per house id so multiple components sharing the same house stay in sync.
const cache = new Map<number, ReturnType<typeof build>>()

function sortItems(items: Label[], sortBy: LabelSort): Label[] {
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
  const items = ref<Label[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const loaded = ref(false)
  const sortBy = ref<LabelSort>('name_asc')

  async function load(force = false): Promise<void> {
    if (loaded.value && !force) return
    loading.value = true
    error.value = null
    try {
      const [fetched, pref] = await Promise.all([
        api.listLabels(houseId),
        getLabelSort(houseId).catch(() => ({ sort: sortBy.value })),
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

  function setSortBy(value: LabelSort): void {
    sortBy.value = value
    items.value = sortItems(items.value, value)
  }

  async function create(input: {
    name: string
    icon: string
    color: string
    listId?: number | null
  }): Promise<Label> {
    const created = await api.createLabel(houseId, input)
    items.value = sortItems([...items.value, created], sortBy.value)
    return created
  }

  async function update(id: number, patch: Parameters<typeof api.updateLabel>[2]): Promise<Label> {
    const updated = await api.updateLabel(houseId, id, patch)
    items.value = sortItems(
      items.value.map((l) => (l.id === id ? updated : l)),
      sortBy.value,
    )
    return updated
  }

  async function remove(id: number): Promise<void> {
    await api.deleteLabel(houseId, id)
    items.value = items.value.filter((l) => l.id !== id)
  }

  async function reorder(entries: { id: number; sortOrder: number }[]): Promise<void> {
    // Apply optimistically so there's no visual jump while the API call is in flight.
    const map = new Map(entries.map((e) => [e.id, e.sortOrder]))
    items.value = items.value
      .map((l) => (map.has(l.id) ? { ...l, sortOrder: map.get(l.id)! } : l))
      .sort((a, b) => a.sortOrder - b.sortOrder || a.name.localeCompare(b.name))
    await api.reorderLabels(houseId, entries)
  }

  function findById(id: number | null | undefined): Label | undefined {
    if (id == null) return undefined
    return items.value.find((l) => l.id === id)
  }

  // Labels offered for a given list: the list's own scoped labels plus every
  // global (null list) one. A null listId means no specific list is in context
  // (e.g. the "All lists" view), so only globals apply.
  function labelsForList(listId: number | null): Label[] {
    return items.value.filter((l) => l.listId == null || l.listId === listId)
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
    labelsForList,
  }
}

export function useLabels(houseId: number) {
  let entry = cache.get(houseId)
  if (!entry) {
    entry = build(houseId)
    cache.set(houseId, entry)
  }
  return entry
}
