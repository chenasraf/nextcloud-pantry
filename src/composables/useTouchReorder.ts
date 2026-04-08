import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue'

export interface TouchReorderCallbacks {
  onDragStart: (_id: number) => void
  onReorderOver: (_hoveredId: number, _clientX: number, _clientY: number) => void
  onDrop: () => void
  onCancel: () => void
}

const LONG_PRESS_MS = 300
const MOVE_THRESHOLD = 8
const SCROLL_EDGE = 40
const SCROLL_SPEED = 8

/**
 * Adds touch-based drag-and-drop reorder to a container.
 *
 * Children must have `[data-drag-id]` attributes to be considered draggable.
 * The composable creates a floating ghost clone during drag and calls the same
 * callbacks the existing HTML5 DnD system uses.
 */
export function useTouchReorder(
  containerRef: Ref<HTMLElement | null>,
  callbacks: TouchReorderCallbacks,
  enabled?: Ref<boolean>,
) {
  const isTouchDragging = ref(false)

  let longPressTimer: ReturnType<typeof setTimeout> | null = null
  let scrollTimer: ReturnType<typeof setInterval> | null = null
  let ghost: HTMLElement | null = null
  let dragId: number | null = null
  let startX = 0
  let startY = 0

  function findDraggable(el: HTMLElement): HTMLElement | null {
    return el.closest<HTMLElement>('[data-drag-id]')
  }

  function getDragId(el: HTMLElement): number | null {
    const val = el.dataset.dragId
    return val != null ? Number(val) : null
  }

  function createGhost(source: HTMLElement, x: number, y: number) {
    const rect = source.getBoundingClientRect()
    ghost = source.cloneNode(true) as HTMLElement
    ghost.style.position = 'fixed'
    ghost.style.width = rect.width + 'px'
    ghost.style.height = rect.height + 'px'
    ghost.style.left = x - rect.width / 2 + 'px'
    ghost.style.top = y - rect.height / 2 + 'px'
    ghost.style.zIndex = '999999'
    ghost.style.opacity = '0.85'
    ghost.style.pointerEvents = 'none'
    ghost.style.transition = 'none'
    ghost.style.transform = 'scale(1.05)'
    ghost.style.boxShadow = '0 8px 24px rgba(0,0,0,0.3)'
    document.body.appendChild(ghost)
  }

  function moveGhost(x: number, y: number) {
    if (!ghost) return
    const w = ghost.offsetWidth
    const h = ghost.offsetHeight
    ghost.style.left = x - w / 2 + 'px'
    ghost.style.top = y - h / 2 + 'px'
  }

  function removeGhost() {
    if (ghost) {
      ghost.remove()
      ghost = null
    }
  }

  function autoScroll(clientY: number) {
    if (scrollTimer) {
      clearInterval(scrollTimer)
      scrollTimer = null
    }

    const vh = window.innerHeight
    if (clientY < SCROLL_EDGE) {
      scrollTimer = setInterval(() => window.scrollBy(0, -SCROLL_SPEED), 16)
    } else if (clientY > vh - SCROLL_EDGE) {
      scrollTimer = setInterval(() => window.scrollBy(0, SCROLL_SPEED), 16)
    }
  }

  function stopAutoScroll() {
    if (scrollTimer) {
      clearInterval(scrollTimer)
      scrollTimer = null
    }
  }

  function hitTest(x: number, y: number): HTMLElement | null {
    if (ghost) ghost.style.display = 'none'
    const el = document.elementFromPoint(x, y) as HTMLElement | null
    if (ghost) ghost.style.display = ''
    return el ? findDraggable(el) : null
  }

  function clearLongPress() {
    if (longPressTimer) {
      clearTimeout(longPressTimer)
      longPressTimer = null
    }
  }

  function cancelDrag() {
    clearLongPress()
    stopAutoScroll()
    removeGhost()
    if (isTouchDragging.value) {
      isTouchDragging.value = false
      callbacks.onCancel()
    }
    dragId = null
  }

  function onTouchStart(e: TouchEvent) {
    if (enabled && !enabled.value) return
    const touch = e.touches[0]
    if (!touch) return

    const target = e.target as HTMLElement
    if (target.closest('button, a, input, textarea, select, .nc-actions')) return

    const draggable = findDraggable(target)
    if (!draggable) return

    const id = getDragId(draggable)
    if (id === null) return

    startX = touch.clientX
    startY = touch.clientY
    dragId = id

    longPressTimer = setTimeout(() => {
      longPressTimer = null
      isTouchDragging.value = true
      callbacks.onDragStart(id)
      createGhost(draggable, startX, startY)
    }, LONG_PRESS_MS)
  }

  function onTouchMove(e: TouchEvent) {
    const touch = e.touches[0]
    if (!touch) return

    const dx = touch.clientX - startX
    const dy = touch.clientY - startY

    // If moved before long press triggers, cancel — user is scrolling
    if (!isTouchDragging.value) {
      if (Math.abs(dx) > MOVE_THRESHOLD || Math.abs(dy) > MOVE_THRESHOLD) {
        clearLongPress()
      }
      return
    }

    // We're dragging — prevent scroll
    e.preventDefault()

    moveGhost(touch.clientX, touch.clientY)
    autoScroll(touch.clientY)

    const hovered = hitTest(touch.clientX, touch.clientY)
    if (hovered) {
      const hoveredId = getDragId(hovered)
      if (hoveredId !== null && hoveredId !== dragId) {
        callbacks.onReorderOver(hoveredId, touch.clientX, touch.clientY)
      }
    }
  }

  function onTouchEnd() {
    clearLongPress()
    stopAutoScroll()
    removeGhost()

    if (isTouchDragging.value) {
      isTouchDragging.value = false
      callbacks.onDrop()
    }
    dragId = null
  }

  onMounted(() => {
    const el = containerRef.value
    if (!el) return
    el.addEventListener('touchstart', onTouchStart, { passive: false })
    el.addEventListener('touchmove', onTouchMove, { passive: false })
    el.addEventListener('touchend', onTouchEnd)
    el.addEventListener('touchcancel', cancelDrag)
  })

  onBeforeUnmount(() => {
    cancelDrag()
    const el = containerRef.value
    if (!el) return
    el.removeEventListener('touchstart', onTouchStart)
    el.removeEventListener('touchmove', onTouchMove)
    el.removeEventListener('touchend', onTouchEnd)
    el.removeEventListener('touchcancel', cancelDrag)
  })

  return { isTouchDragging }
}
