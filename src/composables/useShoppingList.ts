import { ref } from 'vue'
import * as api from '@/api/lists'
import type { ShoppingList, ShoppingListItem } from '@/api/types'

export function useShoppingLists(houseId: number) {
  const lists = ref<ShoppingList[]>([])
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

  async function create(name: string, description?: string | null): Promise<ShoppingList> {
    const created = await api.createList(houseId, name, description)
    lists.value = [...lists.value, created]
    return created
  }

  async function update(
    listId: number,
    patch: { name?: string; description?: string | null },
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

export function useShoppingListItems(houseId: number, listId: number) {
  const items = ref<ShoppingListItem[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function load(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      items.value = await api.listItems(houseId, listId)
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  async function add(input: api.ItemInput): Promise<ShoppingListItem> {
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
      items.value = items.value.map((i) => (i.id === itemId ? { ...i, bought: !i.bought } : i))
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

  async function remove(itemId: number): Promise<void> {
    await api.deleteItem(houseId, listId, itemId)
    items.value = items.value.filter((i) => i.id !== itemId)
  }

  return { items, loading, error, load, add, update, toggle, remove }
}
