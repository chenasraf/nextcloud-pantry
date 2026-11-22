import { ref } from 'vue'
import { getSmartInput, setSmartInput } from '@/api/prefs'

// Module-level ref so every consumer reads the same reactive value.
const value = ref<boolean>(true)
let loaded = false
let inflight: Promise<void> | null = null

async function load(): Promise<void> {
  if (loaded) return
  if (inflight) return inflight
  inflight = (async () => {
    try {
      value.value = await getSmartInput()
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
    value.value = await setSmartInput(next)
    loaded = true
  } catch (e) {
    value.value = previous
    throw e
  }
}

export function useSmartInput() {
  void load()
  return { smartInput: value, set }
}
