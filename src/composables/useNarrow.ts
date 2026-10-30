import { readonly, ref, type Ref } from 'vue'

/** The width at and below which list rows take their narrow layout. */
export const NARROW_MAX_WIDTH = 600

let narrow: Ref<boolean> | null = null

/** Whether the window is at most {@link NARROW_MAX_WIDTH} wide; follows resizes. */
export function useNarrow(): Readonly<Ref<boolean>> {
  if (narrow === null) {
    const query =
      typeof window !== 'undefined' && typeof window.matchMedia === 'function'
        ? window.matchMedia(`(max-width: ${NARROW_MAX_WIDTH}px)`)
        : null
    narrow = ref(query?.matches ?? false)
    query?.addEventListener('change', (e) => {
      narrow!.value = e.matches
    })
  }
  return readonly(narrow)
}
