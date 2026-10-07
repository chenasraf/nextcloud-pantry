import { reactive } from 'vue'
import { getBarcodeFillPrefs, setBarcodeFillPrefs, type BarcodeFillPrefs } from '@/api/prefs'

// Module-level state so every consumer reads the same reactive values.
const value = reactive<BarcodeFillPrefs>({ name: true, category: true, image: true })
let loaded = false
let inflight: Promise<void> | null = null

async function load(): Promise<void> {
  if (loaded) return
  if (inflight) return inflight
  inflight = (async () => {
    try {
      Object.assign(value, await getBarcodeFillPrefs())
      loaded = true
    } catch {
      // Keep the all-enabled defaults; a later consumer retries the load.
    } finally {
      inflight = null
    }
  })()
  return inflight
}

async function set(field: keyof BarcodeFillPrefs, next: boolean): Promise<void> {
  const previous = value[field]
  value[field] = next
  try {
    Object.assign(value, await setBarcodeFillPrefs({ [field]: next }))
    loaded = true
  } catch (e) {
    value[field] = previous
    throw e
  }
}

export function useBarcodeFill() {
  void load()
  return { barcodeFill: value, set }
}
