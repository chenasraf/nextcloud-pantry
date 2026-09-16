import { describe, expect, it } from 'vitest'
import { orderCategoriesForStore } from './storeCategoryOrder'
import type { Category } from '@/api/types'

function cat(id: number, sortOrder: number, name = `c${id}`): Category {
  return {
    id,
    houseId: 1,
    listId: null,
    name,
    icon: 'tag',
    color: '#22c55e',
    sortOrder,
    createdAt: 0,
    updatedAt: 0,
  }
}

const ids = (cats: Category[]) => cats.map((c) => c.id)

describe('orderCategoriesForStore', () => {
  it('falls back to the house-wide order when the store arranges nothing', () => {
    const cats = [cat(1, 2), cat(2, 0), cat(3, 1)]
    expect(ids(orderCategoriesForStore(cats, []))).toEqual([2, 3, 1])
  })

  it('leads with the arranged categories in the store order', () => {
    const cats = [cat(1, 0), cat(2, 1), cat(3, 2)]
    expect(ids(orderCategoriesForStore(cats, [3, 1, 2]))).toEqual([3, 1, 2])
  })

  it('trails a category added after the arrangement, matching where the house order appends it', () => {
    const cats = [cat(1, 0), cat(2, 1), cat(3, 2)]
    // The store was arranged before category 3 existed.
    expect(ids(orderCategoriesForStore(cats, [2, 1]))).toEqual([2, 1, 3])
  })

  it('orders several unarranged categories among themselves by the house order', () => {
    const cats = [cat(1, 0), cat(2, 5), cat(3, 3), cat(4, 4)]
    expect(ids(orderCategoriesForStore(cats, [1]))).toEqual([1, 3, 4, 2])
  })

  it('ignores ids of categories that no longer exist', () => {
    const cats = [cat(1, 0), cat(2, 1)]
    expect(ids(orderCategoriesForStore(cats, [99, 2, 1]))).toEqual([2, 1])
  })

  it('collapses a duplicated id to its first position', () => {
    const cats = [cat(1, 0), cat(2, 1), cat(3, 2)]
    expect(ids(orderCategoriesForStore(cats, [3, 1, 3, 2]))).toEqual([3, 1, 2])
  })

  it('breaks equal house sortOrder ties by name', () => {
    const cats = [cat(3, 0, 'Cherry'), cat(1, 0, 'Apple'), cat(2, 0, 'Banana')]
    expect(ids(orderCategoriesForStore(cats, []))).toEqual([1, 2, 3])
  })
})
