import { ref } from 'vue'
import { getTapRowToComplete, setTapRowToComplete } from '@/api/prefs'

// Module-level ref so every consumer reads the same reactive value.
// Updating it from the settings dialog instantly reflects in any open
// checklist view, no remount needed.
const value = ref(false)
let loaded = false
let inflight: Promise<void> | null = null

async function load(): Promise<void> {
  if (loaded) return
  if (inflight) return inflight
  inflight = (async () => {
    try {
      value.value = await getTapRowToComplete()
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
    value.value = await setTapRowToComplete(next)
    loaded = true
  } catch (e) {
    value.value = previous
    throw e
  }
}

export function useTapRowToComplete() {
  void load()
  return { tapRowToComplete: value, set }
}
