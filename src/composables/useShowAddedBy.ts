import { ref } from 'vue'
import { getHousePrefs, setHousePrefs } from '@/api/prefs'

// Per-house cache so toggling in the settings dialog instantly reflects in
// any open checklist view without an extra fetch.
const cache = new Map<number, ReturnType<typeof ref<boolean>>>()
const inflight = new Map<number, Promise<void>>()

function ensureRef(houseId: number) {
  let r = cache.get(houseId)
  if (!r) {
    r = ref(false)
    cache.set(houseId, r)
  }
  return r
}

async function load(houseId: number): Promise<void> {
  if (inflight.has(houseId)) return inflight.get(houseId)
  const r = ensureRef(houseId)
  const p = (async () => {
    try {
      const prefs = await getHousePrefs(houseId)
      r.value = prefs.showAddedBy
    } finally {
      inflight.delete(houseId)
    }
  })()
  inflight.set(houseId, p)
  return p
}

async function set(houseId: number, next: boolean): Promise<void> {
  const r = ensureRef(houseId)
  const previous = r.value
  r.value = next
  try {
    const prefs = await setHousePrefs(houseId, { showAddedBy: next })
    r.value = prefs.showAddedBy
  } catch (e) {
    r.value = previous
    throw e
  }
}

export function useShowAddedBy(houseId: number) {
  const r = ensureRef(houseId)
  void load(houseId)
  return { showAddedBy: r, set: (v: boolean) => set(houseId, v) }
}
