import type { ExpiryStatus, ItemStock, StockLevel, StockMode, StockStatus } from '@/api/types'

/**
 * The stock rules the server applies to inventory items, for updating a row
 * before the server answers. The server's `stockStatus`, `shortfall` and
 * `expiryStatus` win once it does. Both sides are pinned by the shared vectors
 * in tests/stock-vectors.json.
 */
const FIRST_NUMBER_RE = /\d+(?:\.\d+)?/

/** The stored fields the rules read. A count item's `quantity` is on hand. */
export interface StockInput {
  stockMode: StockMode | null
  stockLevel: StockLevel | null
  quantity: string | null
  target: string | null
  reorderPoint: string | null
}

/** Stock fields being edited, always in a definite mode. */
export type StockDraft = StockInput & { stockMode: StockMode }

export interface StockResult {
  stockStatus: StockStatus
  shortfall: string | null
}

export function firstNumber(text: string | null | undefined): number | null {
  const match = text?.match(FIRST_NUMBER_RE)
  return match ? Number(match[0]) : null
}

/** Rounded to six places, so subtracting decimals never shows float noise. */
export function formatNumber(value: number): string {
  return String(Math.round(value * 1e6) / 1e6)
}

/** `text` with its first number swapped for `value`. */
export function withNumber(text: string, value: number): string {
  return text.replace(FIRST_NUMBER_RE, formatNumber(value))
}

function blankToNull(text: string | null | undefined): string | null {
  const trimmed = (text ?? '').trim()
  return trimmed === '' ? null : trimmed
}

export function evaluateStock(item: StockInput): StockResult {
  if (item.stockMode === 'level') {
    if (item.stockLevel !== 'low' && item.stockLevel !== 'out') {
      return { stockStatus: 'ok', shortfall: null }
    }
    return { stockStatus: item.stockLevel, shortfall: blankToNull(item.quantity) }
  }
  const onHandText = (item.quantity ?? '').trim()
  const onHand = onHandText === '' ? 0 : firstNumber(onHandText)
  const target = firstNumber(item.target)
  if (onHand === null || target === null) {
    return { stockStatus: 'uncounted', shortfall: null }
  }
  const reorder = firstNumber(item.reorderPoint)
  const low = reorder !== null ? onHand <= reorder : onHand < target
  if (!low) {
    return { stockStatus: 'ok', shortfall: null }
  }
  const missing = target - onHand
  return {
    stockStatus: onHand === 0 ? 'out' : 'low',
    shortfall: missing > 0 ? withNumber((item.target ?? '').trim(), missing) : null,
  }
}

/** A count item's on hand when added: full (the target) unless one was given. */
export function startingOnHand(
  quantity: string | null | undefined,
  target: string | null | undefined,
): string | null {
  if ((quantity ?? '').trim() !== '' || firstNumber(target) === null) {
    return quantity ?? null
  }
  return (target ?? '').trim()
}

export interface StockConversion {
  stockMode: StockMode
  stockLevel: StockLevel | null
  quantity: string | null
  target: string | null
}

/**
 * The fields a switch of stock mode rewrites. Count to level takes the level
 * from the status (an uncounted item has "some", so it is full) and restocks
 * the shortfall when low, else the target. Level to count makes the restock
 * amount the target and fills on hand to match the level: full, empty, or the
 * reorder point (blank when there is none, for the member to fill in).
 */
export function convertStock(item: StockInput, toMode: StockMode): StockConversion {
  const fromMode: StockMode = item.stockMode === 'level' ? 'level' : 'count'
  if (toMode === fromMode) {
    return {
      stockMode: fromMode,
      stockLevel: item.stockLevel,
      quantity: item.quantity,
      target: item.target,
    }
  }
  if (toMode === 'level') {
    const { stockStatus, shortfall } = evaluateStock(item)
    const level: StockLevel = stockStatus === 'low' || stockStatus === 'out' ? stockStatus : 'full'
    return {
      stockMode: 'level',
      stockLevel: level,
      quantity: level !== 'full' && shortfall !== null ? shortfall : blankToNull(item.target),
      target: item.target,
    }
  }
  const target = blankToNull(item.quantity) ?? blankToNull(item.target)
  const targetText = target ?? ''
  const targetHasNumber = firstNumber(targetText) !== null
  const reorder = firstNumber(item.reorderPoint)
  let onHand: string | null
  switch (item.stockLevel ?? 'full') {
    case 'out':
      onHand = targetHasNumber ? withNumber(targetText, 0) : '0'
      break
    case 'low':
      onHand =
        reorder === null
          ? null
          : targetHasNumber
            ? withNumber(targetText, reorder)
            : formatNumber(reorder)
      break
    default:
      onHand = target
  }
  return { stockMode: 'count', stockLevel: item.stockLevel, quantity: onHand, target }
}

/** The fields of an inventory item the rules read. */
export function stockInput(
  item: { quantity: string | null; stockMode?: StockMode | null } & Omit<
    Partial<ItemStock>,
    'stockMode'
  >,
): StockInput {
  return {
    stockMode: item.stockMode ?? null,
    stockLevel: item.stockLevel ?? null,
    quantity: item.quantity,
    target: item.target ?? null,
    reorderPoint: item.reorderPoint ?? null,
  }
}

/**
 * Whether an inventory item is low, out, expiring or expired, by the statuses
 * the server last sent.
 */
export function needsRestock(item: {
  stockStatus?: StockStatus
  expiryStatus?: ExpiryStatus
}): boolean {
  return (
    item.stockStatus === 'low' ||
    item.stockStatus === 'out' ||
    item.expiryStatus === 'expiring' ||
    item.expiryStatus === 'expired'
  )
}

export const DEFAULT_EXPIRY_LEAD_DAYS = 2

/** The stored fields the expiry rules read. Dates are midnight timestamps in seconds. */
export interface ExpiryInput {
  stockMode: StockMode | null
  stockLevel: StockLevel | null
  quantity: string | null
  expiresAt: number | null
  shelfLifeDays: number | null
}

/** Midnight of today, in seconds, the way date fields resolve "today". */
export function todayEpoch(): number {
  const day = new Date()
  day.setHours(0, 0, 0, 0)
  return Math.floor(day.getTime() / 1000)
}

/** The midnight `days` days after the midnight `day`. */
export function addDays(day: number, days: number): number {
  const date = new Date(day * 1000)
  date.setDate(date.getDate() + days)
  return Math.floor(date.getTime() / 1000)
}

/**
 * Whole days from one midnight to another. Rounded, so a daylight-saving
 * shift, or a date picked at midnight in another timezone, still lands on its
 * day.
 */
export function daysUntil(date: number, today: number): number {
  return Math.round((date - today) / 86400)
}

function isEmpty(item: ExpiryInput): boolean {
  if (item.stockMode === 'level') return item.stockLevel === 'out'
  const onHand = (item.quantity ?? '').trim()
  return onHand === '' || firstNumber(onHand) === 0
}

/**
 * Where an item stands against its expiry date: good through the date,
 * expired from the day after, and expiring from `leadDays` before it while
 * fewer days are left than its shelf life. An item with none left reports
 * none; one not counted still reports.
 */
export function expiryStatus(item: ExpiryInput, leadDays: number, today: number): ExpiryStatus {
  if (item.expiresAt === null || isEmpty(item)) return 'none'
  const daysLeft = daysUntil(item.expiresAt, today)
  if (daysLeft < 0) return 'expired'
  const shelfLife = item.shelfLifeDays
  if (daysLeft <= Math.max(0, leadDays) && (shelfLife === null || daysLeft < shelfLife)) {
    return 'expiring'
  }
  return 'ok'
}

/** The fields of an inventory item the expiry rules read. */
export function expiryInput(
  item: { quantity: string | null; stockMode?: StockMode | null } & Omit<
    Partial<ItemStock>,
    'stockMode'
  >,
): ExpiryInput {
  return {
    stockMode: item.stockMode ?? null,
    stockLevel: item.stockLevel ?? null,
    quantity: item.quantity,
    expiresAt: item.expiresAt ?? null,
    shelfLifeDays: item.shelfLifeDays ?? null,
  }
}

/**
 * What replaces an expiring item: a count item's target, a level item's
 * restock amount.
 */
export function fullAmount(item: StockInput): string | null {
  return blankToNull(item.stockMode === 'level' ? item.quantity : item.target)
}

export type StockTone = 'success' | 'warning' | 'error' | 'muted'

/** The row's tone: red for out or expired over amber for low or expiring. */
export function itemTone(stockStatus: StockStatus, expiry: ExpiryStatus): StockTone {
  const stock = stockTone(stockStatus)
  if (stock === 'error' || expiry === 'expired') return 'error'
  if (stock === 'warning' || expiry === 'expiring') return 'warning'
  return stock
}

export function stockTone(status: StockStatus): StockTone {
  switch (status) {
    case 'out':
      return 'error'
    case 'low':
      return 'warning'
    case 'uncounted':
      return 'muted'
    default:
      return 'success'
  }
}

/**
 * How full the gauge is, 0 to 1: on hand over target for a count item, capped
 * at full, and three segments' worth for a level item. Null when the item is
 * not counted and so has no gauge.
 */
export function gaugeFill(item: StockInput & StockResult): number | null {
  if (item.stockMode === 'level') {
    return item.stockLevel === 'out' ? 0 : item.stockLevel === 'low' ? 1 / 3 : 1
  }
  if (item.stockStatus === 'uncounted') return null
  const onHand = firstNumber(item.quantity) ?? 0
  const target = firstNumber(item.target) ?? 0
  return target <= 0 ? 1 : Math.min(1, onHand / target)
}

/** Where the reorder point sits on a count item's gauge, 0 to 1, or null. */
export function gaugeTick(item: StockInput): number | null {
  if (item.stockMode === 'level') return null
  const reorder = firstNumber(item.reorderPoint)
  const target = firstNumber(item.target)
  if (reorder === null || target === null || target <= 0) return null
  return Math.min(1, reorder / target)
}
