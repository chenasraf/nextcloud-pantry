import { ref } from 'vue'
import { getReuseExistingItems, setReuseExistingItems, type ReuseExistingItems } from '@/api/prefs'

// Module-level ref so every consumer reads the same reactive value.
const value = ref<ReuseExistingItems>('ask')
let loaded = false
let inflight: Promise<void> | null = null

async function load(): Promise<void> {
  if (loaded) return
  if (inflight) return inflight
  inflight = (async () => {
    try {
      value.value = await getReuseExistingItems()
      loaded = true
    } finally {
      inflight = null
    }
  })()
  return inflight
}

async function set(next: ReuseExistingItems): Promise<void> {
  const previous = value.value
  value.value = next
  try {
    value.value = await setReuseExistingItems(next)
    loaded = true
  } catch (e) {
    value.value = previous
    throw e
  }
}

export function useReuseExistingItems() {
  void load()
  return { reuseExistingItems: value, set }
}
