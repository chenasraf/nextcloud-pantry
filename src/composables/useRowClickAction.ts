import { ref } from 'vue'
import { getRowClickAction, setRowClickAction, type RowClickAction } from '@/api/prefs'

// Module-level ref so every consumer reads the same reactive value.
// Updating it from the settings dialog instantly reflects in any open
// checklist view, no remount needed.
const value = ref<RowClickAction>('none')
let loaded = false
let inflight: Promise<void> | null = null

async function load(): Promise<void> {
  if (loaded) return
  if (inflight) return inflight
  inflight = (async () => {
    try {
      value.value = await getRowClickAction()
      loaded = true
    } finally {
      inflight = null
    }
  })()
  return inflight
}

async function set(next: RowClickAction): Promise<void> {
  const previous = value.value
  value.value = next
  try {
    value.value = await setRowClickAction(next)
    loaded = true
  } catch (e) {
    value.value = previous
    throw e
  }
}

export function useRowClickAction() {
  void load()
  return { rowClickAction: value, set }
}
