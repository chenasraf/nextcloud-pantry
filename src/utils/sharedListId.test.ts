import { describe, expect, it } from 'vitest'

import { sharedListId } from './sharedListId'

describe('sharedListId', () => {
  it('returns null for no items', () => {
    expect(sharedListId([])).toBeNull()
  })

  it('returns the list of a single item', () => {
    expect(sharedListId([{ listId: 4 }])).toBe(4)
  })

  it('returns the list when every item shares it', () => {
    expect(sharedListId([{ listId: 4 }, { listId: 4 }, { listId: 4 }])).toBe(4)
  })

  it('returns null when items span lists', () => {
    expect(sharedListId([{ listId: 4 }, { listId: 7 }])).toBeNull()
  })
})
