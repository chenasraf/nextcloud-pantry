import { ref } from 'vue'
import { getShopAllStores, setShopAllStores } from '@/api/prefs'

// Module-level ref so every consumer reads the same reactive value.
const value = ref<boolean>(false)
let loaded = false
let inflight: Promise<void> | null = null

async function load(): Promise<void> {
  if (loaded) return
  if (inflight) return inflight
  inflight = (async () => {
    try {
      value.value = await getShopAllStores()
      loaded = true
    } finally {
      inflight = null
    }
  })()
  return inflight
}

async function set(next: boolean): Promise<void> {
  const previous = value.value
  value.value = next
  try {
    value.value = await setShopAllStores(next)
    loaded = true
  } catch (e) {
    value.value = previous
    throw e
  }
}

export function useShopAllStores() {
  void load()
  return { shopAllStores: value, load, set }
}
