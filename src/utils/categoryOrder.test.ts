import { describe, expect, it } from 'vitest'
import { orderItemsByCategory, type CategorizedItem } from './categoryOrder'

function item(
  id: number,
  categoryId: number | null,
  sortOrder: number,
  name = `i${id}`,
): CategorizedItem {
  return { id, categoryId, sortOrder, name }
}

const ids = (items: CategorizedItem[]) => items.map((i) => i.id)

describe('orderItemsByCategory', () => {
  it('groups by category header order, then by sortOrder within a group', () => {
    // Category header order: 10 (Fruit) before 20 (Dairy).
    const items = [
      item(1, 20, 1, 'Milk'),
      item(2, 10, 1, 'Banana'),
      item(3, 10, 0, 'Apple'),
      item(4, 20, 0, 'Butter'),
    ]
    // Fruit group by sortOrder: Apple(0), Banana(1); Dairy: Butter(0), Milk(1).
    expect(ids(orderItemsByCategory(items, [10, 20]))).toEqual([3, 2, 4, 1])
  })

  it('places uncategorized items last regardless of sortOrder', () => {
    const items = [item(1, null, 0, 'Loose'), item(2, 10, 5, 'Apple')]
    expect(ids(orderItemsByCategory(items, [10]))).toEqual([2, 1])
  })

  it('honours the given header order (categorySort lens)', () => {
    const items = [item(1, 10, 0, 'Apple'), item(2, 20, 0, 'Milk')]
    // Reverse header order (e.g. name_desc) puts Dairy first.
    expect(ids(orderItemsByCategory(items, [20, 10]))).toEqual([2, 1])
  })

  it('breaks equal sortOrder ties by name', () => {
    const items = [item(3, 10, 0, 'Cherry'), item(1, 10, 0, 'Apple'), item(2, 10, 0, 'Banana')]
    expect(ids(orderItemsByCategory(items, [10]))).toEqual([1, 2, 3])
  })

  it('treats items in an unknown/unloaded category as uncategorized (trailing)', () => {
    const items = [item(1, 99, 0, 'Orphan'), item(2, 10, 0, 'Apple')]
    expect(ids(orderItemsByCategory(items, [10]))).toEqual([2, 1])
  })

  it('does not mutate the input array', () => {
    const items = [item(2, 10, 1), item(1, 10, 0)]
    const snapshot = ids(items)
    orderItemsByCategory(items, [10])
    expect(ids(items)).toEqual(snapshot)
  })
})
