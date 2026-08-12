// Reseed a list's custom order from a chosen basis. Computes a fresh 0..n
// `sortOrder` sequence the caller writes via the reorder endpoint; the list
// stays hand-reorderable afterwards. In category view the reseed is grouped by
// category (header order, uncategorized last) and ordered by the basis within
// each group, so grouping is preserved; otherwise it is a flat basis order
// (which still leaves each store group basis-ordered, since a group is a subset
// of the flat sequence).

export type ReseedBasis = 'dateAdded' | 'name_asc' | 'name_desc'

export interface ReseedItem {
  id: number
  name: string
  createdAt: number
  categoryId: number | null
}

function comparatorFor<T extends ReseedItem>(basis: ReseedBasis): (a: T, b: T) => number {
  switch (basis) {
    case 'name_asc':
      return (a, b) => a.name.localeCompare(b.name)
    case 'name_desc':
      return (a, b) => b.name.localeCompare(a.name)
    default: // dateAdded — oldest first, matching "Date added"
      return (a, b) => a.createdAt - b.createdAt
  }
}

/**
 * @param items         Items to reseed (any input order).
 * @param basis         The ordering basis.
 * @param categoryOrder Category ids in header order — pass in category view to
 *                      group the reseed by category; omit for a flat reseed.
 */
export function reseedOrder<T extends ReseedItem>(
  items: T[],
  basis: ReseedBasis,
  categoryOrder?: number[],
): { id: number; sortOrder: number }[] {
  const cmp = comparatorFor<T>(basis)
  let ordered: T[]
  if (categoryOrder) {
    const rank = new Map(categoryOrder.map((id, i) => [id, i]))
    const rankOf = (categoryId: number | null): number =>
      categoryId != null && rank.has(categoryId) ? rank.get(categoryId)! : Number.MAX_SAFE_INTEGER
    ordered = [...items].sort((a, b) => rankOf(a.categoryId) - rankOf(b.categoryId) || cmp(a, b))
  } else {
    ordered = [...items].sort(cmp)
  }
  return ordered.map((it, n) => ({ id: it.id, sortOrder: n }))
}
