// Per-store category order. A store may be arranged into the order its aisles
// are walked, overriding the house-wide category order while shopping there.
//
// A store names only the categories it has been arranged with, so the full
// order is always "arranged first, in the store's order; everything else after,
// in the house-wide order". That fallback is what carries the lifecycle: a
// category created after the arrangement simply has no entry and lands at the
// end, and a deleted one drops out. The server orders shopping items by exactly
// this rule, so composing it the same way here keeps the reorder dialog showing
// what the trip will show.

import type { Category } from '@/api/types'

/**
 * The house-wide order: `sortOrder`, tie-broken by name. Shopping mode reads
 * this order whatever the viewer's category sort preference is, since a trip is
 * walked in one arrangement rather than per-viewer.
 */
export function byHouseCategoryOrder(a: Category, b: Category): number {
  return a.sortOrder - b.sortOrder || a.name.localeCompare(b.name)
}

/**
 * Categories in the order `storeId` is walked.
 *
 * @param categories   Every category the house has.
 * @param arrangedIds  Category ids the store arranges, in its own order. Ids
 *                     that no longer name a category are ignored.
 */
export function orderCategoriesForStore(categories: Category[], arrangedIds: number[]): Category[] {
  const byId = new Map(categories.map((c) => [c.id, c]))
  const seen = new Set<number>()
  const arranged: Category[] = []
  for (const id of arrangedIds) {
    const cat = byId.get(id)
    if (cat && !seen.has(id)) {
      seen.add(id)
      arranged.push(cat)
    }
  }
  const rest = categories.filter((c) => !seen.has(c.id)).sort(byHouseCategoryOrder)
  return [...arranged, ...rest]
}
