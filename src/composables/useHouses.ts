import { ref, computed } from 'vue'
import * as api from '@/api/houses'
import type { House } from '@/api/types'

const houses = ref<House[]>([])
const loaded = ref(false)
const loading = ref(false)
const error = ref<string | null>(null)

async function load(force = false): Promise<House[]> {
  if (loaded.value && !force) return houses.value
  loading.value = true
  error.value = null
  try {
    houses.value = await api.listHouses()
    loaded.value = true
  } catch (e) {
    error.value = (e as Error).message
    throw e
  } finally {
    loading.value = false
  }
  return houses.value
}

async function create(name: string, description?: string | null): Promise<House> {
  const house = await api.createHouse(name, description)
  houses.value = [...houses.value, house]
  return house
}

async function update(
  id: number,
  patch: { name?: string; description?: string | null },
): Promise<House> {
  const updated = await api.updateHouse(id, patch)
  houses.value = houses.value.map((h) => (h.id === id ? updated : h))
  return updated
}

async function remove(id: number): Promise<void> {
  await api.deleteHouse(id)
  houses.value = houses.value.filter((h) => h.id !== id)
}

function findById(id: number): House | undefined {
  return houses.value.find((h) => h.id === id)
}

export function useHouses() {
  return {
    houses,
    loaded,
    loading,
    error,
    isEmpty: computed(() => loaded.value && houses.value.length === 0),
    load,
    create,
    update,
    remove,
    findById,
  }
}
