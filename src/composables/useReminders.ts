import { computed, ref } from 'vue'
import * as api from '@/api/reminders'
import type { ReminderInput } from '@/api/reminders'
import type { ShoppingReminder, ShoppingReminderMoment } from '@/api/types'

// Cache per house id so the manager and the surfacing blocks share one fetch and
// stay in sync after edits.
const cache = new Map<number, ReturnType<typeof build>>()

function sortItems(items: ShoppingReminder[]): ShoppingReminder[] {
  return [...items].sort((a, b) => a.position - b.position || a.id - b.id)
}

function build(houseId: number) {
  const items = ref<ShoppingReminder[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const loaded = ref(false)

  async function load(force = false): Promise<void> {
    if (loaded.value && !force) return
    loading.value = true
    error.value = null
    try {
      items.value = sortItems(await api.listReminders(houseId))
      loaded.value = true
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  async function create(input: ReminderInput): Promise<ShoppingReminder> {
    const created = await api.createReminder(houseId, input)
    items.value = sortItems([...items.value, created])
    return created
  }

  async function update(id: number, patch: Partial<ReminderInput>): Promise<ShoppingReminder> {
    const updated = await api.updateReminder(houseId, id, patch)
    items.value = sortItems(items.value.map((r) => (r.id === id ? updated : r)))
    return updated
  }

  async function remove(id: number): Promise<void> {
    await api.deleteReminder(houseId, id)
    items.value = items.value.filter((r) => r.id !== id)
  }

  /** Persist a new order (array of ids); the server returns the reordered set. */
  async function reorder(orderedIds: number[]): Promise<void> {
    items.value = sortItems(await api.reorderReminders(houseId, orderedIds))
  }

  /** Enabled reminders for one surfacing moment, in position order. */
  function enabledFor(moment: ShoppingReminderMoment) {
    return computed(() => items.value.filter((r) => r.enabled && r.showOn === moment))
  }

  return { items, loading, error, loaded, load, create, update, remove, reorder, enabledFor }
}

export function useReminders(houseId: number) {
  let entry = cache.get(houseId)
  if (!entry) {
    entry = build(houseId)
    cache.set(houseId, entry)
  }
  return entry
}
