// Store-view grouping/ordering. Store sort is a client-only lens over the one
// shared per-list `sortOrder`: an item assigned to several stores renders under
// each, and within a store group items follow `sortOrder` (tie-break name) — the
// same model as category sort. Dragging reorders that shared sortOrder, so a
// multi-store item shifts in every group it appears in (coupled, by design).

export interface StoreGroupedItem {
  id: number
  storeIds?: number[] | null
  sortOrder: number
  name: string
}

// The reorder group key for a store column, or the trailing "No store" group.
// Shared by the rendered rows (data-drag-group) and the drag-scope logic so a
// store-sort drag stays inside its own column.
export function storeGroupKey(storeId: number | null): string {
  return storeId == null ? 's-none' : `s-${storeId}`
}

export function byStoreGroupOrder<T extends StoreGroupedItem>(a: T, b: T): number {
  return a.sortOrder - b.sortOrder || a.name.localeCompare(b.name)
}

/**
 * Items rendered under a given store group key, ordered exactly as the group
 * renders. `s-none` selects items with no *known* store (an assignment to a
 * store not in `knownStoreIds` doesn't count, matching the render's buckets).
 *
 * @param items         Candidate items.
 * @param groupKey      Group key from {@link storeGroupKey}.
 * @param knownStoreIds Ids of stores that currently have a header.
 */
export function itemsInStoreGroup<T extends StoreGroupedItem>(
  items: T[],
  groupKey: string,
  knownStoreIds: Set<number>,
): T[] {
  const inGroup =
    groupKey === storeGroupKey(null)
      ? (i: T) => (i.storeIds ?? []).filter((id) => knownStoreIds.has(id)).length === 0
      : (i: T) => {
          const sid = Number(groupKey.slice(2))
          return (i.storeIds ?? []).includes(sid)
        }
  return items.filter(inGroup).sort(byStoreGroupOrder)
}
