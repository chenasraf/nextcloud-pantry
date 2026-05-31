import { computed, ref, type Ref } from 'vue'
import { listMembers } from '@/api/houses'
import type { HouseMember } from '@/api/types'

// Per-house cache. Members rarely change, so sharing one fetch across all
// consumers (rows, dialogs, etc.) avoids repeated round-trips.
const cache = new Map<number, Ref<HouseMember[]>>()
const inflight = new Map<number, Promise<void>>()

function ensureRef(houseId: number): Ref<HouseMember[]> {
  let r = cache.get(houseId)
  if (!r) {
    r = ref<HouseMember[]>([])
    cache.set(houseId, r)
  }
  return r
}

async function load(houseId: number): Promise<void> {
  if (inflight.has(houseId)) return inflight.get(houseId)
  const r = ensureRef(houseId)
  const p = (async () => {
    try {
      r.value = await listMembers(houseId)
    } finally {
      inflight.delete(houseId)
    }
  })()
  inflight.set(houseId, p)
  return p
}

export function useHouseMembers(houseId: number) {
  const r = ensureRef(houseId)
  void load(houseId)
  const displayNameByUid = computed<Record<string, string>>(() => {
    const map: Record<string, string> = {}
    for (const m of r.value) {
      map[m.userId] = m.displayName
    }
    return map
  })
  return { members: r, displayNameByUid }
}
