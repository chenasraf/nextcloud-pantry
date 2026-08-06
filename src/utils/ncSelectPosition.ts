import {
  autoUpdate,
  computePosition,
  flip,
  limitShift,
  offset,
  shift,
  size,
  type Middleware,
} from '@floating-ui/dom'

/**
 * A drop-in replacement for NcSelect's `calculate-position` prop.
 *
 * NcSelect appends its dropdown to `document.body` and positions it with
 * floating-ui, but its default implementation only uses `flip` + `shift`. It
 * never constrains the dropdown's height to the space actually available, so on
 * a small viewport (mobile, on-screen keyboard open) a long option list
 * overflows past the top/bottom edge of the screen and — because the box's own
 * height still equals its content height — the off-screen options cannot be
 * scrolled into view.
 *
 * This variant adds floating-ui's `size` middleware, capping `max-height` to the
 * available space so the list stays on-screen and scrolls internally instead.
 *
 * See https://github.com/chenasraf/nextcloud-pantry/issues/174.
 */
export function ncSelectCalculatePosition(
  dropdownMenu: HTMLElement,
  component: { $el: HTMLElement; $refs: { toggle: HTMLElement } },
  { width }: { width: string },
): () => void {
  dropdownMenu.style.width = width

  // Mirror NcSelect's default class toggles so its styling (border radius,
  // drop-up arrow) keeps working.
  const addClass: Middleware = {
    name: 'addClass',
    fn() {
      dropdownMenu.classList.add('vs__dropdown-menu--floating')
      return {}
    },
  }
  const togglePlacementClass: Middleware = {
    name: 'togglePlacementClass',
    fn({ placement }) {
      const isTop = placement === 'top'
      component.$el.classList.toggle('select--drop-up', isTop)
      dropdownMenu.classList.toggle('vs__dropdown-menu--floating-placement-top', isTop)
      return {}
    },
  }

  const updatePosition = () => {
    void computePosition(component.$refs.toggle, dropdownMenu, {
      placement: 'bottom',
      middleware: [
        offset(-1),
        addClass,
        togglePlacementClass,
        flip(),
        shift({ limiter: limitShift() }),
        size({
          padding: 8,
          apply({ availableHeight, elements }) {
            // Preserve the default 350px cap on roomy viewports; shrink to fit
            // when space is tight, but never collapse to an unusable sliver.
            const maxHeight = Math.max(120, Math.min(availableHeight, 350))
            elements.floating.style.maxHeight = `${maxHeight}px`
          },
        }),
      ],
    }).then(({ x, y }) => {
      Object.assign(dropdownMenu.style, {
        left: `${x}px`,
        top: `${y}px`,
        width: `${component.$refs.toggle.getBoundingClientRect().width}px`,
      })
    })
  }

  return autoUpdate(component.$refs.toggle, dropdownMenu, updatePosition)
}
