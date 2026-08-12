import { describe, expect, it } from 'vitest'
import { reseedOrder, type ReseedItem } from './reseedOrder'

function item(
  id: number,
  name: string,
  createdAt: number,
  categoryId: number | null = null,
): ReseedItem {
  return { id, name, createdAt, categoryId }
}

// Map the reseed result to the item ids in assigned sortOrder order.
function order(entries: { id: number; sortOrder: number }[]): number[] {
  return [...entries].sort((a, b) => a.sortOrder - b.sortOrder).map((e) => e.id)
}

describe('reseedOrder (flat)', () => {
  const items = [item(1, 'Banana', 30), item(2, 'Apple', 10), item(3, 'Cherry', 20)]

  it('assigns a contiguous 0..n sequence', () => {
    const entries = reseedOrder(items, 'name_asc')
    expect([...entries].map((e) => e.sortOrder).sort((a, b) => a - b)).toEqual([0, 1, 2])
  })

  it('orders by date added (oldest first)', () => {
    expect(order(reseedOrder(items, 'dateAdded'))).toEqual([2, 3, 1])
  })

  it('orders by name A–Z', () => {
    expect(order(reseedOrder(items, 'name_asc'))).toEqual([2, 1, 3])
  })

  it('orders by name Z–A', () => {
    expect(order(reseedOrder(items, 'name_desc'))).toEqual([3, 1, 2])
  })
})

describe('reseedOrder (grouped by category)', () => {
  // Category header order: 10 before 20; uncategorized (null) last.
  const items = [
    item(1, 'Milk', 5, 20),
    item(2, 'Banana', 6, 10),
    item(3, 'Apple', 7, 10),
    item(4, 'Loose', 1, null),
    item(5, 'Butter', 2, 20),
  ]

  it('groups by category then orders by the basis within each group', () => {
    // cat10 by name: Apple(3), Banana(2); cat20: Butter(5), Milk(1); then null: Loose(4).
    expect(order(reseedOrder(items, 'name_asc', [10, 20]))).toEqual([3, 2, 5, 1, 4])
  })

  it('keeps uncategorized items last even with an earlier createdAt', () => {
    // dateAdded within groups: cat10 Banana(6),Apple(7); cat20 Butter(2),Milk(5); null Loose(1).
    expect(order(reseedOrder(items, 'dateAdded', [10, 20]))).toEqual([2, 3, 5, 1, 4])
  })

  it('honours the given category header order', () => {
    // Reverse header order (20 before 10) flips the group order.
    expect(order(reseedOrder(items, 'name_asc', [20, 10]))).toEqual([5, 1, 3, 2, 4])
  })
})

describe('reseedOrder edge cases', () => {
  it('returns an empty array for no items', () => {
    expect(reseedOrder([], 'name_asc')).toEqual([])
  })

  it('does not mutate the input array', () => {
    const items = [item(2, 'B', 2), item(1, 'A', 1)]
    const snapshot = items.map((i) => i.id)
    reseedOrder(items, 'name_asc')
    expect(items.map((i) => i.id)).toEqual(snapshot)
  })
})
