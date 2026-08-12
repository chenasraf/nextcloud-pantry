// Category-view ordering. There is one shared per-list `sortOrder`; the category
// view is a lens over it. Items are grouped by category following the
// category-header order (the `categorySort` pref, already baked into the ordered
// category list the caller passes), with uncategorized items last, and ordered
// within each group by `sortOrder` (tie-break name). Doing this on the client
// keeps optimistic drag updates correct without a reload.

export interface CategorizedItem {
  id: number
  categoryId: number | null
  sortOrder: number
  name: string
}

/**
 * Order `items` for the category view.
 *
 * @param items         Items to order (any input order).
 * @param categoryOrder Category ids in header order (uncategorized is implicit
 *                      and always trails). Ids not present here are treated as
 *                      uncategorized.
 */
export function orderItemsByCategory<T extends CategorizedItem>(
  items: T[],
  categoryOrder: number[],
): T[] {
  const rank = new Map(categoryOrder.map((id, i) => [id, i]))
  const rankOf = (categoryId: number | null): number =>
    categoryId != null && rank.has(categoryId) ? rank.get(categoryId)! : Number.MAX_SAFE_INTEGER
  return [...items].sort(
    (a, b) =>
      rankOf(a.categoryId) - rankOf(b.categoryId) ||
      a.sortOrder - b.sortOrder ||
      a.name.localeCompare(b.name),
  )
}
