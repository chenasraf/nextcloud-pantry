import type { ChecklistItem } from '@/api/types'
import { needsRestock } from '@/utils/stock'

/** Stock filter row: items that are low or out. */
export const STOCK_NEEDS_RESTOCK = 1
/** Stock filter row: everything else. */
export const STOCK_IN_STOCK = 2

/** The `stock` query value that opens an inventory on each row. */
const QUERY_VALUES: Record<number, string> = {
  [STOCK_NEEDS_RESTOCK]: 'restock',
  [STOCK_IN_STOCK]: 'in-stock',
}

/**
 * Whether an item passes the stock filter. Selecting nothing or both rows
 * shows everything.
 */
export function matchesStockFilter(item: ChecklistItem, selected: number[]): boolean {
  const restock = selected.includes(STOCK_NEEDS_RESTOCK)
  const inStock = selected.includes(STOCK_IN_STOCK)
  if (restock === inStock) return true
  return needsRestock(item) === restock
}

/** The selection a `?stock=` query value asks for. */
export function stockFilterFromQuery(value: unknown): number[] {
  const raw = Array.isArray(value) ? value[0] : value
  const match = Object.entries(QUERY_VALUES).find(([, v]) => v === raw)
  return match ? [Number(match[0])] : []
}

/** The `?stock=` query value for a selection, or undefined when it shows everything. */
export function stockFilterQuery(selected: number[]): string | undefined {
  return selected.length === 1 ? QUERY_VALUES[selected[0]!] : undefined
}
