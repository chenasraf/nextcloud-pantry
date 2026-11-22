import { describe, expect, it } from 'vitest'

import type { ItemCategoryMemory } from '@/api/itemMemory'
import { emptyMemoryIndex, mergeMemory, pickCategory, pickQuantity } from './itemMemory'

function cat(
  categoryId: number,
  uses: number,
  rejections: number,
  updatedAt = 1,
): ItemCategoryMemory {
  return { nameKey: 'milk', categoryId, uses, rejections, updatedAt }
}

describe('pickCategory', () => {
  it('picks the best score among usable categories', () => {
    const index = mergeMemory(emptyMemoryIndex(), [cat(1, 5, 0), cat(2, 3, 0), cat(3, 9, 0)], [])

    expect(pickCategory(index, 'milk', new Set([1, 2]))).toBe(1)
  })

  it('counts rejections against uses', () => {
    const index = mergeMemory(emptyMemoryIndex(), [cat(1, 5, 4), cat(2, 3, 0)], [])

    expect(pickCategory(index, 'milk', new Set([1, 2]))).toBe(2)
  })

  it('never suggests a category scored zero or below', () => {
    const index = mergeMemory(emptyMemoryIndex(), [cat(1, 2, 2), cat(2, 0, 1)], [])

    expect(pickCategory(index, 'milk', new Set([1, 2]))).toBeNull()
  })

  it('breaks a tie with the most recently changed', () => {
    const index = mergeMemory(emptyMemoryIndex(), [cat(1, 3, 0, 10), cat(2, 3, 0, 20)], [])

    expect(pickCategory(index, 'milk', new Set([1, 2]))).toBe(2)
  })

  it('knows nothing of an unknown name', () => {
    expect(pickCategory(emptyMemoryIndex(), 'milk', new Set([1]))).toBeNull()
  })
})

describe('mergeMemory', () => {
  it('replaces a category row with its newer copy', () => {
    let index = mergeMemory(emptyMemoryIndex(), [cat(1, 5, 0)], [])
    index = mergeMemory(index, [cat(1, 5, 6)], [])

    expect(index.categories.get('milk')).toEqual([cat(1, 5, 6)])
  })

  it('remembers amounts per list and forgets one synced as null', () => {
    let index = mergeMemory(
      emptyMemoryIndex(),
      [],
      [
        { listId: 1, nameKey: 'milk', quantity: '2 L', updatedAt: 1 },
        { listId: 2, nameKey: 'milk', quantity: '1 L', updatedAt: 1 },
      ],
    )
    expect(pickQuantity(index, 1, 'milk')).toBe('2 L')
    expect(pickQuantity(index, 2, 'milk')).toBe('1 L')

    index = mergeMemory(index, [], [{ listId: 1, nameKey: 'milk', quantity: null, updatedAt: 2 }])

    expect(pickQuantity(index, 1, 'milk')).toBeNull()
    expect(pickQuantity(index, 2, 'milk')).toBe('1 L')
  })
})
