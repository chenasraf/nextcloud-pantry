import { describe, expect, it } from 'vitest'
import {
  storeGroupKey,
  byStoreGroupOrder,
  itemsInStoreGroup,
  type StoreGroupedItem,
} from './storeGroupOrder'

function item(
  id: number,
  storeIds: number[] | null,
  sortOrder: number,
  name = `i${id}`,
): StoreGroupedItem {
  return { id, storeIds, sortOrder, name }
}

const ids = (items: StoreGroupedItem[]) => items.map((i) => i.id)
const known = (...s: number[]) => new Set(s)

describe('storeGroupKey', () => {
  it('encodes a store id and the no-store group distinctly', () => {
    expect(storeGroupKey(3)).toBe('s-3')
    expect(storeGroupKey(null)).toBe('s-none')
  })
})

describe('byStoreGroupOrder', () => {
  it('orders by sortOrder then name', () => {
    const items = [item(1, [1], 1, 'B'), item(2, [1], 0, 'A'), item(3, [1], 0, 'C')]
    expect(ids([...items].sort(byStoreGroupOrder))).toEqual([2, 3, 1])
  })
})

describe('itemsInStoreGroup', () => {
  it('selects items assigned to the given store, ordered by sortOrder', () => {
    const items = [item(1, [3], 1, 'Milk'), item(2, [3], 0, 'Butter'), item(3, [9], 0, 'Bread')]
    expect(ids(itemsInStoreGroup(items, storeGroupKey(3), known(3, 9)))).toEqual([2, 1])
  })

  it('includes a multi-store item under each of its stores (coupling)', () => {
    // Eggs (id 2) is in both store 3 and store 9.
    const items = [item(1, [3], 0, 'Milk'), item(2, [3, 9], 1, 'Eggs'), item(3, [9], 0, 'Bread')]
    expect(ids(itemsInStoreGroup(items, storeGroupKey(3), known(3, 9)))).toEqual([1, 2])
    expect(ids(itemsInStoreGroup(items, storeGroupKey(9), known(3, 9)))).toEqual([3, 2])
  })

  it('groups items with no known store under s-none', () => {
    const items = [item(1, null, 0, 'Loose'), item(2, [], 1, 'AlsoLoose'), item(3, [3], 0, 'Milk')]
    expect(ids(itemsInStoreGroup(items, storeGroupKey(null), known(3)))).toEqual([1, 2])
  })

  it('treats an assignment to an unknown store as no store', () => {
    // Store 99 has no header (not in knownStoreIds) → item falls under s-none.
    const items = [item(1, [99], 0, 'Orphan'), item(2, [3], 0, 'Milk')]
    expect(ids(itemsInStoreGroup(items, storeGroupKey(null), known(3)))).toEqual([1])
    expect(ids(itemsInStoreGroup(items, storeGroupKey(3), known(3)))).toEqual([2])
  })

  it('does not mutate the input array', () => {
    const items = [item(2, [3], 1), item(1, [3], 0)]
    const snapshot = ids(items)
    itemsInStoreGroup(items, storeGroupKey(3), known(3))
    expect(ids(items)).toEqual(snapshot)
  })
})
