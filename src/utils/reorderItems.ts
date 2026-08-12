// Reorder reconstruction for a checklist's custom sort order (ADR 0010,
// decision 3). A drag happens within one render-time partition (the active
// list or the Done section), but `sort_order` must store the user's *true*
// intended order across both — an item's checked-state must never leak into
// its stored order. So we translate a within-partition drop into a slot in the
// full list order: only the dragged item moves, and every other item — checked
// ones included — keeps its true position between its surviving neighbours.

export interface ReorderItem {
  id: number
  sortOrder: number
  name: string
}

/**
 * Compute the full-list `{ id, sortOrder }` sequence after dropping `dragId`
 * at `dropIndex` within `partition`.
 *
 * @param allItems  Every item currently in scope (both partitions), in any order.
 * @param partition The dragged item's partition, already ordered as rendered.
 * @param dragId    The id of the dragged item.
 * @param dropIndex The drop position within `partition` (as computed against the
 *                  partition with the dragged item removed).
 * @returns The renumbered order for all of `allItems`, or an empty array when
 *          `dragId` is not part of `partition`.
 */
export function reorderToTrueOrder<T extends ReorderItem>(
  allItems: T[],
  partition: T[],
  dragId: number,
  dropIndex: number,
): { id: number; sortOrder: number }[] {
  const dragged = partition.find((i) => i.id === dragId)
  if (!dragged) return []

  // New order within the dragged partition after the drop.
  const without = partition.filter((i) => i.id !== dragId)
  const clamped = Math.min(dropIndex, without.length)
  const reordered = [...without]
  reordered.splice(clamped, 0, dragged)

  // The dragged item lands next to its new neighbour within the partition; it
  // keeps its true slot only when it has no neighbour to anchor against (alone
  // in its partition — nothing to reorder).
  const trueOrder = [...allItems].sort(
    (a, b) => a.sortOrder - b.sortOrder || a.name.localeCompare(b.name),
  )
  const origIndex = trueOrder.findIndex((i) => i.id === dragId)
  const finalOrder = trueOrder.filter((i) => i.id !== dragId)

  const posInPartition = reordered.findIndex((i) => i.id === dragId)
  const predecessor = posInPartition > 0 ? reordered[posInPartition - 1] : null
  const successor = posInPartition < reordered.length - 1 ? reordered[posInPartition + 1] : null

  let insertAt: number
  if (predecessor) {
    insertAt = finalOrder.findIndex((i) => i.id === predecessor.id) + 1
  } else if (successor) {
    insertAt = finalOrder.findIndex((i) => i.id === successor.id)
  } else {
    insertAt = origIndex === -1 ? finalOrder.length : origIndex
  }
  finalOrder.splice(insertAt, 0, dragged)

  return finalOrder.map((i, n) => ({ id: i.id, sortOrder: n }))
}
