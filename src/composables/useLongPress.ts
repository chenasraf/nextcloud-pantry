import { onBeforeUnmount, onMounted, watch, type Ref } from 'vue'

const LONG_PRESS_MS = 400
const MOVE_THRESHOLD = 8

/**
 * Fires `onLongPress(id)` when a child carrying `[data-drag-id]` is touch-held
 * for {@link LONG_PRESS_MS}. Used to enter multi-select on mobile.
 *
 * Deliberately touch-only: on desktop, selection is entered from the toolbar
 * button. The `enabled` ref lets the caller keep this inert while the custom
 * sort's drag-reorder owns the long-press gesture, or while already selecting.
 */
export function useLongPress(
  containerRef: Ref<HTMLElement | null>,
  onLongPress: (_id: number) => void,
  enabled?: Ref<boolean>,
) {
  let timer: ReturnType<typeof setTimeout> | null = null
  let startX = 0
  let startY = 0
  let pressed = false

  function getDragId(el: HTMLElement): number | null {
    const draggable = el.closest<HTMLElement>('[data-drag-id]')
    const val = draggable?.dataset.dragId
    return val != null ? Number(val) : null
  }

  function clear() {
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
    pressed = false
  }

  function onTouchStart(e: TouchEvent) {
    if (enabled && !enabled.value) return
    const touch = e.touches[0]
    if (!touch) return

    const target = e.target as HTMLElement
    // Leave interactive controls (checkbox, buttons, kebab) to their own handlers.
    if (target.closest('button, a, input, textarea, select, .nc-actions')) return

    const id = getDragId(target)
    if (id === null) return

    startX = touch.clientX
    startY = touch.clientY
    pressed = true
    timer = setTimeout(() => {
      timer = null
      pressed = false
      onLongPress(id)
    }, LONG_PRESS_MS)
  }

  function onTouchMove(e: TouchEvent) {
    if (!pressed) return
    const touch = e.touches[0]
    if (!touch) return
    // A drag past the threshold means the user is scrolling, not pressing.
    if (
      Math.abs(touch.clientX - startX) > MOVE_THRESHOLD ||
      Math.abs(touch.clientY - startY) > MOVE_THRESHOLD
    ) {
      clear()
    }
  }

  // Suppress the browser context menu while a long-press is pending.
  function onContextMenu(ev: Event) {
    if (timer) ev.preventDefault()
  }

  function bind(el: HTMLElement) {
    el.addEventListener('touchstart', onTouchStart, { passive: true })
    el.addEventListener('touchmove', onTouchMove, { passive: true })
    el.addEventListener('touchend', clear)
    el.addEventListener('touchcancel', clear)
    el.addEventListener('contextmenu', onContextMenu)
  }

  function unbind(el: HTMLElement) {
    el.removeEventListener('touchstart', onTouchStart)
    el.removeEventListener('touchmove', onTouchMove)
    el.removeEventListener('touchend', clear)
    el.removeEventListener('touchcancel', clear)
    el.removeEventListener('contextmenu', onContextMenu)
  }

  // Re-bind when the container ref changes (v-if toggles the element).
  let boundEl: HTMLElement | null = null
  function syncBinding() {
    const el = containerRef.value
    if (el === boundEl) return
    if (boundEl) unbind(boundEl)
    boundEl = el
    if (el) bind(el)
  }

  onMounted(syncBinding)
  watch(containerRef, syncBinding)

  onBeforeUnmount(() => {
    clear()
    if (boundEl) {
      unbind(boundEl)
      boundEl = null
    }
  })
}
