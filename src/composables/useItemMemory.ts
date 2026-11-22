import { shallowRef } from 'vue'
import { getItemMemory } from '@/api/itemMemory'
import { emptyMemoryIndex, mergeMemory } from '@/utils/itemMemory'

// Cache per house id: the memory is fetched whole once, then only what changed.
const cache = new Map<number, ReturnType<typeof build>>()

function build(houseId: number) {
  const index = shallowRef(emptyMemoryIndex())
  let since: number | undefined
  let inflight: Promise<void> | null = null

  function refresh(): Promise<void> {
    if (inflight) return inflight
    inflight = (async () => {
      try {
        const memory = await getItemMemory(houseId, since)
        index.value = mergeMemory(index.value, memory.categories, memory.quantities)
        since = memory.serverTime
      } catch {
        // Guesses are a convenience; without memory the form simply offers none.
      } finally {
        inflight = null
      }
    })()
    return inflight
  }

  return { index, refresh }
}

export function useItemMemory(houseId: number) {
  let entry = cache.get(houseId)
  if (!entry) {
    entry = build(houseId)
    cache.set(houseId, entry)
  }
  return entry
}
