/**
 * Quantities are free-form text — "3", "400 g", "2 x 1 L" — so stepping one
 * rewrites the first number and leaves everything around it untouched.
 */
const FIRST_NUMBER_RE = /\d+(?:\.\d+)?/

const MIN_QUANTITY = 1

/**
 * The distance between neighbouring values on the quantity grid at `value`: a
 * 1-5-10 progression per decade, so the step grows with the number it applies
 * to. Without it, walking "400 g" up to "500 g" takes a hundred taps.
 */
function stepAt(value: number): number {
  if (value < 10) return 1
  let decade = 10
  while (decade * 10 <= value) decade *= 10
  return value < 5 * decade ? decade / 2 : decade
}

/**
 * The first grid value above `value`; off-grid numbers land on the grid rather
 * than carrying their offset along (123 → 150 → 200).
 */
function gridUp(value: number): number {
  const step = stepAt(value)
  return Math.floor(value / step) * step + step
}

/**
 * The first grid value below `value`. A whole number takes its step from
 * `value - 1` so a value sitting on a band boundary descends into the band
 * beneath it (10 → 9, not 10 → 5); a fractional value is already strictly
 * above its own floor.
 */
function gridDown(value: number): number {
  if (value <= MIN_QUANTITY) return value
  const base = Number.isInteger(value) ? value - 1 : value
  const step = stepAt(base)
  return Math.max(MIN_QUANTITY, Math.floor(base / step) * step)
}

/**
 * Step the number inside `quantity` one grid value up (positive `direction`)
 * or down, keeping the unit and any surrounding text. A quantity with no
 * number at all gains a leading "1" when stepped up.
 */
export function stepQuantity(quantity: string, direction: number): string {
  const match = quantity.match(FIRST_NUMBER_RE)
  if (!match) {
    if (direction <= 0) return quantity
    return quantity === '' ? '1' : `1 ${quantity}`
  }
  const current = Number(match[0])
  if (!Number.isFinite(current)) return quantity
  const stepped = direction > 0 ? gridUp(current) : gridDown(current)
  return quantity.replace(FIRST_NUMBER_RE, String(stepped))
}

/** True when `quantity` holds a number the stepper can take down a grid value. */
export function canStepDown(quantity: string): boolean {
  const match = quantity.match(FIRST_NUMBER_RE)
  if (!match) return false
  const current = Number(match[0])
  return Number.isFinite(current) && current > MIN_QUANTITY
}
