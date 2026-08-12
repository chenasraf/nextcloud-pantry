import { describe, expect, it } from 'vitest'
import { reorderToTrueOrder, type ReorderItem } from './reorderItems'

// Minimal items: the reconstruction only reads id / sortOrder / name.
function item(id: number, sortOrder: number, name = `i${id}`): ReorderItem {
  return { id, sortOrder, name }
}

// Convenience: apply the returned entries and read back the resulting id order.
function orderedIds(
  all: ReorderItem[],
  partition: ReorderItem[],
  dragId: number,
  dropIndex: number,
): number[] {
  const entries = reorderToTrueOrder(all, partition, dragId, dropIndex)
  return [...entries].sort((a, b) => a.sortOrder - b.sortOrder).map((e) => e.id)
}

describe('reorderToTrueOrder', () => {
  it('reproduces a plain within-partition move on an all-unchecked list', () => {
    // [A,B,C,D], drag D between A and B → [A,D,B,C]
    const all = [item(1, 0, 'A'), item(2, 1, 'B'), item(3, 2, 'C'), item(4, 3, 'D')]
    const partition = [...all]
    expect(orderedIds(all, partition, 4, 1)).toEqual([1, 4, 2, 3])
  })

  it('moving an unchecked item leaves checked items pinned between their neighbours', () => {
    // True order: A(unchecked) B(checked) C(unchecked) D(unchecked)
    const A = item(1, 0, 'A')
    const B = item(2, 1, 'B')
    const C = item(3, 2, 'C')
    const D = item(4, 3, 'D')
    const all = [A, B, C, D]
    const unchecked = [A, C, D] // rendered partition, sorted by sortOrder

    // Drag D to sit right after A within the unchecked partition (dropIndex 1
    // against [A,C]) → unchecked becomes [A,D,C].
    const entries = reorderToTrueOrder(all, unchecked, 4, 1)
    const byId = new Map(entries.map((e) => [e.id, e.sortOrder]))

    // D now sits immediately after A; B stays after A and before C.
    expect(byId.get(1)! < byId.get(4)!).toBe(true) // A before D
    expect(byId.get(4)! < byId.get(2)!).toBe(true) // D before B
    expect(byId.get(2)! < byId.get(3)!).toBe(true) // B before C
    // Full reconstructed order.
    expect(orderedIds(all, unchecked, 4, 1)).toEqual([1, 4, 2, 3])
  })

  it('does not change checked items order relative to untouched unchecked neighbours', () => {
    // A(u) B(c) C(u) D(u); reorder C and D among themselves — B must not move
    // relative to A and the unchecked items around it.
    const A = item(1, 0, 'A')
    const B = item(2, 1, 'B')
    const C = item(3, 2, 'C')
    const D = item(4, 3, 'D')
    const all = [A, B, C, D]
    const unchecked = [A, C, D]

    // Drag C to the end of the unchecked partition ([A,D] → dropIndex 2 → [A,D,C]).
    expect(orderedIds(all, unchecked, 3, 2)).toEqual([1, 2, 4, 3])
    // B (checked) keeps sortOrder slot right after A, before the unchecked tail.
  })

  it('reorders within the checked partition without touching unchecked items', () => {
    // A(u) B(c) C(u) D(c) — drag D above B within the checked partition.
    const A = item(1, 0, 'A')
    const B = item(2, 1, 'B')
    const C = item(3, 2, 'C')
    const D = item(4, 3, 'D')
    const all = [A, B, C, D]
    const checked = [B, D]

    // [B] with D removed; dropIndex 0 → D before B.
    expect(orderedIds(all, checked, 4, 0)).toEqual([1, 4, 2, 3])
  })

  it('keeps its true slot when the dragged item is alone in its partition', () => {
    // Only B is checked; dragging it is a no-op for stored order.
    const A = item(1, 0, 'A')
    const B = item(2, 1, 'B')
    const C = item(3, 2, 'C')
    const all = [A, B, C]
    const checked = [B]
    expect(orderedIds(all, checked, 2, 0)).toEqual([1, 2, 3])
  })

  it('places a first-in-partition drop before its successor', () => {
    const A = item(1, 0, 'A')
    const B = item(2, 1, 'B')
    const C = item(3, 2, 'C')
    const all = [A, B, C]
    const partition = [...all]
    // Drag C to the very front → [C,A,B].
    expect(orderedIds(all, partition, 3, 0)).toEqual([3, 1, 2])
  })

  it('returns an empty array when the dragged id is not in the partition', () => {
    const all = [item(1, 0), item(2, 1)]
    expect(reorderToTrueOrder(all, [item(1, 0)], 99, 0)).toEqual([])
  })

  it('breaks equal sortOrder ties by name for a stable true order', () => {
    // Legacy data: everything defaulted to sortOrder 0, so the true order falls
    // back to name — Apple(1), Banana(2), Cherry(3).
    const a = item(1, 0, 'Apple')
    const b = item(2, 0, 'Banana')
    const c = item(3, 0, 'Cherry')
    const all = [c, a, b] // unsorted input
    const partition = [a, b, c] // rendered order (name-sorted for equal sortOrder)
    // Drag Cherry to the front → [Cherry, Apple, Banana].
    expect(orderedIds(all, partition, 3, 0)).toEqual([3, 1, 2])
  })
})
